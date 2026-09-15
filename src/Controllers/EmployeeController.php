<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\Employee;

/**
 * Complete CRUD API for Employees (User Details + Employee Details + Roles + Custom Fields + Documents + AVIF Photo).
 */
class EmployeeController
{
    private const DEFAULT_ROLE = 'Staff';

    protected function rules(): array
    {
        return [
            'academic_year_id'         => 'nullable|integer|exists:academic_years,id',
            'firm_id'                  => 'nullable|integer|exists:firms,id',
            'title_id'                 => 'nullable|integer|exists:titles,id',
            'city_id'                  => 'nullable|integer|exists:cities,id',
            'first_name'               => 'required|string|max:255',
            'middle_name'              => 'nullable|string|max:255',
            'last_name'                => 'nullable|string|max:255',
            'username'                 => 'required|string|max:150',
            'password'                 => 'nullable|string|min:8|max:72',
            'email'                    => 'nullable|email|max:255',
            'mobile_no'                => 'nullable|string|max:50',
            'gender'                   => 'nullable|in:m,f',
            'dob'                      => 'nullable|date',
            'doj'                      => 'nullable|date',
            'is_active'                => 'nullable|in:y,n',
            'employee_type'            => 'required|in:Teaching,Non-Teaching,Management',
            'attendance_code'          => 'nullable|string|max:100',
            'photo'                    => 'nullable|string',
            'role_ids'                 => 'nullable|array',
            'custom_field_category_id' => 'nullable|integer|exists:custom_field_categories,id',
            'document_id'              => 'nullable|integer|exists:documents,id',
        ];
    }

    public function index(Request $request): Response
    {
        $bindings = [];
        $where = 'WHERE u.deleted_at IS NULL AND (e.deleted_at IS NULL OR e.id IS NOT NULL)';

        // Require that user has an employee record or is not a student
        $where .= ' AND e.id IS NOT NULL';

        $academicYearId = $request->query('academic_year_id');
        if ($academicYearId !== null && $academicYearId !== '') {
            $bindings['academic_year_id'] = (int) $academicYearId;
            $where .= ' AND u.academic_year_id = :academic_year_id';
        }

        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $bindings['firm_id'] = (int) $firmId;
            $where .= ' AND u.firm_id = :firm_id';
        }

        $employeeType = $request->query('employee_type');
        if ($employeeType !== null && $employeeType !== '') {
            $bindings['employee_type'] = (string) $employeeType;
            $where .= ' AND e.employee_type = :employee_type';
        }

        $isActive = $request->query('is_active');
        if ($isActive !== null && $isActive !== '') {
            $bindings['is_active'] = (string) $isActive;
            $where .= ' AND u.is_active = :is_active';
        }

