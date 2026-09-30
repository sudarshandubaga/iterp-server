<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\FeeConcession;
use Iterp\Models\FeeConcessionItem;
use Throwable;

/**
 * Controller for Concession (manages 2 tables).
 *
 * 1. Parent: fee_concessions
 *    - session_id
 *    - firm_id
 *    - name
 * 2. Child: fee_concession_items (separate table)
 *    - concession_id
 *    - fee_head_id
 *    - amount_type (Percentage / Value)
 *    - amount_value
 */
class FeeConcessionController
{
    public function index(Request $request): Response
    {
        $query = FeeConcession::query();
        $query->whereNull('deleted_at');

        // Scoping by firm_id & session_id
        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $query->where('firm_id', (int) $firmId);
        }

        $sessionId = $request->query('session_id') ?? $request->query('academic_year_id');
        if ($sessionId !== null && $sessionId !== '') {
            $query->where('session_id', (int) $sessionId);
        }

        // Search by name
        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '') {
            $query->search(['name'], trim((string) $search));
        }

        $query->orderBy('id', 'DESC');

        // Pagination if requested
        $paginate = $request->query('page') !== null || $request->query('per_page') !== null;
        if ($paginate) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(100, max(1, (int) $request->query('per_page', 10)));

            $total = $query->count();
            $query->limit($perPage)->offset(($page - 1) * $perPage);

            $items = [];
            foreach ($query->get() as $model) {
                /** @var FeeConcession $model */
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
            /** @var FeeConcession $model */
            $items[] = $model->toResponseArray();
        }

        return Response::success($items);
    }

    public function show(Request $request, array $context, array $params): Response
    {
        $model = FeeConcession::find((int) $params['id']);
        if ($model === null || !empty($model->deleted_at)) {
            return Response::error('Concession rule not found.', 404);
        }
        return Response::success($model->toResponseArray());
    }

    public function store(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'name'       => 'required|string|max:255',
            'session_id' => 'nullable|integer|exists:academic_years,id',
            'firm_id'    => 'nullable|integer|exists:firms,id',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $concession = new FeeConcession();
            $concession->fill([
                'name'        => trim((string) $data['name']),
                'session_id'  => !empty($data['session_id']) ? (int) $data['session_id'] : null,
                'firm_id'     => !empty($data['firm_id']) ? (int) $data['firm_id'] : null,
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            ]);

            if (!$concession->save()) {
                throw new \RuntimeException('Failed to save concession header.');
            }

            $concessionId = $concession->id();

            // Insert child items if provided
            $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
            $this->saveItems($concessionId, $items);

            $pdo->commit();

            return Response::success($concession->toResponseArray(), 'Concession created successfully.', 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to create concession: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, array $context, array $params): Response
    {
        $concession = FeeConcession::find((int) $params['id']);
        if ($concession === null || !empty($concession->deleted_at)) {
            return Response::error('Concession rule not found.', 404);
        }

        $data = $request->all();

        $validator = Validator::make($data, [
            'name'       => 'required|string|max:255',
            'session_id' => 'nullable|integer|exists:academic_years,id',
            'firm_id'    => 'nullable|integer|exists:firms,id',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $concession->fill([
                'name'        => trim((string) $data['name']),
                'session_id'  => !empty($data['session_id']) ? (int) $data['session_id'] : null,
                'firm_id'     => !empty($data['firm_id']) ? (int) $data['firm_id'] : null,
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            ]);

            if (!$concession->save()) {
                throw new \RuntimeException('Failed to update concession header.');
            }

            $concessionId = $concession->id();

            // Replace items if passed
            if (isset($data['items']) && is_array($data['items'])) {
                $delStmt = $pdo->prepare('DELETE FROM fee_concession_items WHERE concession_id = :cid');
                $delStmt->execute(['cid' => $concessionId]);

                $this->saveItems($concessionId, $data['items']);
            }

            $pdo->commit();

            return Response::success($concession->toResponseArray(), 'Concession updated successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to update concession: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, array $context, array $params): Response
    {
        $concession = FeeConcession::find((int) $params['id']);
        if ($concession === null || !empty($concession->deleted_at)) {
            return Response::error('Concession rule not found.', 404);
        }

        // Soft delete parent
        Database::pdo()->prepare(
            'UPDATE fee_concessions SET deleted_at = NOW() WHERE id = :id'
        )->execute(['id' => $concession->id()]);

        return Response::success(null, 'Concession deleted successfully.');
    }

    /**
     * Save child concession items.
     */
    private function saveItems(int $concessionId, array $items): void
    {
        if ($items === []) {
            return;
        }

        $sql = 'INSERT INTO fee_concession_items 
                (concession_id, fee_head_id, amount_type, amount_value, created_at, updated_at)
                VALUES (:concession_id, :fee_head_id, :amount_type, :amount_value, NOW(), NOW())';
        $stmt = Database::pdo()->prepare($sql);

        foreach ($items as $item) {
            $feeHeadId = !empty($item['fee_head_id']) ? (int) $item['fee_head_id'] : 0;
            if ($feeHeadId <= 0) {
                continue;
            }

            $type = strtolower((string) ($item['amount_type'] ?? 'percentage')) === 'value' ? 'Value' : 'Percentage';
            $val = isset($item['amount_value']) ? (float) $item['amount_value'] : 0.00;

            $stmt->execute([
                'concession_id' => $concessionId,
                'fee_head_id'   => $feeHeadId,
                'amount_type'   => $type,
                'amount_value'  => $val,
            ]);
        }
    }
}
