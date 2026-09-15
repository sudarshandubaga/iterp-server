<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * Student model. There is no dedicated `students` migration; students live in
 * the `users` table and are identified by a role whose name is "Student".
 *
 * Password hashing is handled by the parent User model's `save()` behaviour;
 * the controller deals with attaching the Student role.
 */
class Student extends User
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
}