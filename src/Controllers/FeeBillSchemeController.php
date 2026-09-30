<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\FeeBillScheme;
use Iterp\Models\FeeBillSchemeSlab;
use Iterp\Models\FeeBillSchemeAmount;
use Throwable;

/**
 * Controller for Fee Bill Scheme (manages 3 tables in hierarchy).
 *
 * 1. Parent: fee_bill_schemes
 *    - firm_id
 *    - session_id
 *    - name
 *    - slab (numeric: count of slabs)
 *    - description
 * 2. Child: fee_bill_scheme_slabs
 *    - fee_bill_scheme_id
 *    - slab_no
 *    - due_date
 * 3. Grandchild: fee_bill_scheme_amounts
 *    - fee_bill_scheme_slab_id
 *    - fee_head_id
 *    - amount
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

        // Search by name or description
        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '') {
            $term = trim((string) $search);
            if (is_numeric($term)) {
                $query->where('slab', (int) $term);
            } else {
                $query->search(['name', 'description'], $term);
            }
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
            'slab'       => 'required|integer|min:1',
            'session_id' => 'nullable|integer|exists:academic_years,id',
            'firm_id'    => 'nullable|integer|exists:firms,id',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $slabs = $this->normalizeSlabsData($data);
        $slabCount = (int) $data['slab'];
        if ($slabCount <= 0 && $slabs !== []) {
            $slabCount = count($slabs);
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $scheme = new FeeBillScheme();
            $scheme->fill([
                'name'        => trim((string) $data['name']),
                'slab'        => $slabCount,
                'session_id'  => !empty($data['session_id']) ? (int) $data['session_id'] : null,
                'firm_id'     => !empty($data['firm_id']) ? (int) $data['firm_id'] : null,
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            ]);

            if (!$scheme->save()) {
                throw new \RuntimeException('Failed to save fee bill scheme header.');
            }

            $schemeId = $scheme->id();

            // Save slabs and nested amounts
            $this->saveSlabs($schemeId, $slabs, $slabCount);

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
            'slab'       => 'required|integer|min:1',
            'session_id' => 'nullable|integer|exists:academic_years,id',
            'firm_id'    => 'nullable|integer|exists:firms,id',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $slabCount = (int) $data['slab'];
            $slabs = $this->normalizeSlabsData($data);
            if ($slabCount <= 0 && $slabs !== []) {
                $slabCount = count($slabs);
            }

            $scheme->fill([
                'name'        => trim((string) $data['name']),
                'slab'        => $slabCount,
                'session_id'  => !empty($data['session_id']) ? (int) $data['session_id'] : null,
                'firm_id'     => !empty($data['firm_id']) ? (int) $data['firm_id'] : null,
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            ]);

            if (!$scheme->save()) {
                throw new \RuntimeException('Failed to update fee bill scheme header.');
            }

            $schemeId = $scheme->id();

            // If slabs or amounts passed, update child slabs and amounts
            if (isset($data['slabs']) || isset($data['amounts'])) {
                // Deleting slabs will CASCADE delete amounts from fee_bill_scheme_amounts
                $delStmt = $pdo->prepare('DELETE FROM fee_bill_scheme_slabs WHERE fee_bill_scheme_id = :scheme_id');
                $delStmt->execute(['scheme_id' => $schemeId]);

                $this->saveSlabs($schemeId, $slabs, $slabCount);
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
     * Normalize slabs data from either nested slabs structure or flat amounts array.
     */
    private function normalizeSlabsData(array $data): array
    {
        if (isset($data['slabs']) && is_array($data['slabs'])) {
            $slabs = [];
            foreach ($data['slabs'] as $idx => $s) {
                $slabNo = !empty($s['slab_no']) ? (int) $s['slab_no'] : ($idx + 1);
                $dueDate = !empty($s['due_date']) ? (string) $s['due_date'] : null;
                $amounts = [];

                if (isset($s['amounts']) && is_array($s['amounts'])) {
                    foreach ($s['amounts'] as $a) {
                        $amounts[] = [
                            'fee_head_id' => !empty($a['fee_head_id']) ? (int) $a['fee_head_id'] : null,
                            'amount'      => isset($a['amount']) ? (float) $a['amount'] : 0.00,
                        ];
                    }
                }

                $slabs[] = [
                    'slab_no'  => $slabNo,
                    'due_date' => $dueDate,
                    'amounts'  => $amounts,
                ];
            }
            return $slabs;
        }

        // Fallback: Legacy flat amounts array grouped by slab_no
        if (isset($data['amounts']) && is_array($data['amounts'])) {
            $slabsMap = [];
            foreach ($data['amounts'] as $idx => $amt) {
                $sNo = !empty($amt['slab_no']) ? (int) $amt['slab_no'] : ($idx + 1);
                if (!isset($slabsMap[$sNo])) {
                    $slabsMap[$sNo] = [
                        'slab_no'  => $sNo,
                        'due_date' => !empty($amt['due_date']) ? (string) $amt['due_date'] : null,
                        'amounts'  => [],
                    ];
                }
                $slabsMap[$sNo]['amounts'][] = [
                    'fee_head_id' => !empty($amt['fee_head_id']) ? (int) $amt['fee_head_id'] : null,
                    'amount'      => isset($amt['amount']) ? (float) $amt['amount'] : 0.00,
                ];
            }
            return array_values($slabsMap);
        }

        return [];
    }

    /**
     * Save child slabs and their grandchild amounts.
     */
    private function saveSlabs(int $schemeId, array $slabs, int $expectedCount = 0): void
    {
        $pdo = Database::pdo();

        $slabStmt = $pdo->prepare('
            INSERT INTO fee_bill_scheme_slabs (fee_bill_scheme_id, slab_no, due_date, created_at, updated_at)
            VALUES (:scheme_id, :slab_no, :due_date, NOW(), NOW())
        ');

        $amtStmt = $pdo->prepare('
            INSERT INTO fee_bill_scheme_amounts (fee_bill_scheme_slab_id, fee_head_id, amount, created_at, updated_at)
            VALUES (:slab_id, :fee_head_id, :amount, NOW(), NOW())
        ');

        $savedSlabNos = [];
        $seq = 1;

        foreach ($slabs as $slab) {
            $slabNo = !empty($slab['slab_no']) ? (int) $slab['slab_no'] : $seq;
            $dueDate = !empty($slab['due_date']) ? (string) $slab['due_date'] : null;

            $slabStmt->execute([
                'scheme_id' => $schemeId,
                'slab_no'   => $slabNo,
                'due_date'  => $dueDate,
            ]);

            $slabId = (int) $pdo->lastInsertId();
            $savedSlabNos[] = $slabNo;

            $amounts = isset($slab['amounts']) && is_array($slab['amounts']) ? $slab['amounts'] : [];
            foreach ($amounts as $amt) {
                $feeHeadId = !empty($amt['fee_head_id']) ? (int) $amt['fee_head_id'] : null;
                $amountVal = isset($amt['amount']) ? (float) $amt['amount'] : 0.00;

                // Save if head is selected or amount > 0
                if ($feeHeadId !== null || $amountVal > 0) {
                    $amtStmt->execute([
                        'slab_id'     => $slabId,
                        'fee_head_id' => $feeHeadId,
                        'amount'      => $amountVal,
                    ]);
                }
            }

            $seq++;
        }

        // If slab count requested is greater than provided slabs, fill remaining slabs
        if ($expectedCount > count($savedSlabNos)) {
            for ($i = count($savedSlabNos) + 1; $i <= $expectedCount; $i++) {
                if (!in_array($i, $savedSlabNos, true)) {
                    $slabStmt->execute([
                        'scheme_id' => $schemeId,
                        'slab_no'   => $i,
                        'due_date'  => null,
                    ]);
                }
            }
        }
    }
}
