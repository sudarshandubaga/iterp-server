<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\FeeBillSchemeSlab;
use Iterp\Models\FeeBillSchemeAmount;

/**
 * Controller for Fee Bill Scheme Slabs.
 *
 * fee_bill_scheme_slabs:
 * - fee_bill_scheme_id
 * - slab_no
 * - due_date
 */
class FeeBillSchemeSlabController
{
    public function index(Request $request): Response
    {
        $query = FeeBillSchemeSlab::query();

        $schemeId = $request->query('fee_bill_scheme_id');
        if ($schemeId !== null && $schemeId !== '') {
            $query->where('fee_bill_scheme_id', (int) $schemeId);
        }

        $query->orderBy('slab_no', 'ASC');

        $items = [];
        foreach ($query->get() as $model) {
            /** @var FeeBillSchemeSlab $model */
            $items[] = $model->toResponseArray();
        }

        return Response::success($items);
    }

    public function show(Request $request, array $context, array $params): Response
    {
        $model = FeeBillSchemeSlab::find((int) $params['id']);
        if ($model === null) {
            return Response::error('Fee bill scheme slab not found.', 404);
        }
        return Response::success($model->toResponseArray());
    }

    public function store(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'fee_bill_scheme_id' => 'required|integer|exists:fee_bill_schemes,id',
            'slab_no'            => 'required|integer|min:1',
            'due_date'           => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $slab = new FeeBillSchemeSlab();
        $slab->fill([
            'fee_bill_scheme_id' => (int) $data['fee_bill_scheme_id'],
            'slab_no'            => (int) $data['slab_no'],
            'due_date'           => !empty($data['due_date']) ? (string) $data['due_date'] : null,
        ]);

        if (!$slab->save()) {
            return Response::error('Failed to create slab.', 500);
        }

        return Response::success($slab->toResponseArray(), 'Slab created successfully.', 201);
    }

    public function update(Request $request, array $context, array $params): Response
    {
        $slab = FeeBillSchemeSlab::find((int) $params['id']);
        if ($slab === null) {
            return Response::error('Fee bill scheme slab not found.', 404);
        }

        $data = $request->all();

        $validator = Validator::make($data, [
            'slab_no'  => 'nullable|integer|min:1',
            'due_date' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        if (isset($data['slab_no'])) {
            $slab->slab_no = (int) $data['slab_no'];
        }
        if (array_key_exists('due_date', $data)) {
            $slab->due_date = !empty($data['due_date']) ? (string) $data['due_date'] : null;
        }

        if (!$slab->save()) {
            return Response::error('Failed to update slab.', 500);
        }

        return Response::success($slab->toResponseArray(), 'Slab updated successfully.');
    }

    public function destroy(Request $request, array $context, array $params): Response
    {
        $slab = FeeBillSchemeSlab::find((int) $params['id']);
        if ($slab === null) {
            return Response::error('Fee bill scheme slab not found.', 404);
        }

        Database::pdo()->prepare('DELETE FROM fee_bill_scheme_slabs WHERE id = :id')->execute(['id' => $slab->id()]);

        return Response::success(null, 'Slab deleted successfully.');
    }
}
