<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\Student;

/**
 * CRUD API for students (SIS > Add Students).
 *
 * Students are stored in the `users` table and tagged with the "Student" role.
 * Because there is no dedicated `students` migration, this controller bridges
 * the users table with the roles table.
 */
class StudentController
{
    private const ROLE_STUDENT = 'Student';

    /** Ensure a "Student" role exists and return its primary key. */
    protected function studentRoleId(): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM roles WHERE name = :name LIMIT 1'
        );
        $stmt->execute(['name' => self::ROLE_STUDENT]);
        $id = (int) $stmt->fetchColumn();

        if ($id > 0) {
            return $id;
        }

        Database::pdo()->prepare(
            'INSERT INTO roles (name) VALUES (:name)'
        )->execute(['name' => self::ROLE_STUDENT]);
        return (int) Database::pdo()->lastInsertId();
    }

    protected function rules(): array
    {
        return [
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'firm_id'          => 'nullable|integer|exists:firms,id',
            'title_id'         => 'nullable|integer|exists:titles,id',
            'section_id'       => 'nullable|integer|exists:sections,id',
            'city_id'          => 'nullable|integer|exists:cities,id',
            'first_name'       => 'required|string|max:255',
            'last_name'        => 'nullable|string|max:255',
            'username'         => 'required|string|max:150',
            // Optional for edits; store() enforces it for brand-new students.
            'password'         => 'nullable|string|min:8|max:72',
            'email'            => 'nullable|email|max:255',
            'father_email'     => 'nullable|email|max:255',
            'mobile_no'        => 'nullable|string|max:50',
            'father_mobile_no' => 'nullable|string|max:50',
            'gender'           => 'nullable|in:m,f',
            'dob'              => 'nullable|date',
            'doj'              => 'nullable|date',
            'is_active'        => 'nullable|in:y,n',
            'enrollment_number' => 'nullable|string|max:100',
            'scholar_number'    => 'nullable|string|max:100',
            'roll_number'       => 'nullable|string|max:50',
            'photo'             => 'nullable|string',
            'student_category_id' => 'nullable|integer|exists:student_categories,id',
            'custom_field_category_id' => 'nullable|integer|exists:custom_field_categories,id',
            'document_id'      => 'nullable|integer|exists:documents,id',
        ];
    }

    /** Columns searched by `?search=` in the student list. */
    protected array $searchable = ['first_name', 'middle_name', 'last_name', 'username', 'email', 'mobile_no'];

    protected function studentCountQuery(array $bindings): string
    {
        $where = 'SELECT COUNT(*) FROM users u
                  LEFT JOIN students s ON s.user_id = u.id
                  INNER JOIN roles r ON r.id = u.role_id
                  WHERE r.name = :role AND u.deleted_at IS NULL AND (s.deleted_at IS NULL OR s.id IS NULL)';
        if (isset($bindings['academic_year_id'])) {
            $where .= ' AND u.academic_year_id = :academic_year_id';
        }
        if (isset($bindings['firm_id'])) {
            $where .= ' AND u.firm_id = :firm_id';
        }
        if (isset($bindings['s1'])) {
            $where .= ' AND (u.first_name LIKE :s1 OR u.middle_name LIKE :s2
                       OR u.last_name LIKE :s3 OR u.username LIKE :s4
                       OR u.email LIKE :s5 OR u.mobile_no LIKE :s6)';
        }
        return $where;
    }

    protected function buildStudentFilters(array $bindings): string
    {
        $sql = '';
        if (isset($bindings['academic_year_id'])) {
            $sql .= ' AND u.academic_year_id = :academic_year_id';
        }
        if (isset($bindings['firm_id'])) {
            $sql .= ' AND u.firm_id = :firm_id';
        }
        if (isset($bindings['s1'])) {
            $sql .= ' AND (u.first_name LIKE :s1 OR u.middle_name LIKE :s2
                       OR u.last_name LIKE :s3 OR u.username LIKE :s4
                       OR u.email LIKE :s5 OR u.mobile_no LIKE :s6)';
        }
        return $sql;
    }

    public function index(Request $request): Response
    {
        // Scope by the globally selected academic year / firm (clients send the
        // header switcher context). Only applied when a value is provided.
        $bindings = ['role' => self::ROLE_STUDENT];
        $academicYearId = $request->query('academic_year_id');
        if ($academicYearId !== null && $academicYearId !== '') {
            $bindings['academic_year_id'] = (int) $academicYearId;
        }
        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $bindings['firm_id'] = (int) $firmId;
        }

        // Case-insensitive LIKE search across the student name/contact fields.
        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '') {
            $term = '%' . trim((string) $search) . '%';
            $bindings['s1'] = $term;
            $bindings['s2'] = $term;
            $bindings['s3'] = $term;
            $bindings['s4'] = $term;
            $bindings['s5'] = $term;
            $bindings['s6'] = $term;
        }

        $paginate = $request->query('page') !== null || $request->query('per_page') !== null;
        if ($paginate) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = (int) $request->query('per_page', 10);
            if ($perPage < 1) {
                $perPage = 10;
            }
            if ($perPage > 100) {
                $perPage = 100;
            }

            $countStmt = Database::pdo()->prepare($this->studentCountQuery($bindings));
            $countStmt->execute($bindings);
            $total = (int) $countStmt->fetchColumn();

            $stmt = Database::pdo()->prepare(
                'SELECT u.id, u.first_name, u.middle_name, u.last_name, u.username,
                        u.title_id, u.gender, u.dob, u.doj, u.email, u.mobile_no,
                        u.city_id, u.academic_year_id, u.firm_id, u.custom_field_category_id,
                        u.document_id, u.is_active,
                        s.enrollment_number, s.scholar_number, s.roll_number,
                        s.father_email, s.father_mobile_no, s.photo, s.section_id,
                        s.student_category_id, sc.name AS student_category_name,
                        sec.name AS section_name, sec.class_id, ac.name AS class_name
                 FROM users u
                 LEFT JOIN students s ON s.user_id = u.id
                 LEFT JOIN student_categories sc ON sc.id = s.student_category_id
                 LEFT JOIN sections sec ON sec.id = s.section_id
                 LEFT JOIN academic_classes ac ON ac.id = sec.class_id
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE r.name = :role AND u.deleted_at IS NULL AND (s.deleted_at IS NULL OR s.id IS NULL)'
                    . $this->buildStudentFilters(array_diff_key($bindings, ['role']))
                    . ' ORDER BY u.first_name ASC LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
            foreach ($bindings as $key => $val) {
                $stmt->bindValue(':' . $key, $val);
            }
            $stmt->execute();

            return Response::success([
                'items'       => $stmt->fetchAll(\PDO::FETCH_ASSOC),
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ]);
        }

        // Plain list (used by option loaders / non-paginated consumers).
        $stmt = Database::pdo()->prepare(
            'SELECT u.id, u.first_name, u.middle_name, u.last_name, u.username,
                    u.title_id, u.gender, u.dob, u.doj, u.email, u.mobile_no,
                    u.city_id, u.academic_year_id, u.firm_id, u.custom_field_category_id,
                    u.document_id, u.is_active,
                    s.enrollment_number, s.scholar_number, s.roll_number,
                    s.father_email, s.father_mobile_no, s.photo, s.section_id,
                    s.student_category_id, sc.name AS student_category_name,
                    sec.name AS section_name, sec.class_id, ac.name AS class_name
             FROM users u
             LEFT JOIN students s ON s.user_id = u.id
             LEFT JOIN student_categories sc ON sc.id = s.student_category_id
             LEFT JOIN sections sec ON sec.id = s.section_id
             LEFT JOIN academic_classes ac ON ac.id = sec.class_id
             INNER JOIN roles r ON r.id = u.role_id
             WHERE r.name = :role AND u.deleted_at IS NULL AND (s.deleted_at IS NULL OR s.id IS NULL)'
                . $this->buildStudentFilters(array_diff_key($bindings, ['role']))
                . ' ORDER BY u.first_name ASC LIMIT 1000'
        );
        $stmt->execute($bindings);

        return Response::success($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function show(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $stmt = Database::pdo()->prepare(
            'SELECT u.id, u.first_name, u.middle_name, u.last_name, u.username,
                    u.title_id, u.gender, u.dob, u.doj, u.email, u.mobile_no,
                    u.city_id, u.academic_year_id, u.firm_id, u.custom_field_category_id,
                    u.document_id, u.is_active,
                    s.enrollment_number, s.scholar_number, s.roll_number,
                    s.father_email, s.father_mobile_no, s.photo, s.section_id,
                    s.student_category_id, sc.name AS student_category_name,
                    sec.name AS section_name, sec.class_id, ac.name AS class_name
             FROM users u
             LEFT JOIN students s ON s.user_id = u.id
             LEFT JOIN student_categories sc ON sc.id = s.student_category_id
             LEFT JOIN sections sec ON sec.id = s.section_id
             LEFT JOIN academic_classes ac ON ac.id = sec.class_id
             WHERE u.id = :id AND u.deleted_at IS NULL AND (s.deleted_at IS NULL OR s.id IS NULL)'
        );
        $stmt->execute(['id' => $id]);
        $student = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $student
            ? Response::success($student)
            : Response::error('Student not found.', 404);
    }

    public function store(Request $request): Response
    {
        $data = $request->all();
        $validator = Validator::make($data, $this->rules());
        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        // A brand-new student must set a password.
        if (empty($data['password']) || strlen((string) $data['password']) < 8) {
            return Response::error('Password is required (min 8 characters).', 422, [
                'password' => ['Password is required (min 8 characters).'],
            ]);
        }

        $student = new Student();
        $student->fill([
            'first_name'       => $data['first_name'],
            'middle_name'      => $data['middle_name'] ?? null,
            'last_name'        => $data['last_name'] ?? null,
            'username'         => $data['username'],
            'password'         => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'title_id'         => $data['title_id'] ?? null,
            'gender'           => $data['gender'] ?? null,
            'dob'              => $data['dob'] ?? null,
            'doj'              => $data['doj'] ?? null,
            'email'            => $data['email'] ?? null,
            'mobile_no'        => $data['mobile_no'] ?? null,
            'city_id'          => $data['city_id'] ?? null,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'firm_id'          => $data['firm_id'] ?? null,
            'custom_field_category_id' => $data['custom_field_category_id'] ?? null,
            'document_id'      => $data['document_id'] ?? null,
            'role_id'          => $this->studentRoleId(),
            'is_active'        => $data['is_active'] ?? 'y',
        ]);

        if (!$student->save()) {
            return Response::error('Could not create the student.', 500);
        }
        $studentId = (int) $student->attributes['id'];

        // Record the student-specific profile in the `students` table. Its
        // NOT NULL columns get a generated value when the form left them blank.
        $this->upsertStudentProfile($studentId, $data);

        return Response::success($this->studentPayload($studentId), 'Student created.', 201);
    }

    public function update(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM users WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() === 0) {
            return Response::error('Student not found.', 404);
        }

        $data = $request->all();
        $validator = Validator::make($data, $this->rules());
        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $student = Student::find($id);

        // Build the payload explicitly so an empty/absent password is never
        // written over the existing (hashed) one on edit.
        $payload = [
            'first_name'       => $data['first_name'],
            'middle_name'      => $data['middle_name'] ?? null,
            'last_name'        => $data['last_name'] ?? null,
            'username'         => $data['username'],
            'title_id'         => array_key_exists('title_id', $data) ? $data['title_id'] : $student->title_id ?? null,
            'gender'           => $data['gender'] ?? $student->gender ?? null,
            'dob'              => array_key_exists('dob', $data) ? $data['dob'] : $student->dob ?? null,
            'doj'              => array_key_exists('doj', $data) ? $data['doj'] : $student->doj ?? null,
            'email'            => $data['email'] ?? null,
            'mobile_no'        => $data['mobile_no'] ?? null,
            'city_id'          => array_key_exists('city_id', $data) ? $data['city_id'] : $student->city_id ?? null,
            'academic_year_id' => $data['academic_year_id'] ?? $student->academic_year_id ?? null,
            'firm_id'          => array_key_exists('firm_id', $data) ? $data['firm_id'] : $student->firm_id ?? null,
            'custom_field_category_id' => $data['custom_field_category_id'] ?? $student->custom_field_category_id ?? null,
            'document_id'      => $data['document_id'] ?? $student->document_id ?? null,
            'is_active'        => $data['is_active'] ?? $student->is_active ?? 'y',
        ];
        if (!empty($data['password']) && strlen((string) $data['password']) >= 8) {
            $payload['password'] = (string) $data['password']; // hashed by User::save()
        }

        $student->fill($payload);
        $student->save();
        $this->upsertStudentProfile($id, $data);

        return Response::success($this->studentPayload($id), 'Student updated.');
    }

    public function destroy(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);

        Database::pdo()->prepare(
            'UPDATE students SET deleted_at = NOW() WHERE user_id = :id AND deleted_at IS NULL'
        )->execute(['id' => $id]);

        return $stmt->rowCount() > 0
            ? Response::success(null, 'Student deleted.')
            : Response::error('Student not found.', 404);
    }

    private function studentPayload(int $id): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT u.id, u.first_name, u.middle_name, u.last_name, u.username,
                    u.title_id, u.gender, u.dob, u.doj, u.email, u.mobile_no, u.city_id,
                    u.academic_year_id, u.custom_field_category_id, u.document_id,
                    u.firm_id, u.is_active,
                    s.enrollment_number, s.scholar_number, s.roll_number,
                    s.father_email, s.father_mobile_no, s.photo, s.section_id,
                    s.student_category_id, sc.name AS student_category_name,
                    sec.name AS section_name, sec.class_id, ac.name AS class_name
             FROM users u
             LEFT JOIN students s ON s.user_id = u.id
             LEFT JOIN student_categories sc ON sc.id = s.student_category_id
             LEFT JOIN sections sec ON sec.id = s.section_id
             LEFT JOIN academic_classes ac ON ac.id = sec.class_id
             WHERE u.id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Insert or update the student-specific profile (the `students` table) for a
     * user. The table's NOT NULL columns get a generated fallback so the insert
     * never fails when the form left them blank.
     */
    private function upsertStudentProfile(int $userId, array $data): void
    {
        $now = date('Y-m-d H:i:s');
        $enr = (string) ($data['enrollment_number'] ?? '');
        $sch = (string) ($data['scholar_number'] ?? '');
        if (trim($enr) === '') {
            $enr = 'ENR-' . $userId . '-' . time();
        }
        if (trim($sch) === '') {
            $sch = 'SCH-' . $userId;
        }

        $photoPath = $this->processStudentPhoto($userId, $data['photo'] ?? null);

        $photoUpdateClause = 'photo = photo';
        if (array_key_exists('photo', $data)) {
            if ($data['photo'] === null || $data['photo'] === '' || $data['photo'] === 'remove') {
                $photoUpdateClause = 'photo = NULL';
            } else {
                $photoUpdateClause = 'photo = VALUES(photo)';
            }
        }

        $stmt = Database::pdo()->prepare(
            "INSERT INTO students
                (user_id, enrollment_number, scholar_number, roll_number,
                 father_email, father_mobile_no, photo, section_id, student_category_id, created_at, updated_at)
             VALUES
                (:user_id, :enr, :sch, :roll, :femail, :fmobile, :photo, :section, :category, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                roll_number         = VALUES(roll_number),
                father_email        = VALUES(father_email),
                father_mobile_no    = VALUES(father_mobile_no),
                {$photoUpdateClause},
                section_id          = VALUES(section_id),
                student_category_id = VALUES(student_category_id),
                updated_at          = NOW()"
        );
        $stmt->execute([
            'user_id'  => $userId,
            'enr'      => $enr,
            'sch'      => $sch,
            'roll'     => $data['roll_number'] ?? null,
            'femail'   => $data['father_email'] ?? null,
            'fmobile'  => $data['father_mobile_no'] ?? null,
            'photo'    => $photoPath,
            'section'  => !empty($data['section_id']) ? (int) $data['section_id'] : null,
            'category' => !empty($data['student_category_id']) ? (int) $data['student_category_id'] : null,
        ]);
    }

    /**
     * Decode base64 AVIF/image data URL and save to public/uploads/students.
     */
    private function processStudentPhoto(int $userId, ?string $photo): ?string
    {
        if (empty($photo)) {
            return null;
        }

        // If it's already a relative /uploads/ path or absolute URL, retain it
        if (str_starts_with($photo, '/uploads/') || str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return $photo;
        }

        // Handle base64 DataURL: data:image/avif;base64,...
        if (preg_match('/^data:image\/([a-zA-Z0-9\+\-]+);base64,(.+)$/', $photo, $matches)) {
            $format = strtolower($matches[1]);
            $ext = ($format === 'avif' || str_contains($format, 'avif')) ? 'avif' : ($format === 'webp' ? 'webp' : 'avif');
            $data = base64_decode($matches[2]);
            if ($data !== false) {
                $dir = dirname(__DIR__, 2) . '/public/uploads/students';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $filename = sprintf('student_%d_%s.%s', $userId, bin2hex(random_bytes(6)), $ext);
                $fullPath = $dir . '/' . $filename;
                file_put_contents($fullPath, $data);
                return '/uploads/students/' . $filename;
            }
        }

        return $photo;
    }
}