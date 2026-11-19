<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use DateTime;
use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use PDO;
use Throwable;

/**
 * Controller for the complete Timetabling Module:
 * - Timetable Master & Configurations
 * - Lectures / Periods with Start/End times, Interval, Assembly, Zero Period Cum Fruit Break
 * - Class & Section Allocations
 * - Interactive Timetable Generator & Grid Matrix with clash prevention
 * - Teacher Substitution (absent teacher schedule, free teachers lookup, arrangements)
 * - Reports (Teacher-wise, Group-wise, Substitution History)
 */
class TimetableController
{
    public static function ensureTables(PDO $pdo): void
    {
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS timetables (
                    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                    firm_id           BIGINT UNSIGNED NULL DEFAULT NULL,
                    academic_year_id  BIGINT UNSIGNED NOT NULL,
                    name              VARCHAR(255) NOT NULL,
                    description       TEXT NULL DEFAULT NULL,
                    status            ENUM("active", "inactive") NOT NULL DEFAULT "active",
                    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at        TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_timetables_ay (academic_year_id),
                    INDEX idx_timetables_firm (firm_id),
                    INDEX idx_timetables_status (status),
                    INDEX idx_timetables_deleted_at (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS timetable_lectures (
                    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    timetable_id    BIGINT UNSIGNED NOT NULL,
                    lecture_name    VARCHAR(100) NOT NULL,
                    start_time      TIME NOT NULL,
                    end_time        TIME NOT NULL,
                    is_interval     TINYINT(1) NOT NULL DEFAULT 0,
                    is_assembly     TINYINT(1) NOT NULL DEFAULT 0,
                    is_zero_period  TINYINT(1) NOT NULL DEFAULT 0,
                    sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
                    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at      TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_tt_lectures_tt (timetable_id),
                    INDEX idx_tt_lectures_sort (sort_order),
                    INDEX idx_tt_lectures_flags (is_interval, is_assembly, is_zero_period),
                    INDEX idx_tt_lectures_deleted_at (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS timetable_class_allocations (
                    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    timetable_id       BIGINT UNSIGNED NOT NULL,
                    academic_class_id  BIGINT UNSIGNED NOT NULL,
                    section_id         BIGINT UNSIGNED NOT NULL,
                    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at         TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_tt_alloc_tt (timetable_id),
                    INDEX idx_tt_alloc_class_sec (academic_class_id, section_id),
                    INDEX idx_tt_alloc_deleted_at (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS timetable_slots (
                    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    timetable_id       BIGINT UNSIGNED NOT NULL,
                    academic_class_id  BIGINT UNSIGNED NOT NULL,
                    section_id         BIGINT UNSIGNED NOT NULL,
                    group_id           BIGINT UNSIGNED NULL DEFAULT NULL,
                    lecture_id         BIGINT UNSIGNED NOT NULL,
                    day_of_week        ENUM("monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday") NOT NULL,
                    subject_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                    subject_name       VARCHAR(255) NULL DEFAULT NULL,
                    teacher_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                    room_no            VARCHAR(100) NULL DEFAULT NULL,
                    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at         TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_tt_slots_tt (timetable_id),
                    INDEX idx_tt_slots_class_sec (academic_class_id, section_id),
                    INDEX idx_tt_slots_day_lec (day_of_week, lecture_id),
                    INDEX idx_tt_slots_teacher (teacher_id, day_of_week, lecture_id),
                    INDEX idx_tt_slots_deleted_at (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS timetable_substitutions (
                    id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    timetable_id           BIGINT UNSIGNED NOT NULL,
                    timetable_slot_id      BIGINT UNSIGNED NULL DEFAULT NULL,
                    substitution_date      DATE NOT NULL,
                    day_of_week            ENUM("monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday") NOT NULL,
                    lecture_id             BIGINT UNSIGNED NOT NULL,
                    academic_class_id      BIGINT UNSIGNED NOT NULL,
                    section_id             BIGINT UNSIGNED NOT NULL,
                    group_id               BIGINT UNSIGNED NULL DEFAULT NULL,
                    subject_id             BIGINT UNSIGNED NULL DEFAULT NULL,
                    subject_name           VARCHAR(255) NULL DEFAULT NULL,
                    original_teacher_id    BIGINT UNSIGNED NOT NULL,
                    substitute_teacher_id  BIGINT UNSIGNED NOT NULL,
                    status                 ENUM("assigned", "completed", "cancelled") NOT NULL DEFAULT "assigned",
                    remarks                TEXT NULL DEFAULT NULL,
                    created_by             BIGINT UNSIGNED NULL DEFAULT NULL,
                    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at             TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_tt_sub_date_lec (substitution_date, lecture_id),
                    INDEX idx_tt_sub_orig (original_teacher_id, substitution_date),
                    INDEX idx_tt_sub_new (substitute_teacher_id, substitution_date),
                    INDEX idx_tt_sub_status (status),
                    INDEX idx_tt_sub_deleted_at (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            // Ignore schema creation errors if already present
        }
    }

    // =========================================================================
    // 1. TIMETABLE MASTER CRUD
    // =========================================================================

    public function index(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $academicYearId = $request->query('academic_year_id');
        $firmId = $request->query('firm_id');
        $status = $request->query('status');

        $where = 'WHERE t.deleted_at IS NULL';
        $params = [];

        if ($academicYearId !== null && $academicYearId !== '') {
            $where .= ' AND t.academic_year_id = :ay_id';
            $params['ay_id'] = (int) $academicYearId;
        }

        if ($firmId !== null && $firmId !== '') {
            $where .= ' AND (t.firm_id = :firm_id OR t.firm_id IS NULL)';
            $params['firm_id'] = (int) $firmId;
        }

        if ($status !== null && $status !== '') {
            $where .= ' AND t.status = :status';
            $params['status'] = (string) $status;
        }

        $sql = "SELECT t.*, 
                       ay.name AS session_name,
                       f.name AS firm_name,
                       (SELECT COUNT(*) FROM timetable_lectures l WHERE l.timetable_id = t.id AND l.deleted_at IS NULL) AS lecture_count,
                       (SELECT COUNT(*) FROM timetable_class_allocations a WHERE a.timetable_id = t.id AND a.deleted_at IS NULL) AS allocation_count
                FROM timetables t
                LEFT JOIN academic_years ay ON ay.id = t.academic_year_id
                LEFT JOIN firms f ON f.id = t.firm_id
                {$where}
                ORDER BY t.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $timetables = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::success($timetables);
    }

    public function show(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $routeParams = !empty($params) ? $params : $context;
        $id = (int) ($routeParams['id'] ?? $request->input('id') ?? 0);
        $stmt = $pdo->prepare(
            'SELECT t.*, ay.name AS session_name, f.name AS firm_name
             FROM timetables t
             LEFT JOIN academic_years ay ON ay.id = t.academic_year_id
             LEFT JOIN firms f ON f.id = t.firm_id
             WHERE t.id = :id AND t.deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        $timetable = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$timetable) {
            return Response::error('Timetable not found.', 404);
        }

        // Fetch lectures
        $lstmt = $pdo->prepare(
            'SELECT * FROM timetable_lectures 
             WHERE timetable_id = :id AND deleted_at IS NULL 
             ORDER BY sort_order ASC, start_time ASC'
        );
        $lstmt->execute(['id' => $id]);
        $timetable['lectures'] = $lstmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch allocations
        $astmt = $pdo->prepare(
            'SELECT a.*, c.name AS class_name, s.name AS section_name
             FROM timetable_class_allocations a
             JOIN academic_classes c ON c.id = a.academic_class_id
             JOIN sections s ON s.id = a.section_id
             WHERE a.timetable_id = :id AND a.deleted_at IS NULL
             ORDER BY c.sort_order ASC, c.name ASC, s.name ASC'
        );
        $astmt->execute(['id' => $id]);
        $timetable['allocations'] = $astmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::success($timetable);
    }

    public function store(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $body = $request->all();
        $validator = new Validator($body, [
            'academic_year_id' => 'required|integer',
            'name'             => 'required|string|max:255',
            'firm_id'          => 'nullable|integer',
            'description'      => 'nullable|string',
            'status'           => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $stmt = $pdo->prepare(
            'INSERT INTO timetables (academic_year_id, firm_id, name, description, status)
             VALUES (:academic_year_id, :firm_id, :name, :description, :status)'
        );
        $stmt->execute([
            'academic_year_id' => (int) $body['academic_year_id'],
            'firm_id'          => !empty($body['firm_id']) ? (int) $body['firm_id'] : null,
            'name'             => trim((string) $body['name']),
            'description'      => !empty($body['description']) ? trim((string) $body['description']) : null,
            'status'           => $body['status'] ?? 'active',
        ]);

        $id = (int) $pdo->lastInsertId();
        return Response::success(['id' => $id], 'Timetable created successfully.', 201);
    }

    public function update(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $routeParams = !empty($params) ? $params : $context;
        $id = (int) ($routeParams['id'] ?? $request->input('id') ?? 0);
        $body = $request->all();

        $validator = new Validator($body, [
            'academic_year_id' => 'nullable|integer',
            'name'             => 'required|string|max:255',
            'firm_id'          => 'nullable|integer',
            'description'      => 'nullable|string',
            'status'           => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $stmt = $pdo->prepare(
            'UPDATE timetables 
             SET academic_year_id = COALESCE(:academic_year_id, academic_year_id),
                 firm_id = :firm_id,
                 name = :name,
                 description = :description,
                 status = :status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id'               => $id,
            'academic_year_id' => !empty($body['academic_year_id']) ? (int) $body['academic_year_id'] : null,
            'firm_id'          => !empty($body['firm_id']) ? (int) $body['firm_id'] : null,
            'name'             => trim((string) $body['name']),
            'description'      => !empty($body['description']) ? trim((string) $body['description']) : null,
            'status'           => $body['status'] ?? 'active',
        ]);

        return Response::success(['id' => $id], 'Timetable updated successfully.');
    }

    public function destroy(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $routeParams = !empty($params) ? $params : $context;
        $id = (int) ($routeParams['id'] ?? $request->input('id') ?? 0);
        $stmt = $pdo->prepare(
            'UPDATE timetables SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        return Response::success(null, 'Timetable deleted successfully.');
    }

    // =========================================================================
    // 2. TIMETABLE LECTURES CRUD
    // =========================================================================

    public function lecturesList(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $routeParams = !empty($params) ? $params : $context;
        $timetableId = (int) ($routeParams['id'] ?? $request->input('timetable_id') ?? $request->query('timetable_id') ?? 0);
        $stmt = $pdo->prepare(
            'SELECT * FROM timetable_lectures 
             WHERE timetable_id = :id AND deleted_at IS NULL 
             ORDER BY sort_order ASC, start_time ASC'
        );
        $stmt->execute(['id' => $timetableId]);
        $lectures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::success($lectures);
    }

    public function lectureStore(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $routeParams = !empty($params) ? $params : $context;
        $timetableId = (int) ($routeParams['id'] ?? $request->input('timetable_id') ?? 0);
        if ($timetableId <= 0) {
            return Response::error('Valid Timetable ID is required.', 422);
        }

        $body = $request->all();

        $validator = new Validator($body, [
            'lecture_name'   => 'required|string|max:100',
            'start_time'     => 'required|string',
            'end_time'       => 'required|string',
            'is_interval'    => 'nullable|boolean',
            'is_assembly'    => 'nullable|boolean',
            'is_zero_period' => 'nullable|boolean',
            'sort_order'     => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        // Auto determine sort order if not given
        $sortOrder = $body['sort_order'] ?? null;
        if ($sortOrder === null) {
            $mstmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM timetable_lectures WHERE timetable_id = :id AND deleted_at IS NULL');
            $mstmt->execute(['id' => $timetableId]);
            $sortOrder = (int) $mstmt->fetchColumn();
        }

        $stmt = $pdo->prepare(
            'INSERT INTO timetable_lectures 
             (timetable_id, lecture_name, start_time, end_time, is_interval, is_assembly, is_zero_period, sort_order)
             VALUES (:timetable_id, :lecture_name, :start_time, :end_time, :is_interval, :is_assembly, :is_zero_period, :sort_order)'
        );
        $stmt->execute([
            'timetable_id'   => $timetableId,
            'lecture_name'   => trim((string) $body['lecture_name']),
            'start_time'     => trim((string) $body['start_time']),
            'end_time'       => trim((string) $body['end_time']),
            'is_interval'    => !empty($body['is_interval']) ? 1 : 0,
            'is_assembly'    => !empty($body['is_assembly']) ? 1 : 0,
            'is_zero_period' => !empty($body['is_zero_period']) ? 1 : 0,
            'sort_order'     => (int) $sortOrder,
        ]);

        $id = (int) $pdo->lastInsertId();
        return Response::success(['id' => $id], 'Lecture added successfully.', 201);
    }

    public function lectureUpdate(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $routeParams = !empty($params) ? $params : $context;
        $lectureId = (int) ($routeParams['lectureId'] ?? $routeParams['id'] ?? $request->input('lecture_id') ?? 0);
        if ($lectureId <= 0) {
            return Response::error('Valid Lecture ID is required.', 422);
        }

        $body = $request->all();

        $validator = new Validator($body, [
            'lecture_name'   => 'required|string|max:100',
            'start_time'     => 'required|string',
            'end_time'       => 'required|string',
            'is_interval'    => 'nullable|boolean',
            'is_assembly'    => 'nullable|boolean',
            'is_zero_period' => 'nullable|boolean',
            'sort_order'     => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $stmt = $pdo->prepare(
            'UPDATE timetable_lectures 
             SET lecture_name = :lecture_name,
                 start_time = :start_time,
                 end_time = :end_time,
                 is_interval = :is_interval,
                 is_assembly = :is_assembly,
                 is_zero_period = :is_zero_period,
                 sort_order = COALESCE(:sort_order, sort_order),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id'             => $lectureId,
            'lecture_name'   => trim((string) $body['lecture_name']),
            'start_time'     => trim((string) $body['start_time']),
            'end_time'       => trim((string) $body['end_time']),
            'is_interval'    => !empty($body['is_interval']) ? 1 : 0,
            'is_assembly'    => !empty($body['is_assembly']) ? 1 : 0,
            'is_zero_period' => !empty($body['is_zero_period']) ? 1 : 0,
            'sort_order'     => isset($body['sort_order']) ? (int) $body['sort_order'] : null,
        ]);

        return Response::success(null, 'Lecture updated successfully.');
    }

    public function lectureDestroy(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $routeParams = !empty($params) ? $params : $context;
        $lectureId = (int) ($routeParams['lectureId'] ?? $routeParams['id'] ?? $request->input('lecture_id') ?? 0);
        $stmt = $pdo->prepare(
            'UPDATE timetable_lectures SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $lectureId]);

        return Response::success(null, 'Lecture deleted successfully.');
    }

    public function lecturesReorder(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $body = $request->all();
        $items = $body['items'] ?? []; // [{ id: 1, sort_order: 1 }, ...]

        if (!is_array($items)) {
            return Response::error('Invalid items array.', 422);
        }

        $stmt = $pdo->prepare('UPDATE timetable_lectures SET sort_order = :sort WHERE id = :id');
        foreach ($items as $item) {
            if (!empty($item['id']) && isset($item['sort_order'])) {
                $stmt->execute(['id' => (int) $item['id'], 'sort' => (int) $item['sort_order']]);
            }
        }

        return Response::success(null, 'Lectures reordered successfully.');
    }

    // =========================================================================
    // 3. CLASS / SECTION ALLOCATION
    // =========================================================================

    public function allocationsList(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $routeParams = !empty($params) ? $params : $context;
        $timetableId = (int) ($routeParams['id'] ?? $request->input('timetable_id') ?? $request->query('timetable_id') ?? 0);
        $stmt = $pdo->prepare(
            'SELECT a.*, c.name AS class_name, s.name AS section_name
             FROM timetable_class_allocations a
             JOIN academic_classes c ON c.id = a.academic_class_id
             JOIN sections s ON s.id = a.section_id
             WHERE a.timetable_id = :id AND a.deleted_at IS NULL
             ORDER BY c.sort_order ASC, c.name ASC, s.name ASC'
        );
        $stmt->execute(['id' => $timetableId]);
        $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::success($allocations);
    }

    public function allocationSave(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $routeParams = !empty($params) ? $params : $context;
        $timetableId = (int) ($routeParams['id'] ?? $request->input('timetable_id') ?? 0);
        if ($timetableId <= 0) {
            return Response::error('Valid Timetable ID is required.', 422);
        }

        $body = $request->all();

        // Expects: { allocations: [ { academic_class_id: 1, section_id: 2 }, ... ] }
        $allocations = $body['allocations'] ?? [];
        if (!is_array($allocations)) {
            return Response::error('Invalid allocations payload.', 422);
        }

        $checkStmt = $pdo->prepare(
            'SELECT id FROM timetable_class_allocations 
             WHERE timetable_id = :tt_id AND academic_class_id = :cls_id AND section_id = :sec_id AND deleted_at IS NULL'
        );

        $insertStmt = $pdo->prepare(
            'INSERT INTO timetable_class_allocations (timetable_id, academic_class_id, section_id)
             VALUES (:tt_id, :cls_id, :sec_id)'
        );

        $addedCount = 0;
        foreach ($allocations as $alloc) {
            $classId = (int) ($alloc['academic_class_id'] ?? 0);
            $sectionId = (int) ($alloc['section_id'] ?? 0);
            if ($classId <= 0 || $sectionId <= 0) {
                continue;
            }

            $checkStmt->execute(['tt_id' => $timetableId, 'cls_id' => $classId, 'sec_id' => $sectionId]);
            if (!$checkStmt->fetchColumn()) {
                $insertStmt->execute(['tt_id' => $timetableId, 'cls_id' => $classId, 'sec_id' => $sectionId]);
                $addedCount++;
            }
        }

        return Response::success(['added_count' => $addedCount], "Successfully allocated {$addedCount} class/section(s).");
    }

    public function allocationRemove(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $routeParams = !empty($params) ? $params : $context;
        $allocationId = (int) ($routeParams['allocationId'] ?? $routeParams['id'] ?? $request->input('allocation_id') ?? 0);
        $stmt = $pdo->prepare(
            'UPDATE timetable_class_allocations SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $allocationId]);

        return Response::success(null, 'Allocation removed successfully.');
    }

    // =========================================================================
    // 4. TIMETABLE GENERATOR & GRID MATRIX
    // =========================================================================

    public function gridGet(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $timetableId = (int) $request->query('timetable_id');
        $classId = (int) $request->query('academic_class_id');
        $sectionId = (int) $request->query('section_id');
        $groupId = $request->query('group_id') ? (int) $request->query('group_id') : null;

        if ($timetableId <= 0) {
            return Response::error('Timetable ID is required.', 422);
        }

        // Fetch timetable details
        $tstmt = $pdo->prepare('SELECT * FROM timetables WHERE id = :id AND deleted_at IS NULL');
        $tstmt->execute(['id' => $timetableId]);
        $timetable = $tstmt->fetch(PDO::FETCH_ASSOC);

        if (!$timetable) {
            return Response::error('Timetable not found.', 404);
        }

        // Fetch lectures for this timetable
        $lstmt = $pdo->prepare(
            'SELECT * FROM timetable_lectures 
             WHERE timetable_id = :id AND deleted_at IS NULL 
             ORDER BY sort_order ASC, start_time ASC'
        );
        $lstmt->execute(['id' => $timetableId]);
        $lectures = $lstmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch existing slots
        $slots = [];
        if ($classId > 0 && $sectionId > 0) {
            $swhere = 'WHERE s.timetable_id = :tt_id AND s.academic_class_id = :cls_id AND s.section_id = :sec_id AND s.deleted_at IS NULL';
            $sparams = ['tt_id' => $timetableId, 'cls_id' => $classId, 'sec_id' => $sectionId];

            if ($groupId !== null) {
                $swhere .= ' AND (s.group_id = :grp_id OR s.group_id IS NULL)';
                $sparams['grp_id'] = $groupId;
            }

            $sql = "SELECT s.*, 
                           sub.name AS subject_name_resolved,
                           sub.code AS subject_code,
                           u.name AS teacher_name,
                           u.mobile_no AS teacher_phone,
                           emp.employee_type
                    FROM timetable_slots s
                    LEFT JOIN exam_subjects sub ON sub.id = s.subject_id
                    LEFT JOIN employees emp ON emp.id = s.teacher_id
                    LEFT JOIN users u ON u.id = emp.user_id
                    {$swhere}";

            $sstmt = $pdo->prepare($sql);
            $sstmt->execute($sparams);
            $slots = $sstmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Available teachers (employee_type = 'Teaching')
        $tQuery = $pdo->prepare(
            'SELECT e.id, u.name, u.mobile_no, e.attendance_code, e.employee_type
             FROM employees e
             JOIN users u ON u.id = e.user_id
             WHERE e.deleted_at IS NULL AND u.deleted_at IS NULL
             ORDER BY u.name ASC'
        );
        $tQuery->execute();
        $teachers = $tQuery->fetchAll(PDO::FETCH_ASSOC);

        // Available subjects
        $subQuery = $pdo->prepare(
            'SELECT id, name, short_name, code, type FROM exam_subjects WHERE deleted_at IS NULL AND status = "active" ORDER BY sort_order ASC, name ASC'
        );
        $subQuery->execute();
        $subjects = $subQuery->fetchAll(PDO::FETCH_ASSOC);

        return Response::success([
            'timetable' => $timetable,
            'lectures'  => $lectures,
            'slots'     => $slots,
            'teachers'  => $teachers,
            'subjects'  => $subjects,
        ]);
    }

    public function gridSave(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $body = $request->all();
        $timetableId = (int) ($body['timetable_id'] ?? 0);
        $classId = (int) ($body['academic_class_id'] ?? 0);
        $sectionId = (int) ($body['section_id'] ?? 0);
        $groupId = !empty($body['group_id']) ? (int) $body['group_id'] : null;
        $slots = $body['slots'] ?? []; // Array of slot items
        $allowClash = !empty($body['allow_clash']);

        if ($timetableId <= 0 || $classId <= 0 || $sectionId <= 0) {
            return Response::error('Timetable, Class, and Section are required.', 422);
        }

        // Validate teacher clashes across other classes
        $clashes = [];
        $clashCheckStmt = $pdo->prepare(
            'SELECT s.*, c.name AS class_name, sec.name AS section_name, lec.lecture_name, u.name AS teacher_name
             FROM timetable_slots s
             JOIN academic_classes c ON c.id = s.academic_class_id
             JOIN sections sec ON sec.id = s.section_id
             JOIN timetable_lectures lec ON lec.id = s.lecture_id
             JOIN employees emp ON emp.id = s.teacher_id
             JOIN users u ON u.id = emp.user_id
             WHERE s.teacher_id = :teacher_id
               AND s.day_of_week = :day_of_week
               AND s.lecture_id = :lecture_id
               AND (s.academic_class_id != :cls_id OR s.section_id != :sec_id)
               AND s.deleted_at IS NULL'
        );

        foreach ($slots as $slot) {
            $teacherId = !empty($slot['teacher_id']) ? (int) $slot['teacher_id'] : null;
            $dayOfWeek = strtolower((string) ($slot['day_of_week'] ?? ''));
            $lectureId = !empty($slot['lecture_id']) ? (int) $slot['lecture_id'] : 0;

            if ($teacherId && $dayOfWeek && $lectureId) {
                $clashCheckStmt->execute([
                    'teacher_id'  => $teacherId,
                    'day_of_week' => $dayOfWeek,
                    'lecture_id'  => $lectureId,
                    'cls_id'      => $classId,
                    'sec_id'      => $sectionId,
                ]);
                $clash = $clashCheckStmt->fetch(PDO::FETCH_ASSOC);
                if ($clash) {
                    $clashes[] = [
                        'teacher_name' => $clash['teacher_name'],
                        'day_of_week'  => ucfirst($dayOfWeek),
                        'lecture_name' => $clash['lecture_name'],
                        'busy_in'      => "{$clash['class_name']} - {$clash['section_name']}",
                    ];
                }
            }
        }

        if (!empty($clashes) && !$allowClash) {
            return Response::error('Teacher schedule clash detected.', 409, ['clashes' => $clashes]);
        }

        // Transaction to overwrite/save slots for this class and section
        $pdo->beginTransaction();
        try {
            // Soft-delete existing slots for this class-section & group
            $dsql = 'UPDATE timetable_slots 
                     SET deleted_at = CURRENT_TIMESTAMP 
                     WHERE timetable_id = :tt_id AND academic_class_id = :cls_id AND section_id = :sec_id';
            $dparams = ['tt_id' => $timetableId, 'cls_id' => $classId, 'sec_id' => $sectionId];

            if ($groupId !== null) {
                $dsql .= ' AND group_id = :grp_id';
                $dparams['grp_id'] = $groupId;
            } else {
                $dsql .= ' AND group_id IS NULL';
            }
            $pdo->prepare($dsql)->execute($dparams);

            // Insert new slots
            $insertStmt = $pdo->prepare(
                'INSERT INTO timetable_slots 
                 (timetable_id, academic_class_id, section_id, group_id, lecture_id, day_of_week, subject_id, subject_name, teacher_id, room_no)
                 VALUES (:tt_id, :cls_id, :sec_id, :grp_id, :lec_id, :day, :sub_id, :sub_name, :t_id, :room)'
            );

            $savedCount = 0;
            foreach ($slots as $slot) {
                $lectureId = !empty($slot['lecture_id']) ? (int) $slot['lecture_id'] : 0;
                $dayOfWeek = strtolower((string) ($slot['day_of_week'] ?? ''));
                if ($lectureId <= 0 || empty($dayOfWeek)) {
                    continue;
                }

                $subjectId = !empty($slot['subject_id']) ? (int) $slot['subject_id'] : null;
                $subjectName = !empty($slot['subject_name']) ? trim((string) $slot['subject_name']) : null;
                $teacherId = !empty($slot['teacher_id']) ? (int) $slot['teacher_id'] : null;
                $roomNo = !empty($slot['room_no']) ? trim((string) $slot['room_no']) : null;

                // Only insert if subject or teacher is specified
                if ($subjectId || $subjectName || $teacherId || $roomNo) {
                    $insertStmt->execute([
                        'tt_id'    => $timetableId,
                        'cls_id'   => $classId,
                        'sec_id'   => $sectionId,
                        'grp_id'   => $groupId,
                        'lec_id'   => $lectureId,
                        'day'      => $dayOfWeek,
                        'sub_id'   => $subjectId,
                        'sub_name' => $subjectName,
                        't_id'     => $teacherId,
                        'room'     => $roomNo,
                    ]);
                    $savedCount++;
                }
            }

            $pdo->commit();
            return Response::success(['saved_count' => $savedCount], "Timetable saved successfully ({$savedCount} periods configured).");
        } catch (Throwable $e) {
            $pdo->rollBack();
            return Response::error('Failed to save timetable slots: ' . $e->getMessage(), 500);
        }
    }

    public function checkClashes(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $teacherId = (int) $request->query('teacher_id');
        $dayOfWeek = strtolower((string) $request->query('day_of_week'));
        $lectureId = (int) $request->query('lecture_id');
        $classId = (int) $request->query('academic_class_id');
        $sectionId = (int) $request->query('section_id');

        if ($teacherId <= 0 || empty($dayOfWeek) || $lectureId <= 0) {
            return Response::success(['is_busy' => false]);
        }

        $stmt = $pdo->prepare(
            'SELECT s.*, c.name AS class_name, sec.name AS section_name, lec.lecture_name, u.name AS teacher_name
             FROM timetable_slots s
             JOIN academic_classes c ON c.id = s.academic_class_id
             JOIN sections sec ON sec.id = s.section_id
             JOIN timetable_lectures lec ON lec.id = s.lecture_id
             JOIN employees emp ON emp.id = s.teacher_id
             JOIN users u ON u.id = emp.user_id
             WHERE s.teacher_id = :teacher_id
               AND s.day_of_week = :day_of_week
               AND s.lecture_id = :lecture_id
               AND (s.academic_class_id != :cls_id OR s.section_id != :sec_id)
               AND s.deleted_at IS NULL'
        );
        $stmt->execute([
            'teacher_id'  => $teacherId,
            'day_of_week' => $dayOfWeek,
            'lecture_id'  => $lectureId,
            'cls_id'      => $classId,
            'sec_id'      => $sectionId,
        ]);
        $clash = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($clash) {
            return Response::success([
                'is_busy' => true,
                'clash'   => [
                    'teacher_name' => $clash['teacher_name'],
                    'day_of_week'  => ucfirst($dayOfWeek),
                    'lecture_name' => $clash['lecture_name'],
                    'busy_in'      => "{$clash['class_name']} - {$clash['section_name']}",
                ],
            ]);
        }

        return Response::success(['is_busy' => false]);
    }

    // =========================================================================
    // 5. TEACHER SUBSTITUTION
    // =========================================================================

    public function teacherSchedule(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $teacherId = (int) $request->query('teacher_id');
        $dateFrom = (string) $request->query('date_from');
        $dateTo = (string) $request->query('date_to');

        if ($teacherId <= 0 || empty($dateFrom) || empty($dateTo)) {
            return Response::error('Teacher ID, Date From, and Date To are required.', 422);
        }

        $startDate = new DateTime($dateFrom);
        $endDate = new DateTime($dateTo);

        if ($startDate > $endDate) {
            return Response::error('Date From cannot be after Date To.', 422);
        }

        // Get teacher profile details
        $tstmt = $pdo->prepare(
            'SELECT e.id, u.name, u.mobile_no, e.attendance_code, e.employee_type
             FROM employees e
             JOIN users u ON u.id = e.user_id
             WHERE e.id = :id AND e.deleted_at IS NULL'
        );
        $tstmt->execute(['id' => $teacherId]);
        $teacher = $tstmt->fetch(PDO::FETCH_ASSOC);

        if (!$teacher) {
            return Response::error('Teacher not found.', 404);
        }

        // Prepared statement to fetch teacher scheduled slots for a given day of the week
        $slotStmt = $pdo->prepare(
            'SELECT s.*, 
                    c.name AS class_name, 
                    sec.name AS section_name, 
                    g.name AS group_name,
                    lec.lecture_name, 
                    lec.start_time, 
                    lec.end_time, 
                    lec.is_interval, 
                    lec.is_assembly, 
                    lec.is_zero_period,
                    sub.name AS subject_name_resolved,
                    sub.code AS subject_code
             FROM timetable_slots s
             JOIN academic_classes c ON c.id = s.academic_class_id
             JOIN sections sec ON sec.id = s.section_id
             LEFT JOIN `groups` g ON g.id = s.group_id
             JOIN timetable_lectures lec ON lec.id = s.lecture_id
             LEFT JOIN exam_subjects sub ON sub.id = s.subject_id
             WHERE s.teacher_id = :teacher_id
               AND s.day_of_week = :day_of_week
               AND s.deleted_at IS NULL
             ORDER BY lec.sort_order ASC, lec.start_time ASC'
        );

        // Prepared statement to check existing substitutions on a specific date and slot/lecture
        $substStmt = $pdo->prepare(
            'SELECT sub.*, 
                    u.name AS substitute_teacher_name, 
                    u.mobile_no AS substitute_teacher_phone,
                    emp.employee_type AS substitute_employee_type
             FROM timetable_substitutions sub
             JOIN employees emp ON emp.id = sub.substitute_teacher_id
             JOIN users u ON u.id = emp.user_id
             WHERE sub.original_teacher_id = :teacher_id
               AND sub.substitution_date = :sub_date
               AND sub.lecture_id = :lec_id
               AND sub.academic_class_id = :cls_id
               AND sub.section_id = :sec_id
               AND sub.deleted_at IS NULL
               AND sub.status != "cancelled"
             ORDER BY sub.id DESC LIMIT 1'
        );

        $days = [];
        $curr = clone $startDate;

        while ($curr <= $endDate) {
            $dateStr = $curr->format('Y-m-d');
            $dayOfWeek = strtolower($curr->format('l')); // 'monday', 'tuesday', etc.
            $dayDisplay = $curr->format('l');

            // Find scheduled slots for this day
            $slotStmt->execute([
                'teacher_id'  => $teacherId,
                'day_of_week' => $dayOfWeek,
            ]);
            $rawSlots = $slotStmt->fetchAll(PDO::FETCH_ASSOC);

            $slots = [];
            foreach ($rawSlots as $slot) {
                // Check if already substituted
                $substStmt->execute([
                    'teacher_id' => $teacherId,
                    'sub_date'   => $dateStr,
                    'lec_id'     => $slot['lecture_id'],
                    'cls_id'     => $slot['academic_class_id'],
                    'sec_id'     => $slot['section_id'],
                ]);
                $substitution = $substStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                $slots[] = [
                    'slot_id'               => (int) $slot['id'],
                    'timetable_id'          => (int) $slot['timetable_id'],
                    'lecture_id'            => (int) $slot['lecture_id'],
                    'lecture_name'          => $slot['lecture_name'],
                    'start_time'            => $slot['start_time'],
                    'end_time'              => $slot['end_time'],
                    'is_interval'           => (bool) $slot['is_interval'],
                    'is_assembly'           => (bool) $slot['is_assembly'],
                    'is_zero_period'        => (bool) $slot['is_zero_period'],
                    'academic_class_id'     => (int) $slot['academic_class_id'],
                    'class_name'            => $slot['class_name'],
                    'section_id'            => (int) $slot['section_id'],
                    'section_name'          => $slot['section_name'],
                    'group_id'              => $slot['group_id'] ? (int) $slot['group_id'] : null,
                    'group_name'            => $slot['group_name'],
                    'subject_id'            => $slot['subject_id'] ? (int) $slot['subject_id'] : null,
                    'subject_name'          => $slot['subject_name_resolved'] ?: $slot['subject_name'] ?: 'General Lecture',
                    'room_no'               => $slot['room_no'],
                    'is_substituted'        => $substitution !== null,
                    'substitution'          => $substitution,
                ];
            }

            $days[] = [
                'date'        => $dateStr,
                'day_of_week' => $dayOfWeek,
                'day_display' => $dayDisplay,
                'slots'       => $slots,
                'slot_count'  => count($slots),
            ];

            $curr->modify('+1 day');
        }

        return Response::success([
            'teacher' => $teacher,
            'days'    => $days,
        ]);
    }

    public function freeTeachers(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $date = (string) $request->query('date');
        $dayOfWeek = strtolower((string) $request->query('day_of_week'));
        $lectureId = (int) $request->query('lecture_id');
        $excludeTeacherId = (int) $request->query('exclude_teacher_id');

        if (empty($date) || empty($dayOfWeek) || $lectureId <= 0) {
            return Response::error('Date, Day of Week, and Lecture ID are required.', 422);
        }

        // 1. All teaching employees
        $sql = "SELECT e.id AS teacher_id, 
                       u.name, 
                       u.mobile_no, 
                       e.attendance_code, 
                       e.employee_type,
                       (SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id LIMIT 1) AS designation
                FROM employees e
                JOIN users u ON u.id = e.user_id
                WHERE e.deleted_at IS NULL 
                  AND u.deleted_at IS NULL
                  AND e.id != :exclude_id
                  -- Condition 1: Not scheduled in any regular timetable slot on this day_of_week & lecture
                  AND e.id NOT IN (
                      SELECT DISTINCT s.teacher_id 
                      FROM timetable_slots s 
                      WHERE s.day_of_week = :day_of_week 
                        AND s.lecture_id = :lec_id 
                        AND s.deleted_at IS NULL
                        AND s.teacher_id IS NOT NULL
                  )
                  -- Condition 2: Not already assigned as substitute on this date & lecture
                  AND e.id NOT IN (
                      SELECT DISTINCT sub.substitute_teacher_id 
                      FROM timetable_substitutions sub 
                      WHERE sub.substitution_date = :sub_date 
                        AND sub.lecture_id = :lec_id2 
                        AND sub.deleted_at IS NULL 
                        AND sub.status != 'cancelled'
                  )
                ORDER BY u.name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'exclude_id'  => $excludeTeacherId,
            'day_of_week' => $dayOfWeek,
            'lec_id'      => $lectureId,
            'sub_date'    => $date,
            'lec_id2'     => $lectureId,
        ]);
        $freeTeachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Also check lecture details for display
        $lstmt = $pdo->prepare('SELECT * FROM timetable_lectures WHERE id = :id');
        $lstmt->execute(['id' => $lectureId]);
        $lecture = $lstmt->fetch(PDO::FETCH_ASSOC);

        return Response::success([
            'date'          => $date,
            'day_of_week'   => $dayOfWeek,
            'lecture'       => $lecture,
            'free_teachers' => $freeTeachers,
            'count'         => count($freeTeachers),
        ]);
    }

    public function substitutionSave(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $body = $request->all();
        $validator = new Validator($body, [
            'timetable_id'          => 'required|integer',
            'substitution_date'     => 'required|date',
            'day_of_week'           => 'required|string',
            'lecture_id'            => 'required|integer',
            'academic_class_id'     => 'required|integer',
            'section_id'            => 'required|integer',
            'original_teacher_id'   => 'required|integer',
            'substitute_teacher_id' => 'required|integer',
            'remarks'               => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $timetableId = (int) $body['timetable_id'];
        $slotId = !empty($body['timetable_slot_id']) ? (int) $body['timetable_slot_id'] : null;
        $date = (string) $body['substitution_date'];
        $dayOfWeek = strtolower((string) $body['day_of_week']);
        $lectureId = (int) $body['lecture_id'];
        $classId = (int) $body['academic_class_id'];
        $sectionId = (int) $body['section_id'];
        $groupId = !empty($body['group_id']) ? (int) $body['group_id'] : null;
        $subjectId = !empty($body['subject_id']) ? (int) $body['subject_id'] : null;
        $subjectName = !empty($body['subject_name']) ? trim((string) $body['subject_name']) : null;
        $origTeacherId = (int) $body['original_teacher_id'];
        $subTeacherId = (int) $body['substitute_teacher_id'];
        $remarks = !empty($body['remarks']) ? trim((string) $body['remarks']) : null;

        // Cancel any existing active substitution for this slot/date first
        $cstmt = $pdo->prepare(
            'UPDATE timetable_substitutions 
             SET status = "cancelled", updated_at = CURRENT_TIMESTAMP
             WHERE substitution_date = :sub_date
               AND lecture_id = :lec_id
               AND academic_class_id = :cls_id
               AND section_id = :sec_id
               AND deleted_at IS NULL
               AND status != "cancelled"'
        );
        $cstmt->execute([
            'sub_date' => $date,
            'lec_id'   => $lectureId,
            'cls_id'   => $classId,
            'sec_id'   => $sectionId,
        ]);

        // Insert new substitution
        $stmt = $pdo->prepare(
            'INSERT INTO timetable_substitutions
             (timetable_id, timetable_slot_id, substitution_date, day_of_week, lecture_id, academic_class_id, section_id, group_id, subject_id, subject_name, original_teacher_id, substitute_teacher_id, status, remarks)
             VALUES (:tt_id, :slot_id, :sub_date, :day, :lec_id, :cls_id, :sec_id, :grp_id, :sub_id, :sub_name, :orig_id, :sub_id2, "assigned", :remarks)'
        );
        $stmt->execute([
            'tt_id'    => $timetableId,
            'slot_id'  => $slotId,
            'sub_date' => $date,
            'day'      => $dayOfWeek,
            'lec_id'   => $lectureId,
            'cls_id'   => $classId,
            'sec_id'   => $sectionId,
            'grp_id'   => $groupId,
            'sub_id'   => $subjectId,
            'sub_name' => $subjectName,
            'orig_id'  => $origTeacherId,
            'sub_id2'  => $subTeacherId,
            'remarks'  => $remarks,
        ]);

        $id = (int) $pdo->lastInsertId();

        // Fetch substitute teacher name for quick UI response
        $nstmt = $pdo->prepare('SELECT u.name FROM employees e JOIN users u ON u.id = e.user_id WHERE e.id = :id');
        $nstmt->execute(['id' => $subTeacherId]);
        $subName = $nstmt->fetchColumn() ?: 'Assigned Teacher';

        return Response::success([
            'substitution_id'         => $id,
            'substitute_teacher_name' => $subName,
        ], "Substituted with {$subName} successfully.", 201);
    }

    public function substitutionCancel(Request $request, array $context = [], array $params = []): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $routeParams = !empty($params) ? $params : $context;
        $id = (int) ($routeParams['id'] ?? $request->input('id') ?? 0);
        $stmt = $pdo->prepare(
            'UPDATE timetable_substitutions 
             SET status = "cancelled", deleted_at = CURRENT_TIMESTAMP 
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        return Response::success(null, 'Substitution cancelled successfully.');
    }

    // =========================================================================
    // 6. TIMETABLE REPORTS
    // =========================================================================

    public function reportTeacherWise(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $teacherId = (int) $request->query('teacher_id');
        $timetableId = $request->query('timetable_id') ? (int) $request->query('timetable_id') : null;

        if ($teacherId <= 0) {
            return Response::error('Please select a teacher.', 422);
        }

        // Fetch teacher info
        $tstmt = $pdo->prepare(
            'SELECT e.id, u.name, u.mobile_no, e.attendance_code, e.employee_type,
                    (SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id LIMIT 1) AS designation
             FROM employees e
             JOIN users u ON u.id = e.user_id
             WHERE e.id = :id AND e.deleted_at IS NULL'
        );
        $tstmt->execute(['id' => $teacherId]);
        $teacher = $tstmt->fetch(PDO::FETCH_ASSOC);

        if (!$teacher) {
            return Response::error('Teacher not found.', 404);
        }

        // Fetch lectures from either the specified timetable or the default active timetable
        $lecSql = 'SELECT * FROM timetable_lectures WHERE deleted_at IS NULL';
        $lecParams = [];
        if ($timetableId) {
            $lecSql .= ' AND timetable_id = :tt_id';
            $lecParams['tt_id'] = $timetableId;
        }
        $lecSql .= ' ORDER BY sort_order ASC, start_time ASC';
        $lstmt = $pdo->prepare($lecSql);
        $lstmt->execute($lecParams);
        $lectures = $lstmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all assigned slots for this teacher
        $slotSql = 'SELECT s.*, 
                           c.name AS class_name, 
                           sec.name AS section_name, 
                           g.name AS group_name,
                           lec.lecture_name, 
                           lec.start_time, 
                           lec.end_time,
                           sub.name AS subject_name_resolved,
                           sub.code AS subject_code
                    FROM timetable_slots s
                    JOIN academic_classes c ON c.id = s.academic_class_id
                    JOIN sections sec ON sec.id = s.section_id
                    LEFT JOIN `groups` g ON g.id = s.group_id
                    JOIN timetable_lectures lec ON lec.id = s.lecture_id
                    LEFT JOIN exam_subjects sub ON sub.id = s.subject_id
                    WHERE s.teacher_id = :teacher_id AND s.deleted_at IS NULL';
        $slotParams = ['teacher_id' => $teacherId];

        if ($timetableId) {
            $slotSql .= ' AND s.timetable_id = :tt_id';
            $slotParams['tt_id'] = $timetableId;
        }

        $sstmt = $pdo->prepare($slotSql);
        $sstmt->execute($slotParams);
        $slots = $sstmt->fetchAll(PDO::FETCH_ASSOC);

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $matrix = [];
        $totalPeriods = count($slots);

        foreach ($slots as $slot) {
            $matrix[$slot['day_of_week']][$slot['lecture_id']] = [
                'class_name'   => $slot['class_name'],
                'section_name' => $slot['section_name'],
                'group_name'   => $slot['group_name'],
                'subject_name' => $slot['subject_name_resolved'] ?: $slot['subject_name'],
                'room_no'      => $slot['room_no'],
            ];
        }

        return Response::success([
            'teacher'       => $teacher,
            'lectures'      => $lectures,
            'days'          => $days,
            'matrix'        => $matrix,
            'total_periods' => $totalPeriods,
        ]);
    }

    public function reportGroupWise(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $classId = (int) $request->query('academic_class_id');
        $sectionId = (int) $request->query('section_id');
        $groupId = $request->query('group_id') ? (int) $request->query('group_id') : null;
        $timetableId = $request->query('timetable_id') ? (int) $request->query('timetable_id') : null;

        if ($classId <= 0 || $sectionId <= 0) {
            return Response::error('Class and Section are required.', 422);
        }

        // Fetch class & section info
        $cstmt = $pdo->prepare('SELECT name FROM academic_classes WHERE id = :id');
        $cstmt->execute(['id' => $classId]);
        $className = $cstmt->fetchColumn() ?: '';

        $sstmt = $pdo->prepare('SELECT name FROM sections WHERE id = :id');
        $sstmt->execute(['id' => $sectionId]);
        $sectionName = $sstmt->fetchColumn() ?: '';

        $groupName = '';
        if ($groupId) {
            $gstmt = $pdo->prepare('SELECT name FROM `groups` WHERE id = :id');
            $gstmt->execute(['id' => $groupId]);
            $groupName = $gstmt->fetchColumn() ?: '';
        }

        // Fetch timetable or allocated timetable
        if (!$timetableId) {
            $astmt = $pdo->prepare(
                'SELECT timetable_id FROM timetable_class_allocations 
                 WHERE academic_class_id = :cls_id AND section_id = :sec_id AND deleted_at IS NULL LIMIT 1'
            );
            $astmt->execute(['cls_id' => $classId, 'sec_id' => $sectionId]);
            $timetableId = (int) $astmt->fetchColumn();
        }

        // Fetch lectures
        $lecSql = 'SELECT * FROM timetable_lectures WHERE deleted_at IS NULL';
        $lecParams = [];
        if ($timetableId) {
            $lecSql .= ' AND timetable_id = :tt_id';
            $lecParams['tt_id'] = $timetableId;
        }
        $lecSql .= ' ORDER BY sort_order ASC, start_time ASC';
        $lstmt = $pdo->prepare($lecSql);
        $lstmt->execute($lecParams);
        $lectures = $lstmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch slots
        $slotSql = 'SELECT s.*, 
                           u.name AS teacher_name,
                           sub.name AS subject_name_resolved,
                           sub.code AS subject_code
                    FROM timetable_slots s
                    LEFT JOIN employees emp ON emp.id = s.teacher_id
                    LEFT JOIN users u ON u.id = emp.user_id
                    LEFT JOIN exam_subjects sub ON sub.id = s.subject_id
                    WHERE s.academic_class_id = :cls_id AND s.section_id = :sec_id AND s.deleted_at IS NULL';
        $slotParams = ['cls_id' => $classId, 'sec_id' => $sectionId];

        if ($groupId !== null) {
            $slotSql .= ' AND (s.group_id = :grp_id OR s.group_id IS NULL)';
            $slotParams['grp_id'] = $groupId;
        }

        $sstmt = $pdo->prepare($slotSql);
        $sstmt->execute($slotParams);
        $slots = $sstmt->fetchAll(PDO::FETCH_ASSOC);

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $matrix = [];
        foreach ($slots as $slot) {
            $matrix[$slot['day_of_week']][$slot['lecture_id']] = [
                'subject_name' => $slot['subject_name_resolved'] ?: $slot['subject_name'],
                'teacher_name' => $slot['teacher_name'],
                'room_no'      => $slot['room_no'],
            ];
        }

        return Response::success([
            'class_name'   => $className,
            'section_name' => $sectionName,
            'group_name'   => $groupName,
            'lectures'     => $lectures,
            'days'         => $days,
            'matrix'       => $matrix,
        ]);
    }

    public function reportSubstitution(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $origTeacherId = $request->query('original_teacher_id');
        $subTeacherId = $request->query('substitute_teacher_id');
        $status = $request->query('status');

        $where = 'WHERE sub.deleted_at IS NULL';
        $params = [];

        if (!empty($dateFrom)) {
            $where .= ' AND sub.substitution_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $where .= ' AND sub.substitution_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        if (!empty($origTeacherId)) {
            $where .= ' AND sub.original_teacher_id = :orig_id';
            $params['orig_id'] = (int) $origTeacherId;
        }

        if (!empty($subTeacherId)) {
            $where .= ' AND sub.substitute_teacher_id = :sub_id';
            $params['sub_id'] = (int) $subTeacherId;
        }

        if (!empty($status)) {
            $where .= ' AND sub.status = :status';
            $params['status'] = $status;
        }

        $sql = "SELECT sub.*, 
                       lec.lecture_name,
                       lec.start_time,
                       lec.end_time,
                       c.name AS class_name,
                       sec.name AS section_name,
                       g.name AS group_name,
                       es.name AS subject_name_resolved,
                       u1.name AS original_teacher_name,
                       u1.mobile_no AS original_teacher_phone,
                       u2.name AS substitute_teacher_name,
                       u2.mobile_no AS substitute_teacher_phone
                FROM timetable_substitutions sub
                JOIN timetable_lectures lec ON lec.id = sub.lecture_id
                JOIN academic_classes c ON c.id = sub.academic_class_id
                JOIN sections sec ON sec.id = sub.section_id
                LEFT JOIN `groups` g ON g.id = sub.group_id
                LEFT JOIN exam_subjects es ON es.id = sub.subject_id
                JOIN employees e1 ON e1.id = sub.original_teacher_id
                JOIN users u1 ON u1.id = e1.user_id
                JOIN employees e2 ON e2.id = sub.substitute_teacher_id
                JOIN users u2 ON u2.id = e2.user_id
                {$where}
                ORDER BY sub.substitution_date DESC, lec.sort_order ASC, sub.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::success([
            'records' => $records,
            'total'   => count($records),
        ]);
    }
}
