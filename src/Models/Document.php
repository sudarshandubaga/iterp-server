<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * Document model (backed by the documents table).
 */
class Document extends Model
{
    protected string $table = 'documents';
    protected string $primaryKey = 'id';

    public const FOR_STUDENT  = 'student';
    public const FOR_EMPLOYEE = 'employee';
    public const FOR_BOTH     = 'both';

    protected array $fillable = [
        'name',
        'short_name',
        'document_for',
    ];
}