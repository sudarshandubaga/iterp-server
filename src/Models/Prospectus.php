<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Database;
use Iterp\Core\Model;

/**
 * Prospectus model (backed by the prospectus table).
 */
class Prospectus extends Model
{
    protected string $table = 'prospectus';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'prospectus_no',
        'candidate_name',
        'father_name',
        'mother_name',
        'mobile_no',
        'email',
        'class_id',
        'academic_year_id',
        'firm_id',
        'amount',
        'payment_mode',
        'reference_no',
        'issue_date',
        'status',
        'remarks',
        'created_by',
    ];

    /**
     * Generate next sequential prospectus number (e.g. PR-2026-0001).
     */
    public static function generateNextNumber(?int $academicYearId = null): string
    {
        $yearPrefix = date('Y');
        if ($academicYearId) {
            $yearRecord = AcademicYear::find($academicYearId);
            if ($yearRecord && !empty($yearRecord->name)) {
                $parts = explode('-', (string) $yearRecord->name);
                $yearPrefix = $parts[0] ?: date('Y');
            }
        }

        $prefix = "PR-{$yearPrefix}-";
        $sql = "SELECT prospectus_no FROM prospectus 
                WHERE prospectus_no LIKE :prefix 
                ORDER BY id DESC LIMIT 1";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['prefix' => "{$prefix}%"]);
        $last = $stmt->fetchColumn();

        if ($last) {
            $lastNum = (int) str_replace($prefix, '', (string) $last);
            $nextNum = str_pad((string) ($lastNum + 1), 4, '0', STR_PAD_LEFT);
        } else {
            $nextNum = '0001';
        }

        return $prefix . $nextNum;
    }
}
