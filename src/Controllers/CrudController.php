<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Model;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;

/**
 * Shared REST controller for the flat lookup/settings resources.
 *
 * Concrete controllers only need to declare the target `$model` (and,
 * optionally, validation `$rules` / index-time `$orderBy`). Actions:
 *
 *   GET    /{resource}        -> index()
 *   POST   /{resource}        -> store()
 *   GET    /{resource}/{id}   -> show()
 *   PUT    /{resource}/{id}   -> update()
 *   DELETE /{resource}/{id}   -> destroy()  (soft delete)
 *
 * Soft-delete aware: list/show exclude rows with `deleted_at` set, and destroy
 * sets `deleted_at` rather than issuing a hard DELETE.
 */
abstract class CrudController
{
    /** FQCN of the model this controller manages. */
    protected string $model = '';

    /** Validation rules keyed by field (e.g. 'name' => 'required|string|max:255'). */
    protected array $rules = [];

    /** Default ordering for index(). */
    protected string $orderBy = 'id';
    protected string $orderDir = 'ASC';

    /** Columns the `?search=` query term is matched against (LIKE). */
    protected array $searchable = ['name'];

    /** Cached column list for the managed table (used for auto-scoping). */
    private ?array $columns = null;

    /** Optional index()-time where-in constraints applied in `applyFilters`. */
    protected array $optionals = [];

    /** @return Model a fresh model instance. */
    protected function instance(): Model
    {
        $class = $this->model;
        return new $class();
    }

    /**
     * Hook for subclasses (e.g. CityController) to narrow the index result by
     * query parameters. Default implementation does nothing.
     */
    protected function applyFilters(Request $request, \Iterp\Core\QueryBuilder $query): void
    {
    }

    public function index(Request $request): Response
    {
        $query = $this->instance()::query();
        $query->whereNull('deleted_at');
        $this->applyFilters($request, $query);

        // Auto-scope the result by the global tenant / firm / academic year
        // context picked in the header switcher. Only applied when the target
        // table actually has the column, so lookups that don't belong to a firm
        // (countries, states, cities, titles, etc.) are left untouched.
        $columns = $this->tableColumns();
        foreach ($this->scopeParams() as $column => $param) {
            if (in_array($column, $columns, true)) {
                $value = $request->query($param);
                if ($value !== null && $value !== '') {
                    $query->where($column, (int) $value);
                }
            }
        }

        // Full-text-ish search across the configured searchable columns.
        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '' && $this->searchable !== []) {
            $query->search($this->searchable, trim((string) $search));
        }

        $query->orderBy($this->orderBy, $this->orderDir);

        // Paginate only when page/per_page are requested; otherwise return the
        // flat list so option loaders / the global switcher keep working.
        $paginate = $request->query('page') !== null || $request->query('per_page') !== null;
        if ($paginate) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = (int) $request->query('per_page', 10);
            if ($perPage < 1) {
                $perPage = 10;
            }
            if ($perPage > 100) {
                $perPage = 100;
            }

            $total = $query->count();
            $query->limit($perPage)->offset(($page - 1) * $perPage);

            $items = [];
            foreach ($query->get() as $model) {
                $items[] = $model->toArray();
            }

            return Response::success([
                'items'       => $items,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $total === 0 ? 1 : (int) ceil($total / $perPage),
            ]);
        }

        $items = [];
        foreach ($query->get() as $model) {
            $items[] = $model->toArray();
        }

        return Response::success($items);
    }

    /**
     * Map a query-string parameter to a scope column. Subclasses may override
     * to add (or remove) auto-applied context filters.
     */
    protected function scopeParams(): array
    {
        return [
            'firm_id'          => 'firm_id',
            'academic_year_id' => 'academic_year_id',
            'tenant_id'        => 'tenant_id',
        ];
    }

    /** Column names of the resolved model's table (cached per call). */
    protected function tableColumns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }
        $rows = Database::pdo()->query('SHOW COLUMNS FROM `' . $this->instance()->table() . '`')->fetchAll(\PDO::FETCH_ASSOC);
        $this->columns = array_column($rows ?: [], 'Field');
        return $this->columns;
    }

    /**
     * Hook invoked before validation/persist during store()/update(). Subclasses
     * may mutate `$data` (e.g. to inject a server-resolved tenant_id) or return
     * a Response to short-circuit (e.g. 404 when a domain doesn't match).
     */
    protected function beforePersist(Request $request, array &$data): ?Response
    {
        return null;
    }

    public function store(Request $request): Response
    {
        $data = $request->all();

        if (($early = $this->beforePersist($request, $data)) !== null) {
            return $early;
        }

        // Auto-assign scope columns from query/context if the table has them
        $columns = $this->tableColumns();
        foreach ($this->scopeParams() as $column => $param) {
            if (in_array($column, $columns, true)) {
                if (!isset($data[$column]) || $data[$column] === '' || $data[$column] === null) {
                    $val = $request->query($param) ?? $request->input($param);
                    if ($val !== null && $val !== '') {
                        $data[$column] = (int) $val;
                    }
                }
            }
        }

        $validator = Validator::make($data, $this->rules());
        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $model = $this->instance();
        $model->fill($this->payload($data));

        if (!$model->save()) {
            return Response::error('Could not save the record.', 500);
        }

        return Response::success($model->toArray(), 'Record created.', 201);
    }

    public function show(Request $request, array $context, array $params): Response
    {
        $model = $this->instance()::find($params['id']);
        if ($model === null || !empty($model->deleted_at)) {
            return Response::error('Record not found.', 404);
        }
        return Response::success($model->toArray());
    }

    public function update(Request $request, array $context, array $params): Response
    {
        $model = $this->instance()::find($params['id']);
        if ($model === null || !empty($model->deleted_at)) {
            return Response::error('Record not found.', 404);
        }

        $data = $request->all();

        if (($early = $this->beforePersist($request, $data)) !== null) {
            return $early;
        }

        $validator = Validator::make($data, $this->rules());
        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $model->fill($this->payload($data));
        if (!$model->save()) {
            return Response::error('Could not update the record.', 500);
        }

        return Response::success($model->toArray(), 'Record updated.');
    }

    /** Soft delete: stamp deleted_at instead of removing the row. */
    public function destroy(Request $request, array $context, array $params): Response
    {
        $model = $this->instance()::find($params['id']);
        if ($model === null || !empty($model->deleted_at)) {
            return Response::error('Record not found.', 404);
        }

        // No hard delete anywhere — just stamp deleted_at so the row is hidden
        // from lists but remains recoverable / auditable.
        Database::pdo()->prepare(
            'UPDATE `' . $model->table() . '` SET deleted_at = NOW() WHERE `' . $model->primaryKey() . '` = :id'
        )->execute(['id' => $model->toArray()['id']]);

        return Response::success(null, 'Record deleted.');
    }

    /** Overridable: normalize request data before it is validated/persisted. */
    protected function payload(array $data): array
    {
        return $data;
    }

    protected function rules(): array
    {
        return $this->rules;
    }
}