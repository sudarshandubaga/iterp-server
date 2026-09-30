<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\FeeHead;

/**
 * Controller for Fee Heads.
 *
 * Fields:
 * - name (required)
 * - is_admission_fee (boolean)
 * - is_refundable_fee (boolean)
 * - is_once_a_year (boolean)
 * - is_once_a_career (boolean)
 * - student_category_ids (array/json)
 * - firm_id (integer)
 */
class FeeHeadController
{
    public function index(Request $request): Response
    {
        $query = FeeHead::query();
        $query->whereNull('deleted_at');

        // Scoping by firm_id
        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $query->where('firm_id', (int) $firmId);
        }

        // Search by name
        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '') {
            $query->search(['name'], trim((string) $search));
        }

        // Filter by flags if requested
        if ($request->query('is_admission_fee') !== null) {
            $query->where('is_admission_fee', (int) $request->query('is_admission_fee'));
        }
        if ($request->query('is_refundable_fee') !== null) {
            $query->where('is_refundable_fee', (int) $request->query('is_refundable_fee'));
        }
        if ($request->query('is_once_a_year') !== null) {
            $query->where('is_once_a_year', (int) $request->query('is_once_a_year'));
        }
        if ($request->query('is_once_a_career') !== null) {
            $query->where('is_once_a_career', (int) $request->query('is_once_a_career'));
        }

        $query->orderBy('name', 'ASC');

        // Pagination if requested
        $paginate = $request->query('page') !== null || $request->query('per_page') !== null;
        if ($paginate) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(100, max(1, (int) $request->query('per_page', 10)));

            $total = $query->count();
            $query->limit($perPage)->offset(($page - 1) * $perPage);

            $items = [];
            foreach ($query->get() as $model) {
                /** @var FeeHead $model */
                $items[] = $model->toResponseArray();
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
            /** @var FeeHead $model */
            $items[] = $model->toResponseArray();
        }

        return Response::success($items);
    }

    public function show(Request $request, array $context, array $params): Response
    {
        $model = FeeHead::find((int) $params['id']);
        if ($model === null || !empty($model->deleted_at)) {
            return Response::error('Fee head not found.', 404);
        }
        return Response::success($model->toResponseArray());
    }

    public function store(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'name'    => 'required|string|max:255',
            'firm_id' => 'nullable|integer|exists:firms,id',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $payload = $this->preparePayload($data);

        $model = new FeeHead();
        $model->fill($payload);

        if (!$model->save()) {
            return Response::error('Could not save fee head.', 500);
        }

        return Response::success($model->toResponseArray(), 'Fee head created successfully.', 201);
    }

    public function update(Request $request, array $context, array $params): Response
    {
        $model = FeeHead::find((int) $params['id']);
        if ($model === null || !empty($model->deleted_at)) {
            return Response::error('Fee head not found.', 404);
        }

        $data = $request->all();

        $validator = Validator::make($data, [
            'name'    => 'required|string|max:255',
            'firm_id' => 'nullable|integer|exists:firms,id',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $payload = $this->preparePayload($data);
        $model->fill($payload);

        if (!$model->save()) {
            return Response::error('Could not update fee head.', 500);
        }

        return Response::success($model->toResponseArray(), 'Fee head updated successfully.');
    }

    public function destroy(Request $request, array $context, array $params): Response
    {
        $model = FeeHead::find((int) $params['id']);
        if ($model === null || !empty($model->deleted_at)) {
            return Response::error('Fee head not found.', 404);
        }

        // Soft delete
        Database::pdo()->prepare(
            'UPDATE fee_heads SET deleted_at = NOW() WHERE id = :id'
        )->execute(['id' => $model->id()]);

        return Response::success(null, 'Fee head deleted successfully.');
    }

    private function preparePayload(array $data): array
    {
        $payload = [
            'name'              => trim((string) ($data['name'] ?? '')),
            'firm_id'           => !empty($data['firm_id']) ? (int) $data['firm_id'] : null,
            'is_admission_fee'  => !empty($data['is_admission_fee']) ? 1 : 0,
            'is_refundable_fee' => !empty($data['is_refundable_fee']) ? 1 : 0,
            'is_once_a_year'    => !empty($data['is_once_a_year']) ? 1 : 0,
            'is_once_a_career'  => !empty($data['is_once_a_career']) ? 1 : 0,
            'description'       => isset($data['description']) ? trim((string) $data['description']) : null,
        ];

        // Format student_category_ids as JSON string
        if (isset($data['student_category_ids'])) {
            $catIds = $data['student_category_ids'];
            if (is_string($catIds)) {
                $decoded = json_decode($catIds, true);
                $catIds = is_array($decoded) ? $decoded : array_filter(array_map('intval', explode(',', $catIds)));
            }
            if (is_array($catIds)) {
                $cleanIds = array_values(array_unique(array_filter(array_map('intval', $catIds))));
                $payload['student_category_ids'] = json_encode($cleanIds);
            } else {
                $payload['student_category_ids'] = null;
            }
        }

        return $payload;
    }
}
