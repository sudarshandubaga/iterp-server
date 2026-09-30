<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\FeeBillScheme;
use Iterp\Models\FeeBillSchemeAmount;
use Throwable;

/**
 * Controller for Fee Bill Scheme (manages 2 tables).
 *
 * 1. Parent: fee_bill_schemes
 *    - name
 *    - slab
 *    - session_id
 *    - firm_id
 * 2. Child: fee_bill_scheme_amounts
 *    - fee_bill_scheme_id
 *    - slab_no
 *    - slab_name
 *    - fee_head_id
 *    - amount
 *    - due_date
 */
class FeeBillSchemeController
{
    public function index(Request $request): Response
    {
        $query = FeeBillScheme::query();
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

        // Search by name or slab
        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '') {
            $query->search(['name', 'slab'], trim((string) $search));
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
                /** @var FeeBillScheme $model */
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
            /** @var FeeBillScheme $model */
            $items[] = $model->toResponseArray();
        }

        return Response::success($items);
    }

    public function show(Request $request, array $context, array $params): Response
    {
        $model = FeeBillScheme::find((int) $params['id']);
        if ($model === null || !empty($model->deleted_at)) {
            return Response::error('Fee bill scheme not found.', 404);
        }
        return Response::success($model->toResponseArray());
    }

    public function store(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'name'       => 'required|string|max:255',
            'slab'       => 'required|string|max:100',
            'session_id' => 'nullable|integer|exists:academic_years,id',
            'firm_id'    => 'nullable|integer|exists:firms,id',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $scheme = new FeeBillScheme();
            $scheme->fill([
                'name'        => trim((string) $data['name']),
                'slab'        => trim((string) $data['slab']),
                'session_id'  => !empty($data['session_id']) ? (int) $data['session_id'] : null,
                'firm_id'     => !empty($data['firm_id']) ? (int) $data['firm_id'] : null,
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            ]);

            if (!$scheme->save()) {
                throw new \RuntimeException('Failed to save fee bill scheme header.');
            }

            $schemeId = $scheme->id();

            // Insert slab wise amounts if provided
            $amounts = isset($data['amounts']) && is_array($data['amounts']) ? $data['amounts'] : [];
            $this->saveAmounts($schemeId, $amounts);

            $pdo->commit();

            return Response::success($scheme->toResponseArray(), 'Fee bill scheme created successfully.', 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to create fee bill scheme: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, array $context, array $params): Response
    {
        $scheme = FeeBillScheme::find((int) $params['id']);
        if ($scheme === null || !empty($scheme->deleted_at)) {
            return Response::error('Fee bill scheme not found.', 404);
        }

        $data = $request->all();

        $validator = Validator::make($data, [
            'name'       => 'required|string|max:255',
            'slab'       => 'required|string|max:100',
            'session_id' => 'nullable|integer|exists:academic_years,id',
            'firm_id'    => 'nullable|integer|exists:firms,id',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $scheme->fill([
                'name'        => trim((string) $data['name']),
                'slab'        => trim((string) $data['slab']),
                'session_id'  => !empty($data['session_id']) ? (int) $data['session_id'] : null,
                'firm_id'     => !empty($data['firm_id']) ? (int) $data['firm_id'] : null,
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            ]);

            if (!$scheme->save()) {
                throw new \RuntimeException('Failed to update fee bill scheme header.');
            }

            $schemeId = $scheme->id();

            // If amounts array is passed, replace old records with updated ones
            if (isset($data['amounts']) && is_array($data['amounts'])) {
                $delStmt = $pdo->prepare('DELETE FROM fee_bill_scheme_amounts WHERE fee_bill_scheme_id = :scheme_id');
                $delStmt->execute(['scheme_id' => $schemeId]);

                $this->saveAmounts($schemeId, $data['amounts']);
            }

            $pdo->commit();

            return Response::success($scheme->toResponseArray(), 'Fee bill scheme updated successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to update fee bill scheme: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, array $context, array $params): Response
    {
        $scheme = FeeBillScheme::find((int) $params['id']);
        if ($scheme === null || !empty($scheme->deleted_at)) {
            return Response::error('Fee bill scheme not found.', 404);
        }

        // Soft delete parent
        Database::pdo()->prepare(
            'UPDATE fee_bill_schemes SET deleted_at = NOW() WHERE id = :id'
        )->execute(['id' => $scheme->id()]);

        return Response::success(null, 'Fee bill scheme deleted successfully.');
    }

    /**
     * Save child slab wise amounts.
     */
    private function saveAmounts(int $schemeId, array $amounts): void
    {
        if ($amounts === []) {
            return;
        }

        $sql = 'INSERT INTO fee_bill_scheme_amounts 
                (fee_bill_scheme_id, slab_no, slab_name, fee_head_id, amount, due_date, created_at, updated_at)
                VALUES (:scheme_id, :slab_no, :slab_name, :fee_head_id, :amount, :due_date, NOW(), NOW())';
        $stmt = Database::pdo()->prepare($sql);

        $seq = 1;
        foreach ($amounts as $item) {
            $slabNo = !empty($item['slab_no']) ? (int) $item['slab_no'] : $seq;
            $slabName = !empty($item['slab_name']) ? trim((string) $item['slab_name']) : "Slab {$slabNo}";
            $feeHeadId = !empty($item['fee_head_id']) ? (int) $item['fee_head_id'] : null;
            $amount = isset($item['amount']) ? (float) $item['amount'] : 0.00;
            $dueDate = !empty($item['due_date']) ? (string) $item['due_date'] : null;

            $stmt->execute([
                'scheme_id'   => $schemeId,
                'slab_no'     => $slabNo,
                'slab_name'   => $slabName,
                'fee_head_id' => $feeHeadId,
                'amount'      => $amount,
                'due_date'    => $dueDate,
            ]);

            $seq++;
        }
    }
}
