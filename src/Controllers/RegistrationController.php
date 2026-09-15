<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\Registration;
use Iterp\Models\Prospectus;
use Iterp\Models\User;
use Iterp\Models\Role;

/**
 * Controller for Admission Registration & Student Conversion.
 */
class RegistrationController
{
    protected function rules(): array
    {
        return [
            'registration_no'           => 'nullable|string|max:100',
            'prospectus_id'             => 'nullable|integer|exists:prospectus,id',
            'academic_year_id'          => 'nullable|integer|exists:academic_years,id',
            'firm_id'                   => 'nullable|integer|exists:firms,id',
            'class_id'                  => 'required|integer|exists:academic_classes,id',
            'section_id'                => 'nullable|integer|exists:sections,id',
            'student_category_id'       => 'nullable|integer|exists:student_categories,id',

            'first_name'                => 'required|string|max:255',
            'middle_name'               => 'nullable|string|max:255',
            'last_name'                 => 'nullable|string|max:255',
            'gender'                    => 'nullable|in:m,f,other',
            'dob'                       => 'nullable|date',
            'email'                     => 'nullable|email|max:255',
            'mobile_no'                 => 'nullable|string|max:50',
            'photo'                     => 'nullable|string',
            'blood_group'               => 'nullable|string|max:10',
            'religion'                  => 'nullable|string|max:50',
            'nationality'               => 'nullable|string|max:50',
            'aadhaar_no'                => 'nullable|string|max:30',

            'address'                   => 'nullable|string',
            'city_id'                   => 'nullable|integer|exists:cities,id',
            'state_id'                  => 'nullable|integer|exists:states,id',
            'pincode'                   => 'nullable|string|max:20',

            'father_name'               => 'nullable|string|max:255',
            'father_occupation'         => 'nullable|string|max:100',
            'father_mobile_no'          => 'nullable|string|max:50',
            'father_email'              => 'nullable|email|max:255',
            'mother_name'               => 'nullable|string|max:255',
            'mother_occupation'         => 'nullable|string|max:100',
            'mother_mobile_no'          => 'nullable|string|max:50',
            'guardian_name'             => 'nullable|string|max:255',
            'guardian_relation'         => 'nullable|string|max:50',
            'guardian_mobile_no'        => 'nullable|string|max:50',

            'previous_school'           => 'nullable|string|max:255',
            'previous_class'            => 'nullable|string|max:100',
            'previous_marks_percentage' => 'nullable|numeric|between:0,100',
            'transfer_certificate_no'   => 'nullable|string|max:100',

            'registration_fee'          => 'nullable|numeric|min:0',
            'payment_mode'              => 'nullable|string|max:50',
            'payment_status'            => 'nullable|in:paid,partial,pending',
            'transaction_no'            => 'nullable|string|max:100',
            'registration_date'         => 'required|date',

            'status'                    => 'nullable|in:applied,under_review,shortlisted,admitted,rejected,cancelled',
            'admission_date'            => 'nullable|date',
            'admission_no'              => 'nullable|string|max:100',
            'remarks'                   => 'nullable|string',
        ];
    }

    public function index(Request $request): Response
    {
        $bindings = [];
        $where = 'WHERE r.deleted_at IS NULL';

        $academicYearId = $request->query('academic_year_id');
        if ($academicYearId !== null && $academicYearId !== '') {
            $bindings['academic_year_id'] = (int) $academicYearId;
            $where .= ' AND r.academic_year_id = :academic_year_id';
        }

        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $bindings['firm_id'] = (int) $firmId;
            $where .= ' AND r.firm_id = :firm_id';
        }

        $classId = $request->query('class_id');
        if ($classId !== null && $classId !== '') {
            $bindings['class_id'] = (int) $classId;
            $where .= ' AND r.class_id = :class_id';
        }

        $sectionId = $request->query('section_id');
        if ($sectionId !== null && $sectionId !== '') {
            $bindings['section_id'] = (int) $sectionId;
            $where .= ' AND r.section_id = :section_id';
        }

        $status = $request->query('status');
        if ($status !== null && $status !== '') {
            $bindings['status'] = (string) $status;
            $where .= ' AND r.status = :status';
        }

        $paymentStatus = $request->query('payment_status');
        if ($paymentStatus !== null && $paymentStatus !== '') {
            $bindings['payment_status'] = (string) $paymentStatus;
            $where .= ' AND r.payment_status = :payment_status';
        }

        $gender = $request->query('gender');
        if ($gender !== null && $gender !== '') {
            $bindings['gender'] = (string) $gender;
            $where .= ' AND r.gender = :gender';
        }

