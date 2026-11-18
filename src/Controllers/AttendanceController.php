<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Throwable;

/**
 * Attendance Controller for both Students and Employees.
 *
 * Uses configured `attendance_legends` (filtered by `legend_for`: student / employee / both)
 * for daily marking, batch saving, and rich Summary + Monthly Matrix attendance reports.
 */
class AttendanceController
{
    /**
     * Ensure attendance tables exist (self-healing for deployments without CLI migrate).
     */
    public static function ensureTables(\PDO $pdo): void
    {
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS student_attendances (
                    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id            BIGINT UNSIGNED NULL DEFAULT NULL,
                    firm_id              BIGINT UNSIGNED NULL DEFAULT NULL,
                    academic_year_id     BIGINT UNSIGNED NULL DEFAULT NULL,
                    section_id           BIGINT UNSIGNED NULL DEFAULT NULL,
                    student_id           BIGINT UNSIGNED NOT NULL,
                    attendance_date      DATE NOT NULL,
                    attendance_legend_id BIGINT UNSIGNED NOT NULL,
                    remarks              VARCHAR(500) NULL DEFAULT NULL,
                    marked_by            BIGINT UNSIGNED NULL DEFAULT NULL,
                    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at           TIMESTAMP NULL DEFAULT NULL,
                    UNIQUE KEY uq_student_attendance_date (student_id, attendance_date),
                    INDEX idx_stu_att_date (attendance_date),
                    INDEX idx_stu_att_section_date (section_id, attendance_date),
                    INDEX idx_stu_att_ay (academic_year_id),
                    INDEX idx_stu_att_firm (firm_id),
                    INDEX idx_stu_att_legend (attendance_legend_id),
                    INDEX idx_stu_att_deleted (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS employee_attendances (
                    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id            BIGINT UNSIGNED NULL DEFAULT NULL,
                    firm_id              BIGINT UNSIGNED NULL DEFAULT NULL,
                    academic_year_id     BIGINT UNSIGNED NULL DEFAULT NULL,
                    employee_id          BIGINT UNSIGNED NOT NULL,
                    attendance_date      DATE NOT NULL,
                    attendance_legend_id BIGINT UNSIGNED NOT NULL,
                    in_time              TIME NULL DEFAULT NULL,
                    out_time             TIME NULL DEFAULT NULL,
                    remarks              VARCHAR(500) NULL DEFAULT NULL,
                    marked_by            BIGINT UNSIGNED NULL DEFAULT NULL,
                    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at           TIMESTAMP NULL DEFAULT NULL,
                    UNIQUE KEY uq_employee_attendance_date (employee_id, attendance_date),
                    INDEX idx_emp_att_date (attendance_date),
                    INDEX idx_emp_att_ay (academic_year_id),
                    INDEX idx_emp_att_firm (firm_id),
                    INDEX idx_emp_att_legend (attendance_legend_id),
                    INDEX idx_emp_att_deleted (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            // Best-effort self-healing; ignore if tables exist or DDL restricted.
        }
    }

    /**
     * Fetch active attendance legends for 'student' or 'employee'.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchLegends(\PDO $pdo, string $for): array
    {
        $stmt = $pdo->prepare(
            "SELECT id, name, short_name, treat_as, total_leaves, legend_for
             FROM attendance_legends
             WHERE deleted_at IS NULL
               AND legend_for IN (:for_type, 'both')
             ORDER BY FIELD(treat_as, 'present', 'absent'), id ASC"
        );
        $stmt->execute(['for_type' => $for]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static function (array $r): array {
            return [
                'id'           => (int) $r['id'],
                'name'         => (string) $r['name'],
                'short_name'   => (string) $r['short_name'],
                'treat_as'     => (string) $r['treat_as'],
                'total_leaves' => (int) ($r['total_leaves'] ?? 0),
                'legend_for'   => (string) ($r['legend_for'] ?? 'both'),
            ];
        }, $rows);
    }

    /**
     * Validate YYYY-MM-DD date string.
     */
    protected function normalizeDate(?string $raw, bool $defaultToday = true): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return $defaultToday ? date('Y-m-d') : null;
        }
        $clean = trim($raw);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean)) {
            return null;
        }
        return $clean;
    }

    /**
     * Format attendance string like "76 / 125 (60.80%)".
     */
    public static function formatAttendance(int $present, int $total): string
    {
        if ($total <= 0) {
            return '';
        }
        $pct = round(($present / $total) * 100, 2);
        return sprintf('%d / %d (%.2f%%)', $present, $total, $pct);
    }

    // =========================================================================
    // STUDENT ATTENDANCE
    // =========================================================================

    /**
     * GET /api/student-attendance/sheet
     * Query: section_id (required), date (YYYY-MM-DD, defaults today), academic_year_id, firm_id
     */
    public function studentSheet(Request $request): Response
    {
        $sectionId = (int) $request->query('section_id');
        if (!$sectionId) {
            return Response::error('section_id is required.', 422);
        }

        $date = $this->normalizeDate($request->query('date'), true);
        if (!$date) {
            return Response::error('Invalid date format. Expected YYYY-MM-DD.', 422);
        }

        $pdo = Database::pdo();
        self::ensureTables($pdo);

        // 1. Section & Class info
        $secStmt = $pdo->prepare(
            "SELECT s.id, s.name AS section_name, s.class_id, s.academic_year_id, ac.name AS class_name
             FROM sections s
             LEFT JOIN academic_classes ac ON ac.id = s.class_id
             WHERE s.id = :id AND s.deleted_at IS NULL
             LIMIT 1"
        );
        $secStmt->execute(['id' => $sectionId]);
        $section = $secStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$section) {
            return Response::error('Section not found.', 404);
        }

        // 2. Active student legends
        $legends = $this->fetchLegends($pdo, 'student');

        // 3. Students roster with existing attendance for the requested date
        $bindings = [
            'section_id' => $sectionId,
            'att_date'   => $date,
        ];
        $extraWhere = '';
        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $bindings['firm_id'] = (int) $firmId;
            $extraWhere .= ' AND (u.firm_id = :firm_id OR u.firm_id IS NULL)';
        }

        $sql = "SELECT s.id AS student_id,
                       u.id AS user_id,
                       u.first_name,
                       u.middle_name,
                       u.last_name,
                       u.gender,
                       u.mobile_no,
                       s.enrollment_number,
                       s.scholar_number,
                       s.roll_number,
                       s.photo,
                       s.father_mobile_no,
                       COALESCE(
                           NULLIF(reg.father_name, ''),
                           (SELECT cfv.value
                            FROM custom_field_values cfv
                            INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
                            WHERE cfv.user_id = u.id
                              AND LOWER(TRIM(cf.name)) IN ('father name', 'fathers name', 'father_name')
                              AND cf.deleted_at IS NULL
                            LIMIT 1)
                       ) AS father_name,
                       sa.id AS attendance_id,
                       sa.attendance_legend_id,
                       sa.remarks,
                       al.name AS legend_name,
                       al.short_name AS legend_short_name,
                       al.treat_as
                FROM students s
                INNER JOIN users u ON u.id = s.user_id AND u.deleted_at IS NULL
                LEFT JOIN registrations reg ON reg.student_id = s.id
                LEFT JOIN student_attendances sa
                       ON sa.student_id = s.id
                      AND sa.attendance_date = :att_date
                      AND sa.deleted_at IS NULL
                LEFT JOIN attendance_legends al ON al.id = sa.attendance_legend_id
                WHERE s.section_id = :section_id
                  AND s.deleted_at IS NULL
                  AND (u.is_active = 'y' OR sa.id IS NOT NULL)
                  {$extraWhere}
                ORDER BY CAST(s.roll_number AS UNSIGNED) ASC, s.roll_number ASC, u.first_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $students = [];
        $markedCount = 0;
        $presentCount = 0;
        $absentCount = 0;
        $byLegend = [];
        foreach ($legends as $lg) {
            $byLegend[$lg['id']] = 0;
        }

        foreach ($rows as $row) {
            $fullName = trim(implode(' ', array_filter([
                $row['first_name'] ?? '',
                $row['middle_name'] ?? '',
                $row['last_name'] ?? '',
            ])));
            $legendId = $row['attendance_legend_id'] !== null ? (int) $row['attendance_legend_id'] : null;
            if ($legendId !== null) {
                $markedCount++;
                if (($row['treat_as'] ?? '') === 'present') {
                    $presentCount++;
                } elseif (($row['treat_as'] ?? '') === 'absent') {
                    $absentCount++;
                }
                $byLegend[$legendId] = ($byLegend[$legendId] ?? 0) + 1;
            }

            $students[] = [
                'student_id'           => (int) $row['student_id'],
                'user_id'              => (int) $row['user_id'],
                'student_name'         => $fullName,
                'roll_number'          => $row['roll_number'] ?? '',
                'enrollment_number'    => $row['enrollment_number'] ?? '',
                'scholar_number'       => $row['scholar_number'] ?? '',
                'father_name'          => $row['father_name'] ?? '',
                'mobile_no'            => $row['father_mobile_no'] ?: ($row['mobile_no'] ?? ''),
                'gender'               => $row['gender'] ?? '',
                'photo'                => $row['photo'] ?: null,
                'attendance_id'        => $row['attendance_id'] !== null ? (int) $row['attendance_id'] : null,
                'attendance_legend_id' => $legendId,
                'legend_name'          => $row['legend_name'] ?? null,
                'legend_short_name'    => $row['legend_short_name'] ?? null,
                'treat_as'             => $row['treat_as'] ?? null,
                'remarks'              => $row['remarks'] ?? '',
            ];
        }

        $totalStudents = count($students);

        return Response::success([
            'date'           => $date,
            'section'        => [
                'id'               => (int) $section['id'],
                'section_name'     => (string) $section['section_name'],
                'class_id'         => (int) $section['class_id'],
                'class_name'       => (string) ($section['class_name'] ?? ''),
                'academic_year_id' => $section['academic_year_id'] !== null ? (int) $section['academic_year_id'] : null,
            ],
            'legends'        => $legends,
            'already_marked' => $markedCount > 0,
            'summary'        => [
                'total'     => $totalStudents,
                'marked'    => $markedCount,
                'unmarked'  => max(0, $totalStudents - $markedCount),
                'present'   => $presentCount,
                'absent'    => $absentCount,
                'by_legend' => $byLegend,
            ],
            'students'       => $students,
        ]);
    }

    /**
     * POST /api/student-attendance/batch-save
     */
    public function studentBatchSave(Request $request, array $context = []): Response
    {
        $data = $request->all();
        $date = $this->normalizeDate($data['date'] ?? null, false);
        $sectionId = isset($data['section_id']) ? (int) $data['section_id'] : 0;
        $entries = $data['entries'] ?? [];

        if (!$date) {
            return Response::error('Valid attendance date (YYYY-MM-DD) is required.', 422);
        }
        if (!$sectionId) {
            return Response::error('section_id is required.', 422);
        }
        if (!is_array($entries) || empty($entries)) {
            return Response::error('entries array is required and cannot be empty.', 422);
        }

        $pdo = Database::pdo();
        self::ensureTables($pdo);

        // Fetch valid student legend IDs
        $validLegends = $this->fetchLegends($pdo, 'student');
        $validLegendMap = [];
        foreach ($validLegends as $lg) {
            $validLegendMap[$lg['id']] = $lg;
        }
        if (empty($validLegendMap)) {
            return Response::error('No student attendance legends configured. Please add them in Attendance Legends first.', 422);
        }

        $academicYearId = !empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : null;
        if (!$academicYearId) {
            $ayStmt = $pdo->prepare('SELECT academic_year_id FROM sections WHERE id = :id LIMIT 1');
            $ayStmt->execute(['id' => $sectionId]);
            $secAy = $ayStmt->fetchColumn();
            if ($secAy) {
                $academicYearId = (int) $secAy;
            }
        }

        $firmId = !empty($data['firm_id']) ? (int) $data['firm_id'] : null;
        $authUser = $context['user'] ?? null;
        $markedBy = $authUser ? (int) $authUser->id : null;
        $tenantId = $authUser && isset($authUser->tenant_id) ? (int) $authUser->tenant_id : null;
        if (!$firmId && $authUser && isset($authUser->firm_id) && $authUser->firm_id) {
            $firmId = (int) $authUser->firm_id;
        }

        $upsertSql = 'INSERT INTO student_attendances
                        (tenant_id, firm_id, academic_year_id, section_id, student_id, attendance_date, attendance_legend_id, remarks, marked_by)
                      VALUES
                        (:tenant_id, :firm_id, :academic_year_id, :section_id, :student_id, :attendance_date, :attendance_legend_id, :remarks, :marked_by)
                      ON DUPLICATE KEY UPDATE
                        attendance_legend_id = VALUES(attendance_legend_id),
                        remarks              = VALUES(remarks),
                        section_id           = VALUES(section_id),
                        academic_year_id     = COALESCE(VALUES(academic_year_id), academic_year_id),
                        firm_id              = COALESCE(VALUES(firm_id), firm_id),
                        marked_by            = VALUES(marked_by),
                        deleted_at           = NULL,
                        updated_at           = CURRENT_TIMESTAMP';

        $stmt = $pdo->prepare($upsertSql);
        $saved = 0;

        try {
            $pdo->beginTransaction();

            foreach ($entries as $entry) {
                $stuId = isset($entry['student_id']) ? (int) $entry['student_id'] : 0;
                $legId = isset($entry['attendance_legend_id']) ? (int) $entry['attendance_legend_id'] : 0;
                if (!$stuId || !$legId || !isset($validLegendMap[$legId])) {
                    continue;
                }
                $remarks = isset($entry['remarks']) ? trim((string) $entry['remarks']) : null;
                if ($remarks === '') {
                    $remarks = null;
                }

                $stmt->execute([
                    'tenant_id'            => $tenantId,
                    'firm_id'              => $firmId,
                    'academic_year_id'     => $academicYearId,
                    'section_id'           => $sectionId,
                    'student_id'           => $stuId,
                    'attendance_date'      => $date,
                    'attendance_legend_id' => $legId,
                    'remarks'              => $remarks,
                    'marked_by'            => $markedBy,
                ]);
                $saved++;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to save student attendance: ' . $e->getMessage(), 500);
        }

        return Response::success([
            'date'       => $date,
            'section_id' => $sectionId,
            'saved'      => $saved,
        ], "Saved attendance for {$saved} student(s).");
    }

    /**
     * GET /api/student-attendance/report
     * Query: section_id, class_id, student_id, academic_year_id, firm_id, month (YYYY-MM), from_date, to_date, search
     */
    public function studentReport(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $sectionId = $request->query('section_id') ? (int) $request->query('section_id') : null;
        $classId = $request->query('class_id') ? (int) $request->query('class_id') : null;
        $studentId = $request->query('student_id') ? (int) $request->query('student_id') : null;
        $academicYearId = $request->query('academic_year_id') ? (int) $request->query('academic_year_id') : null;
        $firmId = $request->query('firm_id') ? (int) $request->query('firm_id') : null;
        $month = $request->query('month') ? trim((string) $request->query('month')) : null;
        $fromDate = $this->normalizeDate($request->query('from_date'), false);
        $toDate = $this->normalizeDate($request->query('to_date'), false);
        $search = $request->query('search') ? trim((string) $request->query('search')) : null;

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $fromDate = $month . '-01';
            $toDate = date('Y-m-t', strtotime($fromDate));
        } else {
            $month = null;
        }

        $legends = $this->fetchLegends($pdo, 'student');
        $legendMap = [];
        foreach ($legends as $lg) {
            $legendMap[$lg['id']] = $lg;
        }

        // 1. Fetch matching students
        $stuBindings = [];
        $stuWhere = "WHERE u.deleted_at IS NULL AND s.deleted_at IS NULL";
        if ($sectionId) {
            $stuBindings['section_id'] = $sectionId;
            $stuWhere .= ' AND s.section_id = :section_id';
        }
        if ($classId) {
            $stuBindings['class_id'] = $classId;
            $stuWhere .= ' AND sec.class_id = :class_id';
        }
        if ($studentId) {
            $stuBindings['student_id'] = $studentId;
            $stuWhere .= ' AND s.id = :student_id';
        }
        if ($firmId) {
            $stuBindings['firm_id'] = $firmId;
            $stuWhere .= ' AND (u.firm_id = :firm_id OR u.firm_id IS NULL)';
        }
        if ($academicYearId) {
            $stuBindings['ay_id'] = $academicYearId;
            $stuWhere .= ' AND (COALESCE(s.academic_year_id, u.academic_year_id) = :ay_id OR COALESCE(s.academic_year_id, u.academic_year_id) IS NULL)';
        }
        if ($search !== null && $search !== '') {
            $term = '%' . $search . '%';
            $stuBindings['q1'] = $term;
            $stuBindings['q2'] = $term;
            $stuBindings['q3'] = $term;
            $stuBindings['q4'] = $term;
            $stuBindings['q5'] = $term;
            $stuBindings['q6'] = $term;
            $stuWhere .= ' AND (u.first_name LIKE :q1 OR u.middle_name LIKE :q2 OR u.last_name LIKE :q3 OR s.roll_number LIKE :q4 OR s.enrollment_number LIKE :q5 OR s.scholar_number LIKE :q6)';
        }

        $stuSql = "SELECT s.id AS student_id,
                          u.id AS user_id,
                          u.first_name,
                          u.middle_name,
                          u.last_name,
                          s.roll_number,
                          s.enrollment_number,
                          s.scholar_number,
                          s.photo,
                          s.section_id,
                          sec.name AS section_name,
                          sec.class_id,
                          ac.name AS class_name
                   FROM students s
                   INNER JOIN users u ON u.id = s.user_id
                   LEFT JOIN sections sec ON sec.id = s.section_id
                   LEFT JOIN academic_classes ac ON ac.id = sec.class_id
                   {$stuWhere}
                   ORDER BY ac.sort_order ASC, ac.name ASC, sec.name ASC, CAST(s.roll_number AS UNSIGNED) ASC, s.roll_number ASC, u.first_name ASC
                   LIMIT 1000";

        $stuStmt = $pdo->prepare($stuSql);
        $stuStmt->execute($stuBindings);
        $studentRows = $stuStmt->fetchAll(\PDO::FETCH_ASSOC);

        $studentIds = array_map(static fn($r) => (int) $r['student_id'], $studentRows);

        // 2. Fetch attendance entries for those students in date range
        $attByStudent = [];
        $distinctDates = [];

        if (!empty($studentIds)) {
            $inClause = implode(',', array_fill(0, count($studentIds), '?'));
            $attParams = $studentIds;
            $attWhere = "sa.student_id IN ({$inClause}) AND sa.deleted_at IS NULL";

            if ($fromDate) {
                $attWhere .= ' AND sa.attendance_date >= ?';
                $attParams[] = $fromDate;
            }
            if ($toDate) {
                $attWhere .= ' AND sa.attendance_date <= ?';
                $attParams[] = $toDate;
            }
            if ($academicYearId && !$fromDate && !$toDate) {
                $attWhere .= ' AND (sa.academic_year_id = ? OR sa.academic_year_id IS NULL)';
                $attParams[] = $academicYearId;
            }

            $attSql = "SELECT sa.student_id,
                              sa.attendance_date,
                              sa.attendance_legend_id,
                              sa.remarks,
                              al.name AS legend_name,
                              al.short_name AS legend_short_name,
                              al.treat_as
                       FROM student_attendances sa
                       INNER JOIN attendance_legends al ON al.id = sa.attendance_legend_id
                       WHERE {$attWhere}
                       ORDER BY sa.attendance_date ASC";

            $attStmt = $pdo->prepare($attSql);
            $attStmt->execute($attParams);
            foreach ($attStmt->fetchAll(\PDO::FETCH_ASSOC) as $ar) {
                $sid = (int) $ar['student_id'];
                $d = (string) $ar['attendance_date'];
                $distinctDates[$d] = true;
                $attByStudent[$sid][$d] = [
                    'legend_id'  => (int) $ar['attendance_legend_id'],
                    'short_name' => (string) $ar['legend_short_name'],
                    'name'       => (string) $ar['legend_name'],
                    'treat_as'   => (string) $ar['treat_as'],
                    'remarks'    => $ar['remarks'] ?? '',
                ];
            }
        }

        // Build dates array (all days of month if month specified, else sorted distinct marked dates)
        $datesList = [];
        if ($month && $fromDate && $toDate) {
            $cur = strtotime($fromDate);
            $end = strtotime($toDate);
            while ($cur <= $end) {
                $datesList[] = date('Y-m-d', $cur);
                $cur = strtotime('+1 day', $cur);
            }
        } else {
            $datesList = array_keys($distinctDates);
            sort($datesList);
        }

        $reportRows = [];
        $overallPresent = 0;
        $overallAbsent = 0;
        $overallMarked = 0;
        $overallByLegend = [];
        foreach ($legends as $lg) {
            $overallByLegend[$lg['id']] = 0;
        }
        $sumPct = 0.0;
        $studentsWithRecords = 0;

        foreach ($studentRows as $sr) {
            $sid = (int) $sr['student_id'];
            $daily = $attByStudent[$sid] ?? [];
            $present = 0;
            $absent = 0;
            $legendCounts = [];
            foreach ($legends as $lg) {
                $legendCounts[$lg['id']] = 0;
            }

            foreach ($daily as $entry) {
                $lid = $entry['legend_id'];
                $legendCounts[$lid] = ($legendCounts[$lid] ?? 0) + 1;
                $overallByLegend[$lid] = ($overallByLegend[$lid] ?? 0) + 1;
                if ($entry['treat_as'] === 'present') {
                    $present++;
                } else {
                    $absent++;
                }
            }

            $total = $present + $absent;
            $pct = $total > 0 ? round(($present / $total) * 100, 2) : 0.0;

            $overallPresent += $present;
            $overallAbsent += $absent;
            $overallMarked += $total;
            if ($total > 0) {
                $sumPct += $pct;
                $studentsWithRecords++;
            }

            $fullName = trim(implode(' ', array_filter([
                $sr['first_name'] ?? '',
                $sr['middle_name'] ?? '',
                $sr['last_name'] ?? '',
            ])));

            $reportRows[] = [
                'student_id'        => $sid,
                'user_id'           => (int) $sr['user_id'],
                'student_name'      => $fullName,
                'roll_number'       => $sr['roll_number'] ?? '',
                'enrollment_number' => $sr['enrollment_number'] ?? '',
                'scholar_number'    => $sr['scholar_number'] ?? '',
                'photo'             => $sr['photo'] ?: null,
                'section_id'        => $sr['section_id'] !== null ? (int) $sr['section_id'] : null,
                'section_name'      => $sr['section_name'] ?? '',
                'class_id'          => $sr['class_id'] !== null ? (int) $sr['class_id'] : null,
                'class_name'        => $sr['class_name'] ?? '',
                'present_days'      => $present,
                'absent_days'       => $absent,
                'total_days'        => $total,
                'percentage'        => $pct,
                'formatted'         => self::formatAttendance($present, $total),
                'legend_counts'     => $legendCounts,
                'daily'             => $daily,
            ];
        }

        $avgPct = $studentsWithRecords > 0
            ? round($sumPct / $studentsWithRecords, 2)
            : ($overallMarked > 0 ? round(($overallPresent / $overallMarked) * 100, 2) : 0.0);

        return Response::success([
            'month'           => $month,
            'from_date'       => $fromDate,
            'to_date'         => $toDate,
            'dates'           => $datesList,
            'marked_dates'    => array_keys($distinctDates),
            'legends'         => $legends,
            'overall_summary' => [
                'total_students'     => count($reportRows),
                'total_working_days' => count($distinctDates),
                'total_present'      => $overallPresent,
                'total_absent'       => $overallAbsent,
                'total_marked'       => $overallMarked,
                'avg_percentage'     => $avgPct,
                'by_legend'          => $overallByLegend,
            ],
            'rows'            => $reportRows,
        ]);
    }

    // =========================================================================
    // EMPLOYEE ATTENDANCE
    // =========================================================================

    /**
     * GET /api/employee-attendance/sheet
     * Query: date (YYYY-MM-DD), employee_type, role_id, firm_id, academic_year_id
     */
    public function employeeSheet(Request $request): Response
    {
        $date = $this->normalizeDate($request->query('date'), true);
        if (!$date) {
            return Response::error('Invalid date format. Expected YYYY-MM-DD.', 422);
        }

        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $legends = $this->fetchLegends($pdo, 'employee');

        $bindings = ['att_date' => $date];
        $where = "WHERE u.deleted_at IS NULL AND e.deleted_at IS NULL AND (u.is_active = 'y' OR ea.id IS NOT NULL)";

        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $bindings['firm_id'] = (int) $firmId;
            $where .= ' AND (u.firm_id = :firm_id OR u.firm_id IS NULL)';
        }

        $employeeType = $request->query('employee_type');
        if ($employeeType !== null && $employeeType !== '') {
            $bindings['employee_type'] = (string) $employeeType;
            $where .= ' AND e.employee_type = :employee_type';
        }

        $roleId = $request->query('role_id');
        if ($roleId !== null && $roleId !== '') {
            $bindings['role_id'] = (int) $roleId;
            $where .= ' AND EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id = u.id AND ur2.role_id = :role_id)';
        }

        $sql = "SELECT e.id AS employee_id,
                       u.id AS user_id,
                       u.first_name,
                       u.middle_name,
                       u.last_name,
                       u.email,
                       u.mobile_no,
                       u.gender,
                       e.employee_type,
                       e.attendance_code,
                       e.photo,
                       GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names,
                       ea.id AS attendance_id,
                       ea.attendance_legend_id,
                       ea.in_time,
                       ea.out_time,
                       ea.remarks,
                       al.name AS legend_name,
                       al.short_name AS legend_short_name,
                       al.treat_as
                FROM employees e
                INNER JOIN users u ON u.id = e.user_id
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
                LEFT JOIN employee_attendances ea
                       ON ea.employee_id = e.id
                      AND ea.attendance_date = :att_date
                      AND ea.deleted_at IS NULL
                LEFT JOIN attendance_legends al ON al.id = ea.attendance_legend_id
                {$where}
                GROUP BY e.id, u.id, ea.id, al.id
                ORDER BY u.first_name ASC, u.last_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $employees = [];
        $markedCount = 0;
        $presentCount = 0;
        $absentCount = 0;
        $byLegend = [];
        foreach ($legends as $lg) {
            $byLegend[$lg['id']] = 0;
        }

        foreach ($rows as $row) {
            $fullName = trim(implode(' ', array_filter([
                $row['first_name'] ?? '',
                $row['middle_name'] ?? '',
                $row['last_name'] ?? '',
            ])));
            $legendId = $row['attendance_legend_id'] !== null ? (int) $row['attendance_legend_id'] : null;
            if ($legendId !== null) {
                $markedCount++;
                if (($row['treat_as'] ?? '') === 'present') {
                    $presentCount++;
                } elseif (($row['treat_as'] ?? '') === 'absent') {
                    $absentCount++;
                }
                $byLegend[$legendId] = ($byLegend[$legendId] ?? 0) + 1;
            }

            $employees[] = [
                'employee_id'          => (int) $row['employee_id'],
                'user_id'              => (int) $row['user_id'],
                'employee_name'        => $fullName,
                'employee_type'        => (string) ($row['employee_type'] ?? 'Teaching'),
                'attendance_code'      => $row['attendance_code'] ?? '',
                'role_names'           => $row['role_names'] ?? '',
                'email'                => $row['email'] ?? '',
                'mobile_no'            => $row['mobile_no'] ?? '',
                'gender'               => $row['gender'] ?? '',
                'photo'                => $row['photo'] ?: null,
                'attendance_id'        => $row['attendance_id'] !== null ? (int) $row['attendance_id'] : null,
                'attendance_legend_id' => $legendId,
                'legend_name'          => $row['legend_name'] ?? null,
                'legend_short_name'    => $row['legend_short_name'] ?? null,
                'treat_as'             => $row['treat_as'] ?? null,
                'in_time'              => $row['in_time'] ? substr((string) $row['in_time'], 0, 5) : '',
                'out_time'             => $row['out_time'] ? substr((string) $row['out_time'], 0, 5) : '',
                'remarks'              => $row['remarks'] ?? '',
            ];
        }

        $totalEmployees = count($employees);

        return Response::success([
            'date'           => $date,
            'legends'        => $legends,
            'already_marked' => $markedCount > 0,
            'summary'        => [
                'total'     => $totalEmployees,
                'marked'    => $markedCount,
                'unmarked'  => max(0, $totalEmployees - $markedCount),
                'present'   => $presentCount,
                'absent'    => $absentCount,
                'by_legend' => $byLegend,
            ],
            'employees'      => $employees,
        ]);
    }

    /**
     * POST /api/employee-attendance/batch-save
     */
    public function employeeBatchSave(Request $request, array $context = []): Response
    {
        $data = $request->all();
        $date = $this->normalizeDate($data['date'] ?? null, false);
        $entries = $data['entries'] ?? [];

        if (!$date) {
            return Response::error('Valid attendance date (YYYY-MM-DD) is required.', 422);
        }
        if (!is_array($entries) || empty($entries)) {
            return Response::error('entries array is required and cannot be empty.', 422);
        }

        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $validLegends = $this->fetchLegends($pdo, 'employee');
        $validLegendMap = [];
        foreach ($validLegends as $lg) {
            $validLegendMap[$lg['id']] = $lg;
        }
        if (empty($validLegendMap)) {
            return Response::error('No employee attendance legends configured. Please add them in Attendance Legends first.', 422);
        }

        $academicYearId = !empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : null;
        $firmId = !empty($data['firm_id']) ? (int) $data['firm_id'] : null;
        $authUser = $context['user'] ?? null;
        $markedBy = $authUser ? (int) $authUser->id : null;
        $tenantId = $authUser && isset($authUser->tenant_id) ? (int) $authUser->tenant_id : null;
        if (!$firmId && $authUser && isset($authUser->firm_id) && $authUser->firm_id) {
            $firmId = (int) $authUser->firm_id;
        }

        $upsertSql = 'INSERT INTO employee_attendances
                        (tenant_id, firm_id, academic_year_id, employee_id, attendance_date, attendance_legend_id, in_time, out_time, remarks, marked_by)
                      VALUES
                        (:tenant_id, :firm_id, :academic_year_id, :employee_id, :attendance_date, :attendance_legend_id, :in_time, :out_time, :remarks, :marked_by)
                      ON DUPLICATE KEY UPDATE
                        attendance_legend_id = VALUES(attendance_legend_id),
                        in_time              = VALUES(in_time),
                        out_time             = VALUES(out_time),
                        remarks              = VALUES(remarks),
                        academic_year_id     = COALESCE(VALUES(academic_year_id), academic_year_id),
                        firm_id              = COALESCE(VALUES(firm_id), firm_id),
                        marked_by            = VALUES(marked_by),
                        deleted_at           = NULL,
                        updated_at           = CURRENT_TIMESTAMP';

        $stmt = $pdo->prepare($upsertSql);
        $saved = 0;

        try {
            $pdo->beginTransaction();

            foreach ($entries as $entry) {
                $empId = isset($entry['employee_id']) ? (int) $entry['employee_id'] : 0;
                $legId = isset($entry['attendance_legend_id']) ? (int) $entry['attendance_legend_id'] : 0;
                if (!$empId || !$legId || !isset($validLegendMap[$legId])) {
                    continue;
                }

                $inTime = isset($entry['in_time']) && trim((string) $entry['in_time']) !== ''
                    ? trim((string) $entry['in_time'])
                    : null;
                $outTime = isset($entry['out_time']) && trim((string) $entry['out_time']) !== ''
                    ? trim((string) $entry['out_time'])
                    : null;
                $remarks = isset($entry['remarks']) ? trim((string) $entry['remarks']) : null;
                if ($remarks === '') {
                    $remarks = null;
                }

                $stmt->execute([
                    'tenant_id'            => $tenantId,
                    'firm_id'              => $firmId,
                    'academic_year_id'     => $academicYearId,
                    'employee_id'          => $empId,
                    'attendance_date'      => $date,
                    'attendance_legend_id' => $legId,
                    'in_time'              => $inTime,
                    'out_time'             => $outTime,
                    'remarks'              => $remarks,
                    'marked_by'            => $markedBy,
                ]);
                $saved++;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to save employee attendance: ' . $e->getMessage(), 500);
        }

        return Response::success([
            'date'  => $date,
            'saved' => $saved,
        ], "Saved attendance for {$saved} employee(s).");
    }

    /**
     * GET /api/employee-attendance/report
     * Query: employee_type, role_id, employee_id, academic_year_id, firm_id, month (YYYY-MM), from_date, to_date, search
     */
    public function employeeReport(Request $request): Response
    {
        $pdo = Database::pdo();
        self::ensureTables($pdo);

        $employeeType = $request->query('employee_type') ? trim((string) $request->query('employee_type')) : null;
        $roleId = $request->query('role_id') ? (int) $request->query('role_id') : null;
        $employeeId = $request->query('employee_id') ? (int) $request->query('employee_id') : null;
        $academicYearId = $request->query('academic_year_id') ? (int) $request->query('academic_year_id') : null;
        $firmId = $request->query('firm_id') ? (int) $request->query('firm_id') : null;
        $month = $request->query('month') ? trim((string) $request->query('month')) : null;
        $fromDate = $this->normalizeDate($request->query('from_date'), false);
        $toDate = $this->normalizeDate($request->query('to_date'), false);
        $search = $request->query('search') ? trim((string) $request->query('search')) : null;

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $fromDate = $month . '-01';
            $toDate = date('Y-m-t', strtotime($fromDate));
        } else {
            $month = null;
        }

        $legends = $this->fetchLegends($pdo, 'employee');

        $empBindings = [];
        $empWhere = 'WHERE u.deleted_at IS NULL AND e.deleted_at IS NULL';
        if ($employeeType) {
            $empBindings['employee_type'] = $employeeType;
            $empWhere .= ' AND e.employee_type = :employee_type';
        }
        if ($roleId) {
            $empBindings['role_id'] = $roleId;
            $empWhere .= ' AND EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id = u.id AND ur2.role_id = :role_id)';
        }
        if ($employeeId) {
            $empBindings['employee_id'] = $employeeId;
            $empWhere .= ' AND e.id = :employee_id';
        }
        if ($firmId) {
            $empBindings['firm_id'] = $firmId;
            $empWhere .= ' AND (u.firm_id = :firm_id OR u.firm_id IS NULL)';
        }
        if ($search !== null && $search !== '') {
            $term = '%' . $search . '%';
            $empBindings['q1'] = $term;
            $empBindings['q2'] = $term;
            $empBindings['q3'] = $term;
            $empBindings['q4'] = $term;
            $empBindings['q5'] = $term;
            $empWhere .= ' AND (u.first_name LIKE :q1 OR u.middle_name LIKE :q2 OR u.last_name LIKE :q3 OR e.attendance_code LIKE :q4 OR u.mobile_no LIKE :q5)';
        }

        $empSql = "SELECT e.id AS employee_id,
                          u.id AS user_id,
                          u.first_name,
                          u.middle_name,
                          u.last_name,
                          u.mobile_no,
                          u.email,
                          e.employee_type,
                          e.attendance_code,
                          e.photo,
                          GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names
                   FROM employees e
                   INNER JOIN users u ON u.id = e.user_id
                   LEFT JOIN user_roles ur ON ur.user_id = u.id
                   LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
                   {$empWhere}
                   GROUP BY e.id, u.id
                   ORDER BY u.first_name ASC, u.last_name ASC
                   LIMIT 1000";

        $empStmt = $pdo->prepare($empSql);
        $empStmt->execute($empBindings);
        $empRows = $empStmt->fetchAll(\PDO::FETCH_ASSOC);

        $empIds = array_map(static fn($r) => (int) $r['employee_id'], $empRows);

        $attByEmployee = [];
        $distinctDates = [];

        if (!empty($empIds)) {
            $inClause = implode(',', array_fill(0, count($empIds), '?'));
            $attParams = $empIds;
            $attWhere = "ea.employee_id IN ({$inClause}) AND ea.deleted_at IS NULL";

            if ($fromDate) {
                $attWhere .= ' AND ea.attendance_date >= ?';
                $attParams[] = $fromDate;
            }
            if ($toDate) {
                $attWhere .= ' AND ea.attendance_date <= ?';
                $attParams[] = $toDate;
            }
            if ($academicYearId && !$fromDate && !$toDate) {
                $attWhere .= ' AND (ea.academic_year_id = ? OR ea.academic_year_id IS NULL)';
                $attParams[] = $academicYearId;
            }

            $attSql = "SELECT ea.employee_id,
                              ea.attendance_date,
                              ea.attendance_legend_id,
                              ea.in_time,
                              ea.out_time,
                              ea.remarks,
                              al.name AS legend_name,
                              al.short_name AS legend_short_name,
                              al.treat_as
                       FROM employee_attendances ea
                       INNER JOIN attendance_legends al ON al.id = ea.attendance_legend_id
                       WHERE {$attWhere}
                       ORDER BY ea.attendance_date ASC";

            $attStmt = $pdo->prepare($attSql);
            $attStmt->execute($attParams);
            foreach ($attStmt->fetchAll(\PDO::FETCH_ASSOC) as $ar) {
                $eid = (int) $ar['employee_id'];
                $d = (string) $ar['attendance_date'];
                $distinctDates[$d] = true;
                $attByEmployee[$eid][$d] = [
                    'legend_id'  => (int) $ar['attendance_legend_id'],
                    'short_name' => (string) $ar['legend_short_name'],
                    'name'       => (string) $ar['legend_name'],
                    'treat_as'   => (string) $ar['treat_as'],
                    'in_time'    => $ar['in_time'] ? substr((string) $ar['in_time'], 0, 5) : '',
                    'out_time'   => $ar['out_time'] ? substr((string) $ar['out_time'], 0, 5) : '',
                    'remarks'    => $ar['remarks'] ?? '',
                ];
            }
        }

        $datesList = [];
        if ($month && $fromDate && $toDate) {
            $cur = strtotime($fromDate);
            $end = strtotime($toDate);
            while ($cur <= $end) {
                $datesList[] = date('Y-m-d', $cur);
                $cur = strtotime('+1 day', $cur);
            }
        } else {
            $datesList = array_keys($distinctDates);
            sort($datesList);
        }

        $reportRows = [];
        $overallPresent = 0;
        $overallAbsent = 0;
        $overallMarked = 0;
        $overallByLegend = [];
        foreach ($legends as $lg) {
            $overallByLegend[$lg['id']] = 0;
        }
        $sumPct = 0.0;
        $employeesWithRecords = 0;

        foreach ($empRows as $er) {
            $eid = (int) $er['employee_id'];
            $daily = $attByEmployee[$eid] ?? [];
            $present = 0;
            $absent = 0;
            $legendCounts = [];
            foreach ($legends as $lg) {
                $legendCounts[$lg['id']] = 0;
            }

            foreach ($daily as $entry) {
                $lid = $entry['legend_id'];
                $legendCounts[$lid] = ($legendCounts[$lid] ?? 0) + 1;
                $overallByLegend[$lid] = ($overallByLegend[$lid] ?? 0) + 1;
                if ($entry['treat_as'] === 'present') {
                    $present++;
                } else {
                    $absent++;
                }
            }

            $total = $present + $absent;
            $pct = $total > 0 ? round(($present / $total) * 100, 2) : 0.0;

            $overallPresent += $present;
            $overallAbsent += $absent;
            $overallMarked += $total;
            if ($total > 0) {
                $sumPct += $pct;
                $employeesWithRecords++;
            }

            $fullName = trim(implode(' ', array_filter([
                $er['first_name'] ?? '',
                $er['middle_name'] ?? '',
                $er['last_name'] ?? '',
            ])));

            $reportRows[] = [
                'employee_id'     => $eid,
                'user_id'         => (int) $er['user_id'],
                'employee_name'   => $fullName,
                'employee_type'   => (string) ($er['employee_type'] ?? 'Teaching'),
                'attendance_code' => $er['attendance_code'] ?? '',
                'role_names'      => $er['role_names'] ?? '',
                'mobile_no'       => $er['mobile_no'] ?? '',
                'email'           => $er['email'] ?? '',
                'photo'           => $er['photo'] ?: null,
                'present_days'    => $present,
                'absent_days'     => $absent,
                'total_days'      => $total,
                'percentage'      => $pct,
                'formatted'       => self::formatAttendance($present, $total),
                'legend_counts'   => $legendCounts,
                'daily'           => $daily,
            ];
        }

        $avgPct = $employeesWithRecords > 0
            ? round($sumPct / $employeesWithRecords, 2)
            : ($overallMarked > 0 ? round(($overallPresent / $overallMarked) * 100, 2) : 0.0);

        return Response::success([
            'month'           => $month,
            'from_date'       => $fromDate,
            'to_date'         => $toDate,
            'dates'           => $datesList,
            'marked_dates'    => array_keys($distinctDates),
            'legends'         => $legends,
            'overall_summary' => [
                'total_employees'    => count($reportRows),
                'total_working_days' => count($distinctDates),
                'total_present'      => $overallPresent,
                'total_absent'       => $overallAbsent,
                'total_marked'       => $overallMarked,
                'avg_percentage'     => $avgPct,
                'by_legend'          => $overallByLegend,
            ],
            'rows'            => $reportRows,
        ]);
    }
}

