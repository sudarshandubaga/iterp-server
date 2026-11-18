<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\Student;
use Iterp\Models\CustomFieldValue;

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
            'firm_id' => 'nullable|integer|exists:firms,id',
            'title_id' => 'nullable|integer|exists:titles,id',
            'section_id' => 'nullable|integer|exists:sections,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:150',
            // Optional for edits; store() enforces it for brand-new students.
            'password' => 'nullable|string|min:8|max:72',
            'email' => 'nullable|email|max:255',
            'father_email' => 'nullable|email|max:255',
            'mobile_no' => 'nullable|string|max:50',
            'father_mobile_no' => 'nullable|string|max:50',
            'gender' => 'nullable|in:m,f',
            'dob' => 'nullable|date',
            'doj' => 'nullable|date',
            'is_active' => 'nullable|in:y,n',
            'enrollment_number' => 'nullable|string|max:100',
            'scholar_number' => 'nullable|string|max:100',
            'roll_number' => 'nullable|string|max:50',
            'photo' => 'nullable|string',
            'student_category_id' => 'nullable|integer|exists:student_categories,id',
            'fee_bill_scheme_id' => 'nullable|integer|exists:fee_bill_schemes,id',
            'fee_concession_id' => 'nullable|integer|exists:fee_concessions,id',
            'custom_field_category_id' => 'nullable|integer|exists:custom_field_categories,id',
            'document_id' => 'nullable|integer|exists:documents,id',
        ];
    }

    /** Columns searched by `?search=` in the student list. */
    protected array $searchable = ['first_name', 'middle_name', 'last_name', 'username', 'email', 'mobile_no', 'enrollment_number', 'scholar_number', 'roll_number'];

    protected function studentCountQuery(array $bindings): string
    {
        $where = 'SELECT COUNT(*) FROM users u
                  LEFT JOIN students s ON s.user_id = u.id
                  LEFT JOIN sections sec ON sec.id = s.section_id
                  INNER JOIN roles r ON r.id = u.role_id
                  WHERE r.name = :role AND u.deleted_at IS NULL AND (s.deleted_at IS NULL OR s.id IS NULL)';
        if (isset($bindings['academic_year_id'])) {
            $where .= ' AND COALESCE(s.academic_year_id, u.academic_year_id) = :academic_year_id';
        }
        if (isset($bindings['firm_id'])) {
            $where .= ' AND u.firm_id = :firm_id';
        }
        if (isset($bindings['section_id'])) {
            $where .= ' AND s.section_id = :section_id';
        }
        if (isset($bindings['class_id'])) {
            $where .= ' AND sec.class_id = :class_id';
        }
        if (isset($bindings['s1'])) {
            $where .= ' AND (u.first_name LIKE :s1 OR u.middle_name LIKE :s2
                       OR u.last_name LIKE :s3 OR u.username LIKE :s4
                       OR u.email LIKE :s5 OR u.mobile_no LIKE :s6
                       OR s.enrollment_number LIKE :s7 OR s.scholar_number LIKE :s8 OR s.roll_number LIKE :s9)';
        }
        return $where;
    }

    protected function buildStudentFilters(array $bindings): string
    {
        $sql = '';
        if (isset($bindings['academic_year_id'])) {
            $sql .= ' AND COALESCE(s.academic_year_id, u.academic_year_id) = :academic_year_id';
        }
        if (isset($bindings['firm_id'])) {
            $sql .= ' AND u.firm_id = :firm_id';
        }
        if (isset($bindings['section_id'])) {
            $sql .= ' AND s.section_id = :section_id';
        }
        if (isset($bindings['class_id'])) {
            $sql .= ' AND sec.class_id = :class_id';
        }
        if (isset($bindings['s1'])) {
            $sql .= ' AND (u.first_name LIKE :s1 OR u.middle_name LIKE :s2
                       OR u.last_name LIKE :s3 OR u.username LIKE :s4
                       OR u.email LIKE :s5 OR u.mobile_no LIKE :s6
                       OR s.enrollment_number LIKE :s7 OR s.scholar_number LIKE :s8 OR s.roll_number LIKE :s9)';
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
        $sectionId = $request->query('section_id');
        if ($sectionId !== null && $sectionId !== '') {
            $bindings['section_id'] = (int) $sectionId;
        }
        $classId = $request->query('class_id');
        if ($classId !== null && $classId !== '') {
            $bindings['class_id'] = (int) $classId;
        }

        // Case-insensitive LIKE search across name, contact, enrollment, roll fields.
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
            $bindings['s8'] = $term;
            $bindings['s9'] = $term;
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
                        s.id AS student_id, s.enrollment_number, s.scholar_number, s.roll_number,
                        s.father_email, s.father_mobile_no, s.photo, s.section_id,
                        s.fee_bill_scheme_id, s.fee_concession_id,
                        fbs.name AS fee_bill_scheme_name,
                        sec.fee_bill_scheme_id AS section_fee_bill_scheme_id,
                        sec_fbs.name AS section_fee_bill_scheme_name,
                        s.student_category_id, sc.name AS student_category_name,
                        sec.name AS section_name, sec.class_id, ac.name AS class_name,
                        COALESCE(
                            NULLIF(reg.father_name, \'\'),
                            (SELECT cfv.value
                             FROM custom_field_values cfv
                             INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
                             WHERE cfv.user_id = u.id
                               AND LOWER(TRIM(cf.name)) IN (\'father name\', \'fathers name\', \'father_name\')
                               AND cf.deleted_at IS NULL
                             LIMIT 1)
                        ) AS father_name
                 FROM users u
                 LEFT JOIN students s ON s.user_id = u.id
                 LEFT JOIN student_categories sc ON sc.id = s.student_category_id
                 LEFT JOIN sections sec ON sec.id = s.section_id
                 LEFT JOIN academic_classes ac ON ac.id = sec.class_id
                 LEFT JOIN fee_bill_schemes fbs ON fbs.id = s.fee_bill_scheme_id
                 LEFT JOIN fee_bill_schemes sec_fbs ON sec_fbs.id = sec.fee_bill_scheme_id
                 LEFT JOIN registrations reg ON reg.student_id = s.id
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
                'items' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ]);
        }

        // Plain list (used by option loaders / non-paginated consumers).
        $stmt = Database::pdo()->prepare(
            'SELECT u.id, u.first_name, u.middle_name, u.last_name, u.username,
                    u.title_id, u.gender, u.dob, u.doj, u.email, u.mobile_no,
                    u.city_id, u.academic_year_id, u.firm_id, u.custom_field_category_id,
                    u.document_id, u.is_active,
                    s.id AS student_id, s.enrollment_number, s.scholar_number, s.roll_number,
                    s.father_email, s.father_mobile_no, s.photo, s.section_id,
                    s.fee_bill_scheme_id, s.fee_concession_id,
                    fbs.name AS fee_bill_scheme_name,
                    sec.fee_bill_scheme_id AS section_fee_bill_scheme_id,
                    sec_fbs.name AS section_fee_bill_scheme_name,
                    s.student_category_id, sc.name AS student_category_name,
                    sec.name AS section_name, sec.class_id, ac.name AS class_name,
                    COALESCE(
                        NULLIF(reg.father_name, \'\'),
                        (SELECT cfv.value
                         FROM custom_field_values cfv
                         INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
                         WHERE cfv.user_id = u.id
                           AND LOWER(TRIM(cf.name)) IN (\'father name\', \'fathers name\', \'father_name\')
                           AND cf.deleted_at IS NULL
                         LIMIT 1)
                    ) AS father_name
             FROM users u
             LEFT JOIN students s ON s.user_id = u.id
             LEFT JOIN student_categories sc ON sc.id = s.student_category_id
             LEFT JOIN sections sec ON sec.id = s.section_id
             LEFT JOIN academic_classes ac ON ac.id = sec.class_id
             LEFT JOIN fee_bill_schemes fbs ON fbs.id = s.fee_bill_scheme_id
             LEFT JOIN fee_bill_schemes sec_fbs ON sec_fbs.id = sec.fee_bill_scheme_id
             LEFT JOIN registrations reg ON reg.student_id = s.id
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
                    u.title_id, t.name AS title_name,
                    u.gender, u.dob, u.doj, u.email, u.mobile_no,
                    u.city_id, c.name AS city_name, st.name AS state_name, co.name AS country_name,
                    COALESCE(s.academic_year_id, u.academic_year_id) AS academic_year_id,
                    ay.name AS academic_year_name,
                    u.firm_id, f.name AS firm_name,
                    u.custom_field_category_id, cfc.name AS custom_field_category_name,
                    u.document_id, doc.name AS document_name,
                    u.is_active,
                    s.id AS student_id, s.enrollment_number, s.scholar_number, s.roll_number,
                    s.father_email, s.father_mobile_no, s.photo, s.section_id,
                    s.fee_bill_scheme_id, fbs.name AS fee_bill_scheme_name,
                    sec.fee_bill_scheme_id AS section_fee_bill_scheme_id,
                    sec_fbs.name AS section_fee_bill_scheme_name,
                    s.fee_concession_id, fc.name AS fee_concession_name,
                    s.student_category_id, sc.name AS student_category_name,
                    sec.name AS section_name, sec.class_id, ac.name AS class_name,
                    reg.registration_no, reg.admission_no, reg.admission_date,
                    reg.mother_name, reg.father_occupation, reg.mother_occupation, reg.mother_mobile_no,
                    reg.guardian_name, reg.guardian_relation, reg.guardian_mobile_no,
                    reg.address AS registration_address, reg.pincode,
                    reg.blood_group, reg.religion, reg.nationality, reg.aadhaar_no,
                    reg.previous_school, reg.previous_class, reg.previous_marks_percentage,
                    reg.transfer_certificate_no,
                    COALESCE(
                        NULLIF(reg.father_name, \'\'),
                        (SELECT cfv.value
                         FROM custom_field_values cfv
                         INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
                         WHERE cfv.user_id = u.id
                           AND LOWER(TRIM(cf.name)) IN (\'father name\', \'fathers name\', \'father_name\')
                           AND cf.deleted_at IS NULL
                         LIMIT 1)
                    ) AS father_name
             FROM users u
             LEFT JOIN students s ON s.user_id = u.id
             LEFT JOIN titles t ON t.id = u.title_id
             LEFT JOIN cities c ON c.id = u.city_id
             LEFT JOIN states st ON st.id = c.state_id
             LEFT JOIN countries co ON co.id = st.country_id
             LEFT JOIN academic_years ay ON ay.id = COALESCE(s.academic_year_id, u.academic_year_id)
             LEFT JOIN firms f ON f.id = u.firm_id
             LEFT JOIN custom_field_categories cfc ON cfc.id = u.custom_field_category_id
             LEFT JOIN documents doc ON doc.id = u.document_id
             LEFT JOIN student_categories sc ON sc.id = s.student_category_id
             LEFT JOIN sections sec ON sec.id = s.section_id
             LEFT JOIN academic_classes ac ON ac.id = sec.class_id
             LEFT JOIN fee_bill_schemes fbs ON fbs.id = s.fee_bill_scheme_id
             LEFT JOIN fee_bill_schemes sec_fbs ON sec_fbs.id = sec.fee_bill_scheme_id
             LEFT JOIN fee_concessions fc ON fc.id = s.fee_concession_id
             LEFT JOIN registrations reg ON reg.student_id = s.id
             WHERE (u.id = :id_u OR s.id = :id_s) AND u.deleted_at IS NULL AND (s.deleted_at IS NULL OR s.id IS NULL)
             ORDER BY (u.id = :id_ord AND s.id IS NOT NULL) DESC, (u.id = :id_ord2) DESC
             LIMIT 1'
        );
        $stmt->execute([
            'id_u' => $id,
            'id_s' => $id,
            'id_ord' => $id,
            'id_ord2' => $id,
        ]);
        $student = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($student) {
            $cf = CustomFieldValue::getForUser((int) $student['id']);
            foreach ($cf['answers'] as $k => $v) {
                $student[$k] = $v;
            }
            $student['custom_field_values'] = $cf['by_name'];
        }

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
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'username' => $data['username'],
            'password' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'title_id' => $data['title_id'] ?? null,
            'gender' => $data['gender'] ?? null,
            'dob' => $data['dob'] ?? null,
            'doj' => $data['doj'] ?? null,
            'email' => $data['email'] ?? null,
            'mobile_no' => $data['mobile_no'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'firm_id' => $data['firm_id'] ?? null,
            'custom_field_category_id' => $data['custom_field_category_id'] ?? null,
            'document_id' => $data['document_id'] ?? null,
            'role_id' => $this->studentRoleId(),
            'is_active' => $data['is_active'] ?? 'y',
        ]);

        if (!$student->save()) {
            return Response::error('Could not create the student.', 500);
        }
        $studentId = (int) $student->attributes['id'];

        // Record the student-specific profile in the `students` table. Its
        // NOT NULL columns get a generated value when the form left them blank.
        $this->upsertStudentProfile($studentId, $data);
        $this->saveCustomFieldAnswers($studentId, $data);

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
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'username' => $data['username'],
            'title_id' => array_key_exists('title_id', $data) ? $data['title_id'] : $student->title_id ?? null,
            'gender' => $data['gender'] ?? $student->gender ?? null,
            'dob' => array_key_exists('dob', $data) ? $data['dob'] : $student->dob ?? null,
            'doj' => array_key_exists('doj', $data) ? $data['doj'] : $student->doj ?? null,
            'email' => $data['email'] ?? null,
            'mobile_no' => $data['mobile_no'] ?? null,
            'city_id' => array_key_exists('city_id', $data) ? $data['city_id'] : $student->city_id ?? null,
            'academic_year_id' => $data['academic_year_id'] ?? $student->academic_year_id ?? null,
            'firm_id' => array_key_exists('firm_id', $data) ? $data['firm_id'] : $student->firm_id ?? null,
            'custom_field_category_id' => $data['custom_field_category_id'] ?? $student->custom_field_category_id ?? null,
            'document_id' => $data['document_id'] ?? $student->document_id ?? null,
            'is_active' => $data['is_active'] ?? $student->is_active ?? 'y',
        ];
        if (!empty($data['password']) && strlen((string) $data['password']) >= 8) {
            $payload['password'] = (string) $data['password']; // hashed by User::save()
        }

        $student->fill($payload);
        $student->save();
        $this->upsertStudentProfile($id, $data);
        $this->saveCustomFieldAnswers($id, $data);

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
                    s.fee_bill_scheme_id, s.fee_concession_id,
                    fbs.name AS fee_bill_scheme_name,
                    fc.name AS fee_concession_name,
                    sec.fee_bill_scheme_id AS section_fee_bill_scheme_id,
                    sec_fbs.name AS section_fee_bill_scheme_name,
                    s.student_category_id, sc.name AS student_category_name,
                    sec.name AS section_name, sec.class_id, ac.name AS class_name
             FROM users u
             LEFT JOIN students s ON s.user_id = u.id
             LEFT JOIN student_categories sc ON sc.id = s.student_category_id
             LEFT JOIN sections sec ON sec.id = s.section_id
             LEFT JOIN academic_classes ac ON ac.id = sec.class_id
             LEFT JOIN fee_bill_schemes fbs ON fbs.id = s.fee_bill_scheme_id
             LEFT JOIN fee_concessions fc ON fc.id = s.fee_concession_id
             LEFT JOIN fee_bill_schemes sec_fbs ON sec_fbs.id = sec.fee_bill_scheme_id
             WHERE u.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        if ($row) {
            $cf = CustomFieldValue::getForUser($id);
            foreach ($cf['answers'] as $k => $v) {
                $row[$k] = $v;
            }
            $row['custom_field_values'] = $cf['by_name'];
        }
        return $row;
    }

    /**
     * Persist any cf_{id} answers into custom_field_values table.
     */
    private function saveCustomFieldAnswers(int $userId, array $data): void
    {
        foreach ($data as $key => $val) {
            if (str_starts_with($key, 'cf_') && is_numeric(substr($key, 3))) {
                $cfId = (int) substr($key, 3);
                $strVal = is_array($val) ? implode(', ', $val) : ($val !== null ? (string) $val : null);
                CustomFieldValue::setValue($userId, $cfId, $strVal);
            }
        }
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
                 father_email, father_mobile_no, photo, section_id, student_category_id,
                 fee_bill_scheme_id, fee_concession_id, created_at, updated_at)
             VALUES
                (:user_id, :enr, :sch, :roll, :femail, :fmobile, :photo, :section, :category,
                 :scheme, :concession, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                roll_number         = VALUES(roll_number),
                father_email        = VALUES(father_email),
                father_mobile_no    = VALUES(father_mobile_no),
                {$photoUpdateClause},
                section_id          = VALUES(section_id),
                student_category_id = VALUES(student_category_id),
                fee_bill_scheme_id  = VALUES(fee_bill_scheme_id),
                fee_concession_id   = VALUES(fee_concession_id),
                updated_at          = NOW()"
        );
        $stmt->execute([
            'user_id' => $userId,
            'enr' => $enr,
            'sch' => $sch,
            'roll' => $data['roll_number'] ?? null,
            'femail' => $data['father_email'] ?? null,
            'fmobile' => $data['father_mobile_no'] ?? null,
            'photo' => $photoPath,
            'section' => !empty($data['section_id']) ? (int) $data['section_id'] : null,
            'category' => !empty($data['student_category_id']) ? (int) $data['student_category_id'] : null,
            'scheme' => !empty($data['fee_bill_scheme_id']) ? (int) $data['fee_bill_scheme_id'] : null,
            'concession' => !empty($data['fee_concession_id']) ? (int) $data['fee_concession_id'] : null,
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

        // If it's already an upload on our server (relative or full URL), retain the clean relative path in DB
        if (preg_match('~(/uploads/[^\s?#]+)~i', $photo, $m)) {
            return $m[1];
        }

        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
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

    /**
     * GET /api/students/fast-edit-data
     * Returns all students in a section (including all custom_field_values as cf_{id})
     * plus the list of student custom fields for the Fast Edit bulk-update workflow.
     */
    public function fastEditData(Request $request): Response
    {
        $sectionId = (int) $request->query('section_id');
        if (!$sectionId) {
            return Response::error('section_id is required.', 422);
        }

        $pdo = Database::pdo();

        // 1. Section & Class details
        $secStmt = $pdo->prepare(
            'SELECT sec.id, sec.name AS section_name, sec.class_id, ac.name AS class_name
             FROM sections sec
             LEFT JOIN academic_classes ac ON ac.id = sec.class_id
             WHERE sec.id = :id AND sec.deleted_at IS NULL
             LIMIT 1'
        );
        $secStmt->execute(['id' => $sectionId]);
        $section = $secStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$section) {
            return Response::error('Section not found.', 404);
        }

        // 2. Fetch all students in this section
        $bindings = ['section_id' => $sectionId];
        $extraWhere = '';
        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $bindings['firm_id'] = (int) $firmId;
            $extraWhere .= ' AND (u.firm_id = :firm_id OR u.firm_id IS NULL)';
        }

        $sql = "SELECT u.id, u.first_name, u.middle_name, u.last_name, u.username,
                       u.title_id, u.gender, u.dob, u.doj, u.email, u.mobile_no,
                       u.city_id, u.academic_year_id, u.firm_id, u.is_active,
                       s.id AS student_id, s.enrollment_number, s.scholar_number, s.roll_number,
                       s.father_email, s.father_mobile_no, s.photo, s.section_id,
                       s.student_category_id, s.fee_bill_scheme_id, s.fee_concession_id,
                       sec.name AS section_name, sec.class_id, ac.name AS class_name
                FROM students s
                INNER JOIN users u ON u.id = s.user_id AND u.deleted_at IS NULL
                LEFT JOIN sections sec ON sec.id = s.section_id
                LEFT JOIN academic_classes ac ON ac.id = sec.class_id
                WHERE s.section_id = :section_id
                  AND s.deleted_at IS NULL
                  {$extraWhere}
                ORDER BY CAST(s.roll_number AS UNSIGNED) ASC, s.roll_number ASC, u.first_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Batch-load all custom_field_values for these users
        $userIds = array_map(static fn($r) => (int) $r['id'], $students);
        $cfByUser = [];
        if (!empty($userIds)) {
            $inClause = implode(',', array_fill(0, count($userIds), '?'));
            $cfStmt = $pdo->prepare(
                "SELECT cfv.user_id, cfv.custom_field_id, cfv.value
                 FROM custom_field_values cfv
                 INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id AND cf.deleted_at IS NULL
                 WHERE cfv.user_id IN ({$inClause})"
            );
            $cfStmt->execute($userIds);
            foreach ($cfStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $uid = (int) $row['user_id'];
                $cfByUser[$uid]['cf_' . $row['custom_field_id']] = $row['value'];
            }
        }

        foreach ($students as &$stu) {
            $uid = (int) $stu['id'];
            $stu['id'] = $uid;
            $stu['student_id'] = (int) $stu['student_id'];
            if (isset($cfByUser[$uid])) {
                foreach ($cfByUser[$uid] as $k => $v) {
                    $stu[$k] = $v;
                }
            }
        }
        unset($stu);

        // 4. Fetch active student custom fields
        $cfListStmt = $pdo->query(
            "SELECT cf.id, cf.custom_field_category_id, cf.name, cf.field_type, cf.data_type,
                    cf.options, cf.mandatory, cf.default_value, cf.max_length, cf.sort_order,
                    cfc.name AS category_name
             FROM custom_fields cf
             INNER JOIN custom_field_categories cfc ON cfc.id = cf.custom_field_category_id
             WHERE cfc.type = 'student'
               AND cfc.deleted_at IS NULL
               AND cf.deleted_at IS NULL
               AND cf.`show` = 'yes'
             ORDER BY cfc.sort_order ASC, cfc.id ASC, cf.sort_order ASC, cf.id ASC"
        );
        $customFields = array_map(static function (array $r): array {
            return [
                'id'                       => (int) $r['id'],
                'custom_field_category_id' => (int) $r['custom_field_category_id'],
                'category_name'            => (string) $r['category_name'],
                'name'                     => (string) $r['name'],
                'field_type'               => (string) $r['field_type'],
                'data_type'                => (string) $r['data_type'],
                'options'                  => $r['options'],
                'mandatory'                => (string) $r['mandatory'],
                'default_value'            => $r['default_value'],
                'max_length'               => $r['max_length'] !== null ? (int) $r['max_length'] : null,
            ];
        }, $cfListStmt ? $cfListStmt->fetchAll(\PDO::FETCH_ASSOC) : []);

        return Response::success([
            'section'       => [
                'id'           => (int) $section['id'],
                'section_name' => (string) $section['section_name'],
                'class_id'     => (int) $section['class_id'],
                'class_name'   => (string) ($section['class_name'] ?? ''),
            ],
            'students'      => $students,
            'custom_fields' => $customFields,
        ]);
    }

    /**
     * POST /api/students/bulk-update
     * Bulk-updates ONLY the selected fields for the selected students inside a transaction.
     * Payload: { fields: string[], rows: [{ id: user_id, values: { field: value, ... } }] }
     */
    public function bulkUpdate(Request $request): Response
    {
        $data = $request->all();
        $fields = $data['fields'] ?? [];
        $rows = $data['rows'] ?? [];

        if (!is_array($fields) || empty($fields)) {
            return Response::error('Please select at least one field to update.', 422);
        }
        if (!is_array($rows) || empty($rows)) {
            return Response::error('Please select at least one student to update.', 422);
        }

        $allowedUserCols = [
            'first_name'       => 'string',
            'middle_name'      => 'nullable_string',
            'last_name'        => 'nullable_string',
            'username'         => 'string',
            'title_id'         => 'nullable_int',
            'gender'           => 'nullable_string',
            'dob'              => 'nullable_date',
            'doj'              => 'nullable_date',
            'email'            => 'nullable_string',
            'mobile_no'        => 'nullable_string',
            'city_id'          => 'nullable_int',
            'academic_year_id' => 'nullable_int',
            'is_active'        => 'active_flag',
            'password'         => 'password',
        ];

        $allowedStudentCols = [
            'enrollment_number'   => 'nullable_string',
            'scholar_number'      => 'nullable_string',
            'roll_number'         => 'nullable_string',
            'father_email'        => 'nullable_string',
            'father_mobile_no'    => 'nullable_string',
            'section_id'          => 'nullable_int',
            'student_category_id' => 'nullable_int',
            'fee_bill_scheme_id'  => 'nullable_int',
            'fee_concession_id'   => 'nullable_int',
        ];

        $selectedUserCols = [];
        $selectedStudentCols = [];
        $selectedCustomIds = [];

        foreach ($fields as $f) {
            $key = (string) $f;
            if (isset($allowedUserCols[$key])) {
                $selectedUserCols[$key] = $allowedUserCols[$key];
            } elseif (isset($allowedStudentCols[$key])) {
                $selectedStudentCols[$key] = $allowedStudentCols[$key];
            } elseif (str_starts_with($key, 'cf_') && ctype_digit(substr($key, 3))) {
                $selectedCustomIds[$key] = (int) substr($key, 3);
            }
        }

        if (empty($selectedUserCols) && empty($selectedStudentCols) && empty($selectedCustomIds)) {
            return Response::error('No valid editable fields were selected.', 422);
        }

        $pdo = Database::pdo();
        $updatedCount = 0;

        try {
            $pdo->beginTransaction();

            foreach ($rows as $row) {
                $userId = isset($row['id']) ? (int) $row['id'] : 0;
                $values = is_array($row['values'] ?? null) ? $row['values'] : [];
                if ($userId <= 0) {
                    continue;
                }

                // 1. Update `users` columns if any selected
                if (!empty($selectedUserCols)) {
                    $setParts = [];
                    $params = ['id' => $userId];
                    foreach ($selectedUserCols as $col => $kind) {
                        if (!array_key_exists($col, $values)) {
                            continue;
                        }
                        $raw = $values[$col];
                        if ($kind === 'password') {
                            $pw = trim((string) ($raw ?? ''));
                            if ($pw === '') {
                                continue; // Blank password leaves existing password unchanged
                            }
                            if (strlen($pw) < 8) {
                                throw new \RuntimeException("Password for student #{$userId} must be at least 8 characters.");
                            }
                            $setParts[] = '`password` = :password';
                            $params['password'] = password_hash($pw, PASSWORD_BCRYPT);
                            continue;
                        }
                        if ($kind === 'string') {
                            $val = trim((string) ($raw ?? ''));
                            if ($val === '' && $col === 'first_name') {
                                throw new \RuntimeException("First Name cannot be empty (student #{$userId}).");
                            }
                            if ($val === '' && $col === 'username') {
                                throw new \RuntimeException("Username cannot be empty (student #{$userId}).");
                            }
                            $setParts[] = "`{$col}` = :{$col}";
                            $params[$col] = $val;
                        } elseif ($kind === 'nullable_int') {
                            $val = ($raw === '' || $raw === null) ? null : (int) $raw;
                            $setParts[] = "`{$col}` = :{$col}";
                            $params[$col] = $val;
                        } elseif ($kind === 'nullable_date') {
                            $val = trim((string) ($raw ?? ''));
                            $setParts[] = "`{$col}` = :{$col}";
                            $params[$col] = $val === '' ? null : $val;
                        } elseif ($kind === 'active_flag') {
                            $val = in_array((string) $raw, ['y', 'n'], true) ? (string) $raw : 'y';
                            $setParts[] = "`{$col}` = :{$col}";
                            $params[$col] = $val;
                        } else {
                            $val = trim((string) ($raw ?? ''));
                            $setParts[] = "`{$col}` = :{$col}";
                            $params[$col] = $val === '' ? null : $val;
                        }
                    }
                    if (!empty($setParts)) {
                        $setParts[] = 'updated_at = NOW()';
                        $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id AND deleted_at IS NULL';
                        $pdo->prepare($sql)->execute($params);
                    }
                }

                // 2. Update `students` columns if any selected
                if (!empty($selectedStudentCols)) {
                    $setParts = [];
                    $params = ['uid' => $userId];
                    foreach ($selectedStudentCols as $col => $kind) {
                        if (!array_key_exists($col, $values)) {
                            continue;
                        }
                        $raw = $values[$col];
                        if ($kind === 'nullable_int') {
                            $val = ($raw === '' || $raw === null) ? null : (int) $raw;
                        } else {
                            $trimmed = trim((string) ($raw ?? ''));
                            $val = $trimmed === '' ? null : $trimmed;
                        }
                        // Enrollment & scholar numbers are NOT NULL in schema; skip clearing them to null
                        if (($col === 'enrollment_number' || $col === 'scholar_number') && $val === null) {
                            continue;
                        }
                        $setParts[] = "`{$col}` = :{$col}";
                        $params[$col] = $val;
                    }
                    if (!empty($setParts)) {
                        $setParts[] = 'updated_at = NOW()';
                        $sql = 'UPDATE students SET ' . implode(', ', $setParts) . ' WHERE user_id = :uid AND deleted_at IS NULL';
                        $pdo->prepare($sql)->execute($params);
                    }
                }

                // 3. Update `custom_field_values` if any selected
                foreach ($selectedCustomIds as $cfKey => $cfId) {
                    if (!array_key_exists($cfKey, $values)) {
                        continue;
                    }
                    $raw = $values[$cfKey];
                    $strVal = is_array($raw)
                        ? implode(', ', $raw)
                        : ($raw !== null && trim((string) $raw) !== '' ? trim((string) $raw) : null);
                    CustomFieldValue::setValue($userId, $cfId, $strVal);
                }

                $updatedCount++;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Bulk update failed: ' . $e->getMessage(), 422);
        }

        return Response::success([
            'updated' => $updatedCount,
        ], "Successfully updated {$updatedCount} student(s).");
    }
}