        $fromDate = $request->query('from_date');
        if ($fromDate !== null && $fromDate !== '') {
            $bindings['from_date'] = (string) $fromDate;
            $where .= ' AND r.registration_date >= :from_date';
        }

        $toDate = $request->query('to_date');
        if ($toDate !== null && $toDate !== '') {
            $bindings['to_date'] = (string) $toDate;
            $where .= ' AND r.registration_date <= :to_date';
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
            $where .= ' AND (r.registration_no LIKE :s1 OR r.first_name LIKE :s2
                       OR r.middle_name LIKE :s3 OR r.last_name LIKE :s4
                       OR r.mobile_no LIKE :s5 OR r.email LIKE :s6
                       OR r.father_name LIKE :s7)';
        }

        $paginate = $request->query('page') !== null || $request->query('per_page') !== null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 15);
        if ($perPage < 1) $perPage = 15;
        if ($perPage > 100) $perPage = 100;

        $countSql = "SELECT COUNT(*) FROM registrations r {$where}";
        $countStmt = Database::pdo()->prepare($countSql);
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT r.*,
                       ac.name AS class_name,
                       ac.short_name AS class_short_name,
                       sec.name AS section_name,
                       sc.name AS category_name,
                       ay.name AS academic_year_name,
                       f.name AS firm_name,
                       p.prospectus_no AS linked_prospectus_no
                FROM registrations r
                LEFT JOIN academic_classes ac ON ac.id = r.class_id
                LEFT JOIN sections sec ON sec.id = r.section_id
                LEFT JOIN student_categories sc ON sc.id = r.student_category_id
                LEFT JOIN academic_years ay ON ay.id = r.academic_year_id
                LEFT JOIN firms f ON f.id = r.firm_id
                LEFT JOIN prospectus p ON p.id = r.prospectus_id
                {$where}
                ORDER BY r.registration_date DESC, r.id DESC";

        if ($paginate) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = Database::pdo()->prepare($sql);
        if ($paginate) {
            $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        }
        foreach ($bindings as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($paginate) {
            return Response::paginated($items, $total, $page, $perPage);
        }
        return Response::success($items);
    }

    public function show(Request $request, array $context = [], array $params = []): Response
    {
        $id = (int) ($params['id'] ?? $context['id'] ?? 0);
        $sql = "SELECT r.*,
                       ac.name AS class_name,
                       ac.short_name AS class_short_name,
                       sec.name AS section_name,
                       sc.name AS category_name,
                       ay.name AS academic_year_name,
                       f.name AS firm_name,
                       ci.name AS city_name,
                       st.name AS state_name,
                       p.prospectus_no AS linked_prospectus_no,
                       p.amount AS prospectus_amount,
                       stu.enrollment_number,
                       stu.scholar_number,
                       stu.roll_number
                FROM registrations r
                LEFT JOIN academic_classes ac ON ac.id = r.class_id
                LEFT JOIN sections sec ON sec.id = r.section_id
                LEFT JOIN student_categories sc ON sc.id = r.student_category_id
                LEFT JOIN academic_years ay ON ay.id = r.academic_year_id
                LEFT JOIN firms f ON f.id = r.firm_id
                LEFT JOIN cities ci ON ci.id = r.city_id
                LEFT JOIN states st ON st.id = r.state_id
                LEFT JOIN prospectus p ON p.id = r.prospectus_id
                LEFT JOIN students stu ON stu.id = r.student_id
                WHERE r.id = :id AND r.deleted_at IS NULL
                LIMIT 1";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$record) {
            return Response::error('Registration not found.', 404);
        }

        return Response::success($record);
    }

    public function store(Request $request): Response
    {
        $data = $request->all();
        $validator = Validator::make($data, $this->rules());

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $academicYearId = !empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : null;

        // Auto-generate registration_no if empty
        $regNo = trim((string) ($data['registration_no'] ?? ''));
        if ($regNo === '') {
            $regNo = Registration::generateNextNumber($academicYearId);
        }

        // Uniqueness check
        $existsStmt = Database::pdo()->prepare(
            'SELECT id FROM registrations WHERE registration_no = :no AND deleted_at IS NULL LIMIT 1'
        );
        $existsStmt->execute(['no' => $regNo]);
        if ($existsStmt->fetchColumn()) {
            return Response::error("Registration number '{$regNo}' is already in use.", 422, [
                'registration_no' => ["Registration number '{$regNo}' already exists."],
            ]);
        }

        // Process photo (AVIF / base64 or URL)
        $photoPath = $this->processPhoto($data['photo'] ?? null);

        $data['registration_no'] = $regNo;
        $data['photo'] = $photoPath;
        $data['status'] = $data['status'] ?? 'applied';
        $data['payment_mode'] = $data['payment_mode'] ?? 'Cash';
        $data['payment_status'] = $data['payment_status'] ?? 'paid';
        $data['registration_fee'] = isset($data['registration_fee']) ? (float) $data['registration_fee'] : 0.00;

        $registration = new Registration();
        $registration->fill($data);
        $registration->save();

        // If linked to prospectus, mark that prospectus as 'registered'
        if (!empty($data['prospectus_id'])) {
            $prospectusStmt = Database::pdo()->prepare(
                'UPDATE prospectus SET status = "registered", updated_at = NOW() WHERE id = :pid'
            );
            $prospectusStmt->execute(['pid' => (int) $data['prospectus_id']]);
        }

        return Response::success(
            $this->show($request, [], ['id' => $registration->id()])->data ?? $registration->attributes,
            'Registration created successfully.',
            201
        );
    }

    public function update(Request $request, array $context = [], array $params = []): Response
    {
        $id = (int) ($params['id'] ?? $context['id'] ?? 0);
        $registration = Registration::find($id);

        if (!$registration || $registration->deleted_at !== null) {
            return Response::error('Registration not found.', 404);
        }

        $data = $request->all();
        $validator = Validator::make($data, $this->rules());

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        if (!empty($data['registration_no']) && $data['registration_no'] !== $registration->registration_no) {
            $existsStmt = Database::pdo()->prepare(
                'SELECT id FROM registrations WHERE registration_no = :no AND id != :id AND deleted_at IS NULL LIMIT 1'
            );
            $existsStmt->execute(['no' => $data['registration_no'], 'id' => $id]);
            if ($existsStmt->fetchColumn()) {
                return Response::error("Registration number is already in use.", 422, [
                    'registration_no' => ["Registration number already exists."],
                ]);
            }
        }

        if (array_key_exists('photo', $data)) {
            $data['photo'] = $this->processPhoto($data['photo'], $registration->photo);
        }

        $registration->fill($data);
        $registration->save();

        return Response::success(
            $this->show($request, [], ['id' => $id])->data ?? $registration->attributes,
            'Registration updated successfully.'
        );
    }

    public function destroy(Request $request, array $context = [], array $params = []): Response
    {
        $id = (int) ($params['id'] ?? $context['id'] ?? 0);
        $stmt = Database::pdo()->prepare(
            'UPDATE registrations SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            return Response::error('Registration not found or already deleted.', 404);
        }

        return Response::success(null, 'Registration deleted successfully.');
    }

    public function nextNumber(Request $request): Response
    {
        $yearId = $request->query('academic_year_id');
        $num = Registration::generateNextNumber($yearId ? (int) $yearId : null);
        return Response::success(['next_number' => $num]);
    }

    /**
     * Confirm Admission & Convert applicant to permanent Student in users + students tables.
     */
    public function convertToStudent(Request $request, array $context = [], array $params = []): Response
    {
        $id = (int) ($params['id'] ?? $context['id'] ?? 0);
        $registration = Registration::find($id);

        if (!$registration || $registration->deleted_at !== null) {
            return Response::error('Registration not found.', 404);
        }

        if (!empty($registration->student_id)) {
            return Response::error('Candidate is already admitted as a student.', 422);
        }

        $data = $request->all();
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            // Find or default the Student role
            $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = "Student" AND deleted_at IS NULL LIMIT 1');
            $roleStmt->execute();
            $roleId = $roleStmt->fetchColumn() ?: 2;

            // Generate username
            $baseUsername = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $registration->first_name));
            if ($baseUsername === '') $baseUsername = 'student';
            $uniqueUsername = $baseUsername . '_' . rand(100, 9999);

            $defaultPassword = password_hash('Student@123', PASSWORD_BCRYPT);

            // Insert into users table
            $userInsert = "INSERT INTO users (
                first_name, middle_name, last_name, username, password,
                gender, role_id, dob, doj, email, mobile_no, city_id,
                is_active, firm_id, academic_year_id, created_at, updated_at
            ) VALUES (
                :first_name, :middle_name, :last_name, :username, :password,
                :gender, :role_id, :dob, :doj, :email, :mobile_no, :city_id,
                'y', :firm_id, :academic_year_id, NOW(), NOW()
            )";
            $userStmt = $pdo->prepare($userInsert);
            $userStmt->execute([
                'first_name'       => $registration->first_name,
                'middle_name'      => $registration->middle_name,
                'last_name'        => $registration->last_name,
                'username'         => $uniqueUsername,
                'password'         => $defaultPassword,
                'gender'           => $registration->gender,
                'role_id'          => $roleId,
                'dob'              => $registration->dob,
                'doj'              => $data['admission_date'] ?? date('Y-m-d'),
                'email'            => $registration->email,
                'mobile_no'        => $registration->mobile_no,
                'city_id'          => $registration->city_id,
                'firm_id'          => $registration->firm_id,
                'academic_year_id' => $registration->academic_year_id,
            ]);
            $userId = (int) $pdo->lastInsertId();

            // Insert into user_roles table
            $urStmt = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (:uid, :rid, NOW())');
            $urStmt->execute(['uid' => $userId, 'rid' => $roleId]);

            // Generate enrollment & scholar numbers
            $enrollmentNumber = $data['enrollment_number'] ?? ('ENR-' . date('Y') . '-' . str_pad((string) $userId, 4, '0', STR_PAD_LEFT));
            $scholarNumber = $data['scholar_number'] ?? ('SCH-' . date('Y') . '-' . str_pad((string) $userId, 4, '0', STR_PAD_LEFT));
            $rollNumber = $data['roll_number'] ?? null;
            $sectionId = !empty($data['section_id']) ? (int) $data['section_id'] : $registration->section_id;

            // Insert into students table
            $studentInsert = "INSERT INTO students (
                user_id, enrollment_number, scholar_number, roll_number,
                father_email, father_mobile_no, photo, section_id,
                academic_year_id, student_category_id, created_at, updated_at
            ) VALUES (
                :user_id, :enrollment_number, :scholar_number, :roll_number,
                :father_email, :father_mobile_no, :photo, :section_id,
                :academic_year_id, :student_category_id, NOW(), NOW()
            )";
            $studentStmt = $pdo->prepare($studentInsert);
            $studentStmt->execute([
                'user_id'             => $userId,
                'enrollment_number'   => $enrollmentNumber,
                'scholar_number'      => $scholarNumber,
                'roll_number'         => $rollNumber,
                'father_email'        => $registration->father_email,
                'father_mobile_no'    => $registration->father_mobile_no,
                'photo'               => $registration->photo,
                'section_id'          => $sectionId,
                'academic_year_id'    => $registration->academic_year_id,
                'student_category_id' => $registration->student_category_id,
            ]);
            $studentId = (int) $pdo->lastInsertId();

            // Update registration status to 'admitted'
            $admissionDate = $data['admission_date'] ?? date('Y-m-d');
            $regUpdate = "UPDATE registrations SET
                status = 'admitted',
                admission_date = :adm_date,
                student_id = :student_id,
                admission_no = :adm_no,
                section_id = :section_id,
                updated_at = NOW()
                WHERE id = :id";
            $regStmt = $pdo->prepare($regUpdate);
            $regStmt->execute([
                'adm_date'   => $admissionDate,
                'student_id' => $studentId,
                'adm_no'     => $scholarNumber,
                'section_id' => $sectionId,
                'id'         => $id,
            ]);

            $pdo->commit();

            return Response::success([
                'registration'      => $this->show($request, ['id' => $id])->data ?? null,
                'student_id'        => $studentId,
                'user_id'           => $userId,
                'enrollment_number' => $enrollmentNumber,
                'scholar_number'    => $scholarNumber,
            ], 'Student admission confirmed successfully.');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return Response::error('Admission confirmation failed: ' . $e->getMessage(), 500);
        }
    }

    private function processPhoto(?string $photo, ?string $existing = null): ?string
    {
        if (empty($photo) || $photo === 'remove') {
            return null;
        }

        if (str_starts_with($photo, '/uploads/') || str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return $photo;
        }

        if (preg_match('/^data:image\/([a-zA-Z0-9\+\-]+);base64,(.+)$/', $photo, $matches)) {
            $ext = strtolower($matches[1]);
            if ($ext === 'jpeg') $ext = 'jpg';
            if ($ext === 'octet-stream') $ext = 'avif';
            $binary = base64_decode($matches[2]);
            if ($binary !== false) {
                $dir = __DIR__ . '/../../public/uploads/admissions';
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $filename = 'admission_' . uniqid() . '.' . $ext;
                file_put_contents($dir . '/' . $filename, $binary);
                return '/uploads/admissions/' . $filename;
            }
        }

        return $existing;
    }
}