        $roleId = $request->query('role_id');
        if ($roleId !== null && $roleId !== '') {
            $bindings['role_id'] = (int) $roleId;
            $where .= ' AND EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id = u.id AND ur2.role_id = :role_id)';
        }

        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '') {
            $term = '%' . trim((string) $search) . '%';
            $bindings['s1'] = $term;
            $bindings['s2'] = $term;
            $bindings['s3'] = $term;
            $bindings['s4'] = $term;
            $bindings['s5'] = $term;
            $bindings['s6'] = $term;
            $bindings['s7'] = $term;
            $where .= ' AND (u.first_name LIKE :s1 OR u.middle_name LIKE :s2
                       OR u.last_name LIKE :s3 OR u.username LIKE :s4
                       OR u.email LIKE :s5 OR u.mobile_no LIKE :s6
                       OR e.attendance_code LIKE :s7)';
        }

        $paginate = $request->query('page') !== null || $request->query('per_page') !== null;
        if ($paginate) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = (int) $request->query('per_page', 10);
            if ($perPage < 1) $perPage = 10;
            if ($perPage > 100) $perPage = 100;

            $countSql = "SELECT COUNT(DISTINCT u.id) FROM users u
                         LEFT JOIN employees e ON e.user_id = u.id
                         {$where}";
            $countStmt = Database::pdo()->prepare($countSql);
            $countStmt->execute($bindings);
            $total = (int) $countStmt->fetchColumn();

            $sql = "SELECT u.id, u.first_name, u.middle_name, u.last_name, u.username,
                           u.title_id, u.gender, u.dob, u.doj, u.email, u.mobile_no,
                           u.city_id, u.academic_year_id, u.firm_id, u.custom_field_category_id,
                           u.document_id, u.is_active,
                           e.employee_type, e.attendance_code, e.photo,
                           GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names,
                           GROUP_CONCAT(DISTINCT r.id ORDER BY r.name SEPARATOR ',') AS role_ids_str
                    FROM users u
                    INNER JOIN employees e ON e.user_id = u.id
                    LEFT JOIN user_roles ur ON ur.user_id = u.id
                    LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
                    {$where}
                    GROUP BY u.id, e.id
                    ORDER BY u.first_name ASC
                    LIMIT :limit OFFSET :offset";

            $stmt = Database::pdo()->prepare($sql);
            $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
            foreach ($bindings as $key => $val) {
                $stmt->bindValue(':' . $key, $val);
            }
            $stmt->execute();

            $items = array_map(function ($row) {
                $row['role_ids'] = !empty($row['role_ids_str'])
                    ? array_map('intval', explode(',', $row['role_ids_str']))
                    : [];
                unset($row['role_ids_str']);
                return $row;
            }, $stmt->fetchAll(\PDO::FETCH_ASSOC));

            return Response::success([
                'items'       => $items,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ]);
        }

        $sql = "SELECT u.id, u.first_name, u.middle_name, u.last_name, u.username,
                       u.title_id, u.gender, u.dob, u.doj, u.email, u.mobile_no,
                       u.city_id, u.academic_year_id, u.firm_id, u.custom_field_category_id,
                       u.document_id, u.is_active,
                       e.employee_type, e.attendance_code, e.photo,
                       GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names,
                       GROUP_CONCAT(DISTINCT r.id ORDER BY r.name SEPARATOR ',') AS role_ids_str
                FROM users u
                INNER JOIN employees e ON e.user_id = u.id
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
                {$where}
                GROUP BY u.id, e.id
                ORDER BY u.first_name ASC
                LIMIT 1000";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($bindings);

        $items = array_map(function ($row) {
            $row['role_ids'] = !empty($row['role_ids_str'])
                ? array_map('intval', explode(',', $row['role_ids_str']))
                : [];
            unset($row['role_ids_str']);
            return $row;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));

        return Response::success($items);
    }

    public function show(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $payload = $this->employeePayload($id);

        return !empty($payload)
            ? Response::success($payload)
            : Response::error('Employee not found.', 404);
    }

    public function store(Request $request): Response
    {
        $data = $request->all();
        $validator = Validator::make($data, $this->rules());
        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        // Brand new employee must have a password
        if (empty($data['password']) || strlen((string) $data['password']) < 8) {
            return Response::error('Password is required (min 8 characters).', 422, [
                'password' => ['Password is required (min 8 characters).'],
            ]);
        }

        // Attendance code uniqueness check if provided
        if (!empty($data['attendance_code'])) {
            $stmt = Database::pdo()->prepare(
                'SELECT id FROM employees WHERE attendance_code = :code AND deleted_at IS NULL LIMIT 1'
            );
            $stmt->execute(['code' => trim((string) $data['attendance_code'])]);
            if ($stmt->fetchColumn()) {
                return Response::error('Attendance Code already in use.', 422, [
                    'attendance_code' => ['Attendance Code already in use.'],
                ]);
            }
        }

        // Determine primary role
        $roleIds = !empty($data['role_ids']) && is_array($data['role_ids']) ? $data['role_ids'] : [];
        $primaryRole = !empty($roleIds) ? (int) $roleIds[0] : null;

        $employee = new Employee();
        $employee->fill([
            'first_name'               => $data['first_name'],
            'middle_name'              => $data['middle_name'] ?? null,
            'last_name'                => $data['last_name'] ?? null,
            'username'                 => $data['username'],
            'password'                 => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'title_id'                 => $data['title_id'] ?? null,
            'gender'                   => $data['gender'] ?? null,
            'dob'                      => $data['dob'] ?? null,
            'doj'                      => $data['doj'] ?? null,
            'email'                    => $data['email'] ?? null,
            'mobile_no'                => $data['mobile_no'] ?? null,
            'city_id'                  => $data['city_id'] ?? null,
            'academic_year_id'         => $data['academic_year_id'] ?? null,
            'firm_id'                  => $data['firm_id'] ?? null,
            'custom_field_category_id' => $data['custom_field_category_id'] ?? null,
            'document_id'              => $data['document_id'] ?? null,
            'role_id'                  => $primaryRole,
            'is_active'                => $data['is_active'] ?? 'y',
        ]);

        if (!$employee->save()) {
            return Response::error('Could not create employee account.', 500);
        }
        $userId = (int) $employee->attributes['id'];

        // Assign multiple roles to user_roles
        if (!empty($roleIds)) {
            $employee->syncRoles($roleIds);
        }

        // Upsert employee profile in `employees` table
        $this->upsertEmployeeProfile($userId, $data);

        return Response::success($this->employeePayload($userId), 'Employee created successfully.', 201);
    }

    public function update(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM users WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() === 0) {
            return Response::error('Employee not found.', 404);
        }

        $data = $request->all();
        $validator = Validator::make($data, $this->rules());
        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        // Attendance code uniqueness check excluding this employee
        if (!empty($data['attendance_code'])) {
            $stmt = Database::pdo()->prepare(
                'SELECT id FROM employees WHERE attendance_code = :code AND user_id != :id AND deleted_at IS NULL LIMIT 1'
            );
            $stmt->execute(['code' => trim((string) $data['attendance_code']), 'id' => $id]);
            if ($stmt->fetchColumn()) {
                return Response::error('Attendance Code already in use.', 422, [
                    'attendance_code' => ['Attendance Code already in use.'],
                ]);
            }
        }

        $employee = Employee::find($id);

        $payload = [
            'first_name'               => $data['first_name'],
            'middle_name'              => $data['middle_name'] ?? null,
            'last_name'                => $data['last_name'] ?? null,
            'username'                 => $data['username'],
            'title_id'                 => array_key_exists('title_id', $data) ? $data['title_id'] : $employee->title_id ?? null,
            'gender'                   => $data['gender'] ?? $employee->gender ?? null,
            'dob'                      => array_key_exists('dob', $data) ? $data['dob'] : $employee->dob ?? null,
            'doj'                      => array_key_exists('doj', $data) ? $data['doj'] : $employee->doj ?? null,
            'email'                    => $data['email'] ?? null,
            'mobile_no'                => $data['mobile_no'] ?? null,
            'city_id'                  => array_key_exists('city_id', $data) ? $data['city_id'] : $employee->city_id ?? null,
            'academic_year_id'         => $data['academic_year_id'] ?? $employee->academic_year_id ?? null,
            'firm_id'                  => array_key_exists('firm_id', $data) ? $data['firm_id'] : $employee->firm_id ?? null,
            'custom_field_category_id' => $data['custom_field_category_id'] ?? $employee->custom_field_category_id ?? null,
            'document_id'              => $data['document_id'] ?? $employee->document_id ?? null,
            'is_active'                => $data['is_active'] ?? $employee->is_active ?? 'y',
        ];

        if (!empty($data['password']) && strlen((string) $data['password']) >= 8) {
            $payload['password'] = (string) $data['password'];
        }

        $employee->fill($payload);
        $employee->save();

        // Update multi-roles if provided
        if (isset($data['role_ids']) && is_array($data['role_ids'])) {
            $employee->syncRoles($data['role_ids']);
        }

        // Upsert employee profile
        $this->upsertEmployeeProfile($id, $data);

        return Response::success($this->employeePayload($id), 'Employee updated successfully.');
    }

    public function destroy(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);

        Database::pdo()->prepare(
            'UPDATE employees SET deleted_at = NOW() WHERE user_id = :id AND deleted_at IS NULL'
        )->execute(['id' => $id]);

        return $stmt->rowCount() > 0
            ? Response::success(null, 'Employee deleted.')
            : Response::error('Employee not found.', 404);
    }

    private function employeePayload(int $id): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT u.id, u.first_name, u.middle_name, u.last_name, u.username,
                    u.title_id, u.gender, u.dob, u.doj, u.email, u.mobile_no, u.city_id,
                    u.academic_year_id, u.custom_field_category_id, u.document_id,
                    u.firm_id, u.is_active,
                    e.employee_type, e.attendance_code, e.photo,
                    c.name AS city_name, t.name AS title_name
             FROM users u
             INNER JOIN employees e ON e.user_id = u.id
             LEFT JOIN cities c ON c.id = u.city_id
             LEFT JOIN titles t ON t.id = u.title_id
             WHERE u.id = :id AND u.deleted_at IS NULL AND e.deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        $employee = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$employee) {
            return [];
        }

        // Fetch assigned roles
        $rolesStmt = Database::pdo()->prepare(
            'SELECT r.id, r.name FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :id AND r.deleted_at IS NULL
             ORDER BY r.name ASC'
        );
        $rolesStmt->execute(['id' => $id]);
        $roles = $rolesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $employee['roles'] = $roles;
        $employee['role_ids'] = array_map(fn($r) => (int) $r['id'], $roles);
        $employee['role_names'] = implode(', ', array_column($roles, 'name'));

        return $employee;
    }

    private function upsertEmployeeProfile(int $userId, array $data): void
    {
        $employeeType = $data['employee_type'] ?? 'Teaching';
        $attendanceCode = !empty($data['attendance_code']) ? trim((string) $data['attendance_code']) : null;
        if (empty($attendanceCode)) {
            $attendanceCode = 'EMP-' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT);
        }

        $photoPath = $this->processEmployeePhoto($userId, $data['photo'] ?? null);

        $photoUpdateClause = 'photo = photo';
        if (array_key_exists('photo', $data)) {
            if ($data['photo'] === null || $data['photo'] === '' || $data['photo'] === 'remove') {
                $photoUpdateClause = 'photo = NULL';
            } else {
                $photoUpdateClause = 'photo = VALUES(photo)';
            }
        }

        $stmt = Database::pdo()->prepare(
            "INSERT INTO employees
                (user_id, employee_type, attendance_code, photo, created_at, updated_at)
             VALUES
                (:user_id, :type, :code, :photo, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                employee_type   = VALUES(employee_type),
                attendance_code = VALUES(attendance_code),
                {$photoUpdateClause},
                updated_at      = NOW()"
        );
        $stmt->execute([
            'user_id' => $userId,
            'type'    => $employeeType,
            'code'    => $attendanceCode,
            'photo'   => $photoPath,
        ]);
    }

    private function processEmployeePhoto(int $userId, ?string $photo): ?string
    {
        if (empty($photo) || $photo === 'remove') {
            return null;
        }

        if (str_starts_with($photo, '/uploads/') || str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return $photo;
        }

        if (preg_match('/^data:image\/([a-zA-Z0-9\+\-]+);base64,(.+)$/', $photo, $matches)) {
            $format = strtolower($matches[1]);
            $ext = ($format === 'avif' || str_contains($format, 'avif')) ? 'avif' : ($format === 'webp' ? 'webp' : 'avif');
            $data = base64_decode($matches[2]);
            if ($data !== false) {
                $dir = dirname(__DIR__, 2) . '/public/uploads/employees';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $filename = sprintf('employee_%d_%s.%s', $userId, bin2hex(random_bytes(6)), $ext);
                $fullPath = $dir . '/' . $filename;
                file_put_contents($fullPath, $data);
                return '/uploads/employees/' . $filename;
            }
        }

        return $photo;
    }
}
