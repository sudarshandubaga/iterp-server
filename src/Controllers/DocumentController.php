<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Request;
use Iterp\Models\Document;

/**
 * CRUD API for documents (Annual Settings > Documents).
 */
class DocumentController extends CrudController
{
    protected string $model = Document::class;

    protected array $rules = [
        'name'         => 'required|string|max:255',
        'short_name'   => 'required|string|max:50',
        'document_for' => 'required|in:student,employee,both',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name', 'short_name'];

    protected function applyFilters(Request $request, \Iterp\Core\QueryBuilder $query): void
    {
        $docFor = $request->query('document_for');
        if ($docFor === 'student' || $docFor === 'employee') {
            $query->whereIn('document_for', [(string) $docFor, 'both']);
        }
    }
}