<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Request;
use Iterp\Models\CustomField;

/**
 * CRUD API for custom fields (Annual Settings > Custom Fields).
 * Optionally filter by `?custom_field_category_id=`.
 */
class CustomFieldController extends CrudController
{
    protected string $model = CustomField::class;

    protected array $rules = [
        'custom_field_category_id'  => 'required|integer|exists:custom_field_categories,id',
        'name'                      => 'required|string|max:255',
        'field_type'                => 'required|in:textbox,radio,checkbox,pulldown,textarea,date',
        'data_type'                 => 'required|in:numeric,alpha_numeric,alphabatic,alphabatic_special,alpha_numeric_special,numeric_special',
        'options'                   => 'nullable|string',
        'mandatory'                 => 'nullable|in:yes,no',
        'show'                      => 'nullable|in:yes,no',
        'default_value'             => 'nullable|string|max:255',
        'validation_message'        => 'nullable|string|max:255',
        'max_length'                => 'nullable|integer',
        'sort_order'                => 'nullable|integer',
    ];

    protected string $orderBy = 'sort_order';

    protected array $searchable = ['name', 'field_type', 'data_type'];

    protected function applyFilters(Request $request, \Iterp\Core\QueryBuilder $query): void
    {
        if ($request->query('custom_field_category_id') !== null) {
            $query->where('custom_field_category_id', (int) $request->query('custom_field_category_id'));
        }
    }
}