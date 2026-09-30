<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\FeeBillSchemeAmount;

/**
 * Controller for Fee Bill Scheme Amounts.
 *
 * fee_bill_scheme_amounts:
 * - fee_bill_scheme_slab_id
 * - fee_head_id
 * - amount
 */
class FeeBillSchemeAmountController
{
    public function index(Request $request): Response
    {
        $query = FeeBillSchemeAmount::query();

        $slabId = $request->query('fee_bill_scheme_slab_id');
        if ($slabId !== null && $slabId !== '') {
            $query->where('fee_bill_scheme_slab_id', (int) $slabId);
        }

        $items = $query->get();
        return Response::success($items);
    }

    public function show(Request $request, array $context, array $params): Response
    {
        $model = FeeBillSchemeAmount::find((int) $params['id']);
        if ($model === null) {
            return Response::error('Fee bill scheme amount not found.', 404);
        }
        return Response::success($model->toArray());
    }

    public function store(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'fee_bill_scheme_slab_id' => 'required|integer|exists:fee_bill_scheme_slabs,id',
            'fee_head_id'             => 'nullable|integer|exists:fee_heads,id',
            'amount'                  => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $amount = new FeeBillSchemeAmount();
        $amount->fill([
            'fee_bill_scheme_slab_id' => (int) $data['fee_bill_scheme_slab_id'],
            'fee_head_id'             => !empty($data['fee_head_id']) ? (int) $data['fee_head_id'] : null,
            'amount'                  => (float) $data['amount'],
        ]);

        if (!$amount->save()) {
            return Response::error('Failed to create amount record.', 500);
        }

        return Response::success($amount->toArray(), 'Amount created successfully.', 201);
    }

    public function update(Request $request, array $context, array $params): Response
    {
        $amount = FeeBillSchemeAmount::find((int) $params['id']);
        if ($amount === null) {
            return Response::error('Fee bill scheme amount not found.', 404);
        }

        $data = $request->all();

        $validator = Validator::make($data, [
            'fee_head_id' => 'nullable|integer|exists:fee_heads,id',
            'amount'      => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        if (array_key_exists('fee_head_id', $data)) {
            $amount->fee_head_id = !empty($data['fee_head_id']) ? (int) $data['fee_head_id'] : null;
        }
        if (isset($data['amount'])) {
            $amount->amount = (float) $data['amount'];
        }

        if (!$amount->save()) {
            return Response::error('Failed to update amount record.', 500);
        }

        return Response::success($amount->toArray(), 'Amount updated successfully.');
    }

    public function destroy(Request $request, array $context, array $params): Response
    {
        $amount = FeeBillSchemeAmount::find((int) $params['id']);
        if ($amount === null) {
            return Response::error('Fee bill scheme amount not found.', 404);
        }

        Database::pdo()->prepare('DELETE FROM fee_bill_scheme_amounts WHERE id = :id')->execute(['id' => $amount->id()]);

        return Response::success(null, 'Amount deleted successfully.');
    }
}
