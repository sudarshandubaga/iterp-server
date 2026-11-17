<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Models\City;

/**
 * CRUD API for cities (Master Settings > Location > City).
 * Joins `states` table so every city returned includes `state_name` ("City, State").
 * Optionally filter by `?state_id=` to list cities of a state.
 */
class CityController extends CrudController
{
    protected string $model = City::class;

    protected array $rules = [
        'state_id'   => 'required|integer|exists:states,id',
        'name'       => 'required|string|max:255',
        'short_name' => 'required|string|max:50',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name', 'short_name'];

    public function index(Request $request): Response
    {
        $pdo = Database::pdo();
        $where = ['c.deleted_at IS NULL'];
        $params = [];

        if ($request->query('state_id') !== null && $request->query('state_id') !== '') {
            $where[] = 'c.state_id = :state_id';
            $params['state_id'] = (int) $request->query('state_id');
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $where[] = '(c.name LIKE :s1 OR c.short_name LIKE :s2 OR s.name LIKE :s3)';
            $params['s1'] = '%' . $search . '%';
            $params['s2'] = '%' . $search . '%';
            $params['s3'] = '%' . $search . '%';
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT c.*, s.name AS state_name 
                FROM cities c
                LEFT JOIN states s ON s.id = c.state_id
                WHERE {$whereClause}
                ORDER BY c.name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // If pagination requested
        if ($request->query('page') !== null || $request->query('per_page') !== null) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, (int) $request->query('per_page', 25));
            $total = count($items);
            $sliced = array_slice($items, ($page - 1) * $perPage, $perPage);
            return Response::success([
                'items'       => $sliced,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ]);
        }

        return Response::success($items);
    }

    protected function applyFilters(Request $request, \Iterp\Core\QueryBuilder $query): void
    {
        if ($request->query('state_id') !== null) {
            $query->where('state_id', (int) $request->query('state_id'));
        }
    }
}
