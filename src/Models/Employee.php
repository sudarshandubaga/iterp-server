<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Database;

/**
 * Employee model.
 *
 * Like students, employees live in the `users` table and have specific
 * attributes (employee_type, attendance_code, photo) stored in the `employees`
 * table, along with multiple roles in `user_roles`.
 */
class Employee extends User
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'username',
        'password',
        'title_id',
        'gender',
        'role_id',
        'dob',
        'doj',
        'email',
        'mobile_no',
        'city_id',
        'academic_year_id',
        'firm_id',
        'custom_field_category_id',
        'document_id',
        'is_active',
    ];

    /**
     * Get the employee profile record from the `employees` table.
     */
    public function profile(): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM employees WHERE user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['uid' => $this->id()]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
