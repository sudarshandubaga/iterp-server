<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Database;
use Iterp\Core\Model;

/**
 * Registration model (backed by the registrations table).
 */
class Registration extends Model
{
    protected string $table = 'registrations';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'registration_no',
        'prospectus_id',
        'academic_year_id',
        'firm_id',
        'class_id',
        'section_id',
        'student_category_id',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'dob',
        'email',
        'mobile_no',
        'photo',
        'blood_group',
        'religion',
        'nationality',
        'aadhaar_no',
        'address',
        'city_id',
        'state_id',
        'pincode',
        'father_name',
        'father_occupation',
        'father_mobile_no',
        'father_email',
        'mother_name',
        'mother_occupation',
        'mother_mobile_no',
        'guardian_name',
        'guardian_relation',
        'guardian_mobile_no',
        'previous_school',
        'previous_class',
        'previous_marks_percentage',
        'transfer_certificate_no',
        'registration_fee',
        'payment_mode',
        'payment_status',
        'transaction_no',
        'registration_date',
        'status',
        'admission_date',
        'student_id',
        'admission_no',
        'remarks',
        'created_by',
    ];

    /**
     * Generate next sequential registration number (e.g. REG-2026-0001).
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

        $prefix = "REG-{$yearPrefix}-";
        $sql = "SELECT registration_no FROM registrations 
                WHERE registration_no LIKE :prefix 
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
