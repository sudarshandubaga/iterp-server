<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Throwable;

/**
 * Controller for Exam Marks Entry, Consolidated Results & Multiple Format Marksheets.
 */
class ExamMarkController
{
    /**
     * Compute CBSE standard letter grade from percentage.
     */
    public static function computeGrade(float $percentage): array
    {
        if ($percentage >= 91.0) {
            return ['grade' => 'A1', 'point' => 10.0, 'remark' => 'Outstanding'];
        }
        if ($percentage >= 81.0) {
            return ['grade' => 'A2', 'point' => 9.0, 'remark' => 'Excellent'];
        }
        if ($percentage >= 71.0) {
            return ['grade' => 'B1', 'point' => 8.0, 'remark' => 'Very Good'];
        }
        if ($percentage >= 61.0) {
            return ['grade' => 'B2', 'point' => 7.0, 'remark' => 'Good'];
        }
        if ($percentage >= 51.0) {
            return ['grade' => 'C1', 'point' => 6.0, 'remark' => 'Fair'];
        }
        if ($percentage >= 41.0) {
            return ['grade' => 'C2', 'point' => 5.0, 'remark' => 'Average'];
        }
        if ($percentage >= 33.0) {
            return ['grade' => 'D', 'point' => 4.0, 'remark' => 'Pass'];
        }
        return ['grade' => 'E', 'point' => 0.0, 'remark' => 'Needs Improvement'];
    }

    /**
     * Load Marks Entry Sheet for a specific Exam, Section & Subject.
     */
    public function entrySheet(Request $request): Response
    {
        $examId = (int) $request->query('exam_id');
        $sectionId = (int) $request->query('section_id');
        $subjectId = (int) $request->query('subject_id');

        if (!$examId || !$sectionId || !$subjectId) {
            return Response::error('exam_id, section_id, and subject_id are required.', 422);
        }

        $pdo = Database::pdo();

        // 1. Fetch Exam
        $examStmt = $pdo->prepare("SELECT e.id, e.name, e.academic_year_id, e.start_date, e.end_date, e.status,
                                          ay.name AS academic_year_name
                                   FROM exams e
                                   LEFT JOIN academic_years ay ON ay.id = e.academic_year_id
                                   WHERE e.id = :id AND e.deleted_at IS NULL LIMIT 1");
        $examStmt->execute(['id' => $examId]);
        $exam = $examStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$exam) {
            return Response::error('Exam not found.', 404);
        }

        // 2. Fetch Section & Class
        $secStmt = $pdo->prepare("SELECT s.id, s.name, s.class_id, ac.name AS class_name
                                  FROM sections s
                                  LEFT JOIN academic_classes ac ON ac.id = s.class_id
                                  WHERE s.id = :id AND s.deleted_at IS NULL LIMIT 1");
        $secStmt->execute(['id' => $sectionId]);
        $section = $secStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$section) {
            return Response::error('Section not found.', 404);
        }

        // Verify allocation if exam has allocations configured
        $hasAllocStmt = $pdo->prepare("SELECT 1 FROM exam_class_allocations WHERE exam_id = :exam_id LIMIT 1");
        $hasAllocStmt->execute(['exam_id' => $examId]);
        if ($hasAllocStmt->fetch()) {
            $isAllocatedStmt = $pdo->prepare("SELECT 1 FROM exam_class_allocations WHERE exam_id = :exam_id AND section_id = :section_id LIMIT 1");
            $isAllocatedStmt->execute(['exam_id' => $examId, 'section_id' => $sectionId]);
            if (!$isAllocatedStmt->fetch()) {
                return Response::error('This section has not been allocated to the selected exam. Please allocate it in Exam Class Allocation first.', 422);
            }
        }

        // 3. Fetch Subject
        $subStmt = $pdo->prepare("SELECT id, name, short_name, code, type FROM exam_subjects WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $subStmt->execute(['id' => $subjectId]);
        $subject = $subStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$subject) {
            return Response::error('Subject not found.', 404);
        }

        // 4. Default min/max marks from exam_schedules (if scheduled)
        $schedStmt = $pdo->prepare("SELECT min_marks, max_marks, exam_date, start_time, end_time, room_no
                                    FROM exam_schedules
                                    WHERE exam_id = :exam_id AND subject_id = :subject_id 
                                      AND (academic_class_id = :class_id OR section_id = :section_id)
                                      AND deleted_at IS NULL
                                    ORDER BY (section_id IS NOT NULL) DESC
                                    LIMIT 1");
        $schedStmt->execute([
            'exam_id'    => $examId,
            'subject_id' => $subjectId,
            'class_id'   => (int) $section['class_id'],
            'section_id' => $sectionId,
        ]);
        $schedule = $schedStmt->fetch(\PDO::FETCH_ASSOC);

        $defaultMinMarks = $schedule ? (float) $schedule['min_marks'] : 33.00;
        $defaultMaxMarks = $schedule ? (float) $schedule['max_marks'] : 100.00;

        // 5. Fetch Students Roster with any existing marks
        $studentsSql = "SELECT s.id AS student_id,
                               s.enrollment_number,
                               s.scholar_number,
                               s.roll_number,
                               u.id AS user_id,
                               u.first_name,
                               u.middle_name,
                               u.last_name,
                               u.gender,
                               u.mobile_no,
                               em.id AS mark_id,
                               em.min_marks,
                               em.max_marks,
                               em.obtained_marks,
                               em.attendance,
                               em.remarks
                        FROM students s
                        JOIN users u ON u.id = s.user_id AND u.deleted_at IS NULL
                        LEFT JOIN exam_marks em ON em.student_id = s.id
                             AND em.exam_id = :exam_id
                             AND em.subject_id = :subject_id
                             AND em.deleted_at IS NULL
                        WHERE s.section_id = :section_id AND s.deleted_at IS NULL
                        ORDER BY CAST(s.roll_number AS UNSIGNED) ASC, s.roll_number ASC, u.first_name ASC";

        $studentsStmt = $pdo->prepare($studentsSql);
        $studentsStmt->execute([
            'exam_id'    => $examId,
            'subject_id' => $subjectId,
            'section_id' => $sectionId,
        ]);
        $rows = $studentsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $students = [];
        foreach ($rows as $row) {
            $hasMarks = $row['mark_id'] !== null;
            $minM = $hasMarks ? (float) $row['min_marks'] : $defaultMinMarks;
            $maxM = $hasMarks ? (float) $row['max_marks'] : $defaultMaxMarks;
            $obtM = $hasMarks ? (float) $row['obtained_marks'] : 0.00;
            $att = $hasMarks && !empty($row['attendance']) ? $row['attendance'] : 'present';

            $pct = $maxM > 0 ? round(($obtM / $maxM) * 100, 2) : 0.00;
            $gradeInfo = self::computeGrade($pct);

            $students[] = [
                'student_id'        => (int) $row['student_id'],
                'user_id'           => (int) $row['user_id'],
                'enrollment_number' => $row['enrollment_number'],
                'scholar_number'    => $row['scholar_number'],
                'roll_number'       => $row['roll_number'] ?: '',
                'student_name'      => trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'gender'            => $row['gender'],
                'mark_id'           => $row['mark_id'] ? (int) $row['mark_id'] : null,
                'min_marks'         => $minM,
                'max_marks'         => $maxM,
                'obtained_marks'    => $obtM,
                'attendance'        => $att,
                'remarks'           => $row['remarks'] ?: '',
                'percentage'        => $pct,
                'grade'             => $gradeInfo['grade'],
                'has_marks'         => $hasMarks,
            ];
        }

        return Response::success([
            'exam'              => $exam,
            'section'           => $section,
            'subject'           => $subject,
            'schedule'          => $schedule ?: null,
            'default_min_marks' => $defaultMinMarks,
            'default_max_marks' => $defaultMaxMarks,
            'total_students'    => count($students),
            'students'          => $students,
        ]);
    }

    /**
     * Batch save marks for a subject and students.
     */
    public function batchSave(Request $request): Response
    {
        $data = $request->all();
        $examId = (int) ($data['exam_id'] ?? 0);
        $sectionId = (int) ($data['section_id'] ?? 0);
        $subjectId = (int) ($data['subject_id'] ?? 0);
        $minMarks = isset($data['min_marks']) ? (float) $data['min_marks'] : 33.00;
        $maxMarks = isset($data['max_marks']) ? (float) $data['max_marks'] : 100.00;
        $entries = isset($data['entries']) && is_array($data['entries']) ? $data['entries'] : [];

        if (!$examId || !$sectionId || !$subjectId) {
            return Response::error('exam_id, section_id, and subject_id are required.', 422);
        }

        if (empty($entries)) {
            return Response::error('No student entries provided.', 422);
        }

        $pdo = Database::pdo();

        // Get class_id from section
        $secStmt = $pdo->prepare("SELECT class_id FROM sections WHERE id = :id LIMIT 1");
        $secStmt->execute(['id' => $sectionId]);
        $sec = $secStmt->fetch(\PDO::FETCH_ASSOC);
        $classId = $sec ? (int) $sec['class_id'] : null;

        // Verify allocation if exam has allocations configured
        $hasAllocStmt = $pdo->prepare("SELECT 1 FROM exam_class_allocations WHERE exam_id = :exam_id LIMIT 1");
        $hasAllocStmt->execute(['exam_id' => $examId]);
        if ($hasAllocStmt->fetch()) {
            $isAllocatedStmt = $pdo->prepare("SELECT 1 FROM exam_class_allocations WHERE exam_id = :exam_id AND section_id = :section_id LIMIT 1");
            $isAllocatedStmt->execute(['exam_id' => $examId, 'section_id' => $sectionId]);
            if (!$isAllocatedStmt->fetch()) {
                return Response::error('Cannot save marks: this section is not allocated to the selected exam.', 422);
            }
        }

        try {
            $pdo->beginTransaction();
            $upsertSql = "INSERT INTO exam_marks (exam_id, academic_class_id, section_id, subject_id, student_id, min_marks, max_marks, obtained_marks, attendance, remarks, created_at, updated_at)
                          VALUES (:exam_id, :class_id, :section_id, :subject_id, :student_id, :min_marks, :max_marks, :obtained_marks, :attendance, :remarks, NOW(), NOW())
                          ON DUPLICATE KEY UPDATE
                              academic_class_id = VALUES(academic_class_id),
                              min_marks = VALUES(min_marks),
                              max_marks = VALUES(max_marks),
                              obtained_marks = VALUES(obtained_marks),
                              attendance = VALUES(attendance),
                              remarks = VALUES(remarks),
                              updated_at = NOW(),
                              deleted_at = NULL";

            $stmt = $pdo->prepare($upsertSql);
            $savedCount = 0;

            foreach ($entries as $entry) {
                $studentId = (int) ($entry['student_id'] ?? 0);
                if (!$studentId) {
                    continue;
                }

                $att = strtolower((string) ($entry['attendance'] ?? 'present'));
                if ($att !== 'absent') {
                    $att = 'present';
                }

                $rawObtained = (float) ($entry['obtained_marks'] ?? 0.00);
                $entryMin = isset($entry['min_marks']) ? (float) $entry['min_marks'] : $minMarks;
                $entryMax = isset($entry['max_marks']) ? (float) $entry['max_marks'] : $maxMarks;

                // When absent, obtained marks must be 0
                $obtained = $att === 'absent' ? 0.00 : min($entryMax, max(0.00, $rawObtained));
                $remarks = !empty($entry['remarks']) ? trim((string) $entry['remarks']) : null;

                $stmt->execute([
                    'exam_id'        => $examId,
                    'class_id'       => $classId,
                    'section_id'     => $sectionId,
                    'subject_id'     => $subjectId,
                    'student_id'     => $studentId,
                    'min_marks'      => $entryMin,
                    'max_marks'      => $entryMax,
                    'obtained_marks' => $obtained,
                    'attendance'     => $att,
                    'remarks'        => $remarks,
                ]);

                $savedCount++;
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            return Response::success([
                'message'     => 'Marks saved successfully.',
                'saved_count' => $savedCount,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to save marks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Consolidated Result Tabulation Sheet for an Exam & Section/Class.
     */
    public function resultSheet(Request $request): Response
    {
        $examId = (int) $request->query('exam_id');
        $sectionId = (int) $request->query('section_id');

        if (!$examId || !$sectionId) {
            return Response::error('exam_id and section_id are required.', 422);
        }

        $pdo = Database::pdo();

        // 1. Exam
        $examStmt = $pdo->prepare("SELECT e.*, ay.name AS academic_year_name, et.name AS exam_type_name
                                   FROM exams e
                                   LEFT JOIN academic_years ay ON ay.id = e.academic_year_id
                                   LEFT JOIN exam_types et ON et.id = e.exam_type_id
                                   WHERE e.id = :id AND e.deleted_at IS NULL LIMIT 1");
        $examStmt->execute(['id' => $examId]);
        $exam = $examStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$exam) {
            return Response::error('Exam not found.', 404);
        }

        // 2. Section & Class
        $secStmt = $pdo->prepare("SELECT s.id, s.name AS section_name, s.class_id, ac.name AS class_name
                                  FROM sections s
                                  LEFT JOIN academic_classes ac ON ac.id = s.class_id
                                  WHERE s.id = :id AND s.deleted_at IS NULL LIMIT 1");
        $secStmt->execute(['id' => $sectionId]);
        $section = $secStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$section) {
            return Response::error('Section not found.', 404);
        }

        // 3. Subjects list for this Exam & Section (from schedules or recorded marks)
        $subSql = "SELECT DISTINCT sub.id, sub.name, sub.short_name, sub.code, sub.type, sub.sort_order,
                                   COALESCE(sched.min_marks, marks.min_marks, 33.00) AS default_min_marks,
                                   COALESCE(sched.max_marks, marks.max_marks, 100.00) AS default_max_marks
                   FROM exam_subjects sub
                   LEFT JOIN exam_schedules sched ON sched.subject_id = sub.id 
                        AND sched.exam_id = :exam_id_1 
                        AND (sched.section_id = :sec_id_1 OR sched.academic_class_id = :class_id_1)
                        AND sched.deleted_at IS NULL
                   LEFT JOIN exam_marks marks ON marks.subject_id = sub.id 
                        AND marks.exam_id = :exam_id_2 
                        AND marks.section_id = :sec_id_2
                        AND marks.deleted_at IS NULL
                   WHERE (sched.id IS NOT NULL OR marks.id IS NOT NULL)
                     AND sub.deleted_at IS NULL
                   ORDER BY sub.type ASC, sub.sort_order ASC, sub.name ASC";

        $subStmt = $pdo->prepare($subSql);
        $subStmt->execute([
            'exam_id_1'  => $examId,
            'sec_id_1'   => $sectionId,
            'class_id_1' => (int) $section['class_id'],
            'exam_id_2'  => $examId,
            'sec_id_2'   => $sectionId,
        ]);
        $subjects = $subStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fallback: If no schedules or marks found yet, load all active primary subjects
        if (empty($subjects)) {
            $fallbackStmt = $pdo->prepare("SELECT id, name, short_name, code, type, sort_order, 33.00 AS default_min_marks, 100.00 AS default_max_marks
                                           FROM exam_subjects WHERE deleted_at IS NULL AND status = 'active'
                                           ORDER BY type ASC, sort_order ASC, name ASC");
            $fallbackStmt->execute();
            $subjects = $fallbackStmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        // 4. Students
        $studentsStmt = $pdo->prepare("SELECT s.id AS student_id, s.enrollment_number, s.scholar_number, s.roll_number,
                                              u.first_name, u.middle_name, u.last_name, u.gender
                                       FROM students s
                                       JOIN users u ON u.id = s.user_id AND u.deleted_at IS NULL
                                       WHERE s.section_id = :section_id AND s.deleted_at IS NULL
                                       ORDER BY CAST(s.roll_number AS UNSIGNED) ASC, s.roll_number ASC, u.first_name ASC");
        $studentsStmt->execute(['section_id' => $sectionId]);
        $studentRows = $studentsStmt->fetchAll(\PDO::FETCH_ASSOC);

        // 5. All marks for this exam and section
        $marksStmt = $pdo->prepare("SELECT em.student_id, em.subject_id, em.min_marks, em.max_marks, em.obtained_marks, em.attendance, em.remarks
                                   FROM exam_marks em
                                   WHERE em.exam_id = :exam_id AND em.section_id = :section_id AND em.deleted_at IS NULL");
        $marksStmt->execute([
            'exam_id'    => $examId,
            'section_id' => $sectionId,
        ]);
        $allMarks = $marksStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Map marks by [student_id][subject_id]
        $marksMatrix = [];
        foreach ($allMarks as $m) {
            $sid = (int) $m['student_id'];
            $subid = (int) $m['subject_id'];
            $marksMatrix[$sid][$subid] = $m;
        }

        // 6. Build Student Result Rows
        $resultRows = [];
        foreach ($studentRows as $st) {
            $sid = (int) $st['student_id'];
            $stName = trim(($st['first_name'] ?? '') . ' ' . ($st['middle_name'] ?? '') . ' ' . ($st['last_name'] ?? ''));

            $subjectResults = [];
            $totalMax = 0.00;
            $totalObtained = 0.00;
            $primaryFailedCount = 0;
            $hasAnyAbsent = false;

            foreach ($subjects as $sub) {
                $subid = (int) $sub['id'];
                $markRec = $marksMatrix[$sid][$subid] ?? null;

                $maxM = $markRec ? (float) $markRec['max_marks'] : (float) $sub['default_max_marks'];
                $minM = $markRec ? (float) $markRec['min_marks'] : (float) $sub['default_min_marks'];
                $obtM = $markRec ? (float) $markRec['obtained_marks'] : 0.00;
                $att = $markRec ? $markRec['attendance'] : 'absent';
                $isAbsent = $att === 'absent';

                if ($isAbsent) {
                    $hasAnyAbsent = true;
                }

                $isPass = !$isAbsent && ($obtM >= $minM);
                if (!$isPass && $sub['type'] === 'primary') {
                    $primaryFailedCount++;
                }

                $pct = $maxM > 0 ? round(($obtM / $maxM) * 100, 2) : 0.00;
                $gradeInfo = self::computeGrade($pct);

                if ($sub['type'] === 'primary') {
                    $totalMax += $maxM;
                    $totalObtained += $obtM;
                }

                $subjectResults[$subid] = [
                    'subject_id'     => $subid,
                    'subject_name'   => $sub['name'],
                    'short_name'     => $sub['short_name'],
                    'type'           => $sub['type'],
                    'min_marks'      => $minM,
                    'max_marks'      => $maxM,
                    'obtained_marks' => $obtM,
                    'attendance'     => $att,
                    'is_pass'        => $isPass,
                    'grade'          => $gradeInfo['grade'],
                    'grade_point'    => $gradeInfo['point'],
                ];
            }

            $overallPct = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 2) : 0.00;
            $overallGrade = self::computeGrade($overallPct);

            $status = 'Passed';
            if ($primaryFailedCount > 0) {
                $status = $primaryFailedCount <= 2 ? 'Needs Improvement' : 'Failed';
            }

            $resultRows[] = [
                'student_id'        => $sid,
                'enrollment_number' => $st['enrollment_number'],
                'scholar_number'    => $st['scholar_number'],
                'roll_number'       => $st['roll_number'] ?: '',
                'student_name'      => $stName,
                'gender'            => $st['gender'],
                'subjects'          => $subjectResults,
                'total_max'         => $totalMax,
                'total_obtained'    => $totalObtained,
                'percentage'        => $overallPct,
                'grade'             => $overallGrade['grade'],
                'grade_point'       => $overallGrade['point'],
                'result_status'     => $status,
                'has_absent'        => $hasAnyAbsent,
                'failed_subjects'   => $primaryFailedCount,
            ];
        }

        // 7. Calculate Ranks (based on total_obtained DESC)
        usort($resultRows, function ($a, $b) {
            if ($b['total_obtained'] == $a['total_obtained']) {
                return $b['percentage'] <=> $a['percentage'];
            }
            return $b['total_obtained'] <=> $a['total_obtained'];
        });

        $currentRank = 1;
        foreach ($resultRows as $idx => &$row) {
            if ($idx > 0 && $row['total_obtained'] < $resultRows[$idx - 1]['total_obtained']) {
                $currentRank = $idx + 1;
            }
            $row['rank'] = $row['result_status'] === 'Failed' ? '—' : (string) $currentRank;
        }
        unset($row);

        // Sort back by roll_number for display
        usort($resultRows, function ($a, $b) {
            $rA = (int) ($a['roll_number'] ?: 999999);
            $rB = (int) ($b['roll_number'] ?: 999999);
            if ($rA !== $rB) {
                return $rA <=> $rB;
            }
            return strcmp($a['student_name'], $b['student_name']);
        });

        // 8. Summary KPIs
        $totalStudents = count($resultRows);
        $passedCount = count(array_filter($resultRows, fn($r) => $r['result_status'] === 'Passed'));
        $failedCount = count(array_filter($resultRows, fn($r) => $r['result_status'] === 'Failed'));
        $needsImpCount = count(array_filter($resultRows, fn($r) => $r['result_status'] === 'Needs Improvement'));
        $highestPct = $totalStudents > 0 ? max(array_column($resultRows, 'percentage')) : 0.00;
        $avgPct = $totalStudents > 0 ? round(array_sum(array_column($resultRows, 'percentage')) / $totalStudents, 2) : 0.00;

        return Response::success([
            'exam'     => $exam,
            'section'  => $section,
            'subjects' => $subjects,
            'summary'  => [
                'total_students'    => $totalStudents,
                'passed'            => $passedCount,
                'failed'            => $failedCount,
                'needs_improvement' => $needsImpCount,
                'highest_pct'       => $highestPct,
                'average_pct'       => $avgPct,
                'pass_rate'         => $totalStudents > 0 ? round(($passedCount / $totalStudents) * 100, 1) : 0.0,
            ],
            'students' => $resultRows,
        ]);
    }

    /**
     * Marksheet data payload formatted for generating Marksheets (Multiple Formats).
     */
    public function marksheetData(Request $request): Response
    {
        $examId = (int) $request->query('exam_id');
        $sectionId = (int) $request->query('section_id');
        $studentId = $request->query('student_id') ? (int) $request->query('student_id') : null;

        if (!$examId || !$sectionId) {
            return Response::error('exam_id and section_id are required.', 422);
        }

        $pdo = Database::pdo();

        // 1. Exam & Firm
        $examStmt = $pdo->prepare("SELECT e.*, ay.name AS academic_year_name, et.name AS exam_type_name,
                                          f.name AS firm_name, f.email AS firm_email, f.phone_no AS firm_phone,
                                          f.address AS firm_address, f.logo AS firm_logo, f.registration_no AS firm_registration_no
                                   FROM exams e
                                   LEFT JOIN academic_years ay ON ay.id = e.academic_year_id
                                   LEFT JOIN exam_types et ON et.id = e.exam_type_id
                                   LEFT JOIN firms f ON f.id = e.firm_id
                                   WHERE e.id = :id AND e.deleted_at IS NULL LIMIT 1");
        $examStmt->execute(['id' => $examId]);
        $exam = $examStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$exam) {
            return Response::error('Exam not found.', 404);
        }

        // 2. Class & Section
        $secStmt = $pdo->prepare("SELECT s.id, s.name AS section_name, s.class_id, ac.name AS class_name
                                  FROM sections s
                                  LEFT JOIN academic_classes ac ON ac.id = s.class_id
                                  WHERE s.id = :id AND s.deleted_at IS NULL LIMIT 1");
        $secStmt->execute(['id' => $sectionId]);
        $section = $secStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$section) {
            return Response::error('Section not found.', 404);
        }

        // Reuse resultSheet calculation logic for full consistency
        $resultResp = $this->resultSheet($request);
        if ($resultResp->getStatus() >= 400) {
            return $resultResp;
        }
        $body = $resultResp->getBody();
        $resultData = is_array($body) ? $body : (is_string($body) ? (json_decode($body, true) ?: []) : []);
        $allStudents = $resultData['data']['students'] ?? [];
        $subjectsList = $resultData['data']['subjects'] ?? [];

        // If specific student requested, filter
        if ($studentId) {
            $allStudents = array_values(array_filter($allStudents, fn($s) => $s['student_id'] === $studentId));
        }

        // Enrich student rows with additional profile info (father_name, mother_name, dob, photo)
        $studentIds = array_column($allStudents, 'student_id');
        $profileMap = [];
        if (!empty($studentIds)) {
            $inClause = implode(',', array_fill(0, count($studentIds), '?'));
            $profSql = "SELECT s.id AS student_id, s.photo, s.father_mobile_no,
                               u.dob, u.gender,
                               reg.father_name, reg.mother_name, reg.admission_no, reg.registration_no,
                               (SELECT cfv.value
                                FROM custom_field_values cfv
                                INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
                                WHERE cfv.user_id = u.id AND LOWER(TRIM(cf.name)) IN ('father name', 'fathers name', 'father_name')
                                LIMIT 1) AS cf_father_name,
                               (SELECT cfv.value
                                FROM custom_field_values cfv
                                INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
                                WHERE cfv.user_id = u.id AND LOWER(TRIM(cf.name)) IN ('mother name', 'mothers name', 'mother_name')
                                LIMIT 1) AS cf_mother_name
                        FROM students s
                        JOIN users u ON u.id = s.user_id
                        LEFT JOIN registrations reg ON reg.student_id = s.id
                        WHERE s.id IN ({$inClause})";

            $profStmt = $pdo->prepare($profSql);
            $profStmt->execute($studentIds);
            foreach ($profStmt->fetchAll(\PDO::FETCH_ASSOC) as $pr) {
                $profileMap[(int) $pr['student_id']] = [
                    'father_name'  => $pr['father_name'] ?: ($pr['cf_father_name'] ?: 'N/A'),
                    'mother_name'  => $pr['mother_name'] ?: ($pr['cf_mother_name'] ?: 'N/A'),
                    'dob'          => $pr['dob'] ?: '',
                    'gender'       => $pr['gender'] ?: '',
                    'photo'        => $pr['photo'] ?: null,
                    'admission_no' => $pr['admission_no'] ?: ($pr['registration_no'] ?: ''),
                ];
            }
        }

        // 3. Multi-term exams for this Academic Year and Section
        $ayId = (int) $exam['academic_year_id'];
        $ayExamsStmt = $pdo->prepare("SELECT e.id, e.name, e.start_date, et.name AS type_name, et.short_name AS type_short_name
                                      FROM exams e
                                      LEFT JOIN exam_types et ON et.id = e.exam_type_id
                                      WHERE e.academic_year_id = :ay_id AND e.deleted_at IS NULL
                                      ORDER BY e.start_date ASC, e.id ASC");
        $ayExamsStmt->execute(['ay_id' => $ayId]);
        $sessionExams = $ayExamsStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fetch marks for this section across all session exams
        $sessionMarksMap = []; // [student_id][subject_id][exam_id] => mark row
        if (!empty($sessionExams)) {
            $examIds = array_column($sessionExams, 'id');
            $inExams = implode(',', array_fill(0, count($examIds), '?'));
            $allMarksStmt = $pdo->prepare("SELECT em.exam_id, em.student_id, em.subject_id, em.min_marks, em.max_marks, em.obtained_marks, em.attendance
                                           FROM exam_marks em
                                           WHERE em.section_id = ? AND em.exam_id IN ({$inExams}) AND em.deleted_at IS NULL");
            $allMarksStmt->execute(array_merge([$sectionId], $examIds));
            foreach ($allMarksStmt->fetchAll(\PDO::FETCH_ASSOC) as $mr) {
                $eId = (int) $mr['exam_id'];
                $sId = (int) $mr['student_id'];
                $subId = (int) $mr['subject_id'];
                $sessionMarksMap[$sId][$subId][$eId] = $mr;
            }
        }

        // Map session exams to standard report card term slots
        $slotMap = [
            'unit_1'      => null,
            'unit_2'      => null,
            'half_yearly' => null,
            'unit_3'      => null,
            'final'       => null,
        ];

        foreach ($sessionExams as $se) {
            $eName = strtolower($se['name'] . ' ' . ($se['type_name'] ?? '') . ' ' . ($se['type_short_name'] ?? ''));
            if ($slotMap['unit_1'] === null && (str_contains($eName, 'i unit') || str_contains($eName, '1st unit') || str_contains($eName, 'unit 1') || str_contains($eName, 'unit-1') || str_contains($eName, 'unit i') || str_contains($eName, 'ut 1') || str_contains($eName, 'ut1'))) {
                $slotMap['unit_1'] = $se;
            } elseif ($slotMap['unit_2'] === null && (str_contains($eName, 'ii unit') || str_contains($eName, '2nd unit') || str_contains($eName, 'unit 2') || str_contains($eName, 'unit-2') || str_contains($eName, 'unit ii') || str_contains($eName, 'ut 2') || str_contains($eName, 'ut2'))) {
                $slotMap['unit_2'] = $se;
            } elseif ($slotMap['half_yearly'] === null && (str_contains($eName, 'half') || str_contains($eName, 'mid') || str_contains($eName, 'term 1') || str_contains($eName, 'hy'))) {
                $slotMap['half_yearly'] = $se;
            } elseif ($slotMap['unit_3'] === null && (str_contains($eName, 'iii unit') || str_contains($eName, '3rd unit') || str_contains($eName, 'unit 3') || str_contains($eName, 'unit-3') || str_contains($eName, 'unit iii') || str_contains($eName, 'ut 3') || str_contains($eName, 'ut3'))) {
                $slotMap['unit_3'] = $se;
            } elseif ($slotMap['final'] === null && (str_contains($eName, 'final') || str_contains($eName, 'annual') || str_contains($eName, 'yearly') || str_contains($eName, 'term 2'))) {
                $slotMap['final'] = $se;
            }
        }

        // Ensure current exam is slotted if not already matched
        $curExamMatched = false;
        foreach ($slotMap as $slotKey => $slotVal) {
            if ($slotVal && (int) $slotVal['id'] === $examId) {
                $curExamMatched = true;
                break;
            }
        }
        if (!$curExamMatched) {
            if ($slotMap['half_yearly'] === null) {
                $slotMap['half_yearly'] = $exam;
            } else {
                foreach ($slotMap as $sk => $sv) {
                    if ($sv === null) {
                        $slotMap[$sk] = $exam;
                        break;
                    }
                }
            }
        }

        $marksheets = [];
        foreach ($allStudents as $st) {
            $sid = (int) $st['student_id'];
            $prof = $profileMap[$sid] ?? [];

            // Split into Primary Subjects and Extra Activity / Co-Scholastic Subjects
            $primarySubjects = [];
            $extraSubjects = [];

            $termTotals = [
                'unit_1'      => ['max' => 0.0, 'obtained' => 0.0, 'percentage' => 0.0],
                'unit_2'      => ['max' => 0.0, 'obtained' => 0.0, 'percentage' => 0.0],
                'half_yearly' => ['max' => 0.0, 'obtained' => 0.0, 'percentage' => 0.0],
                'unit_3'      => ['max' => 0.0, 'obtained' => 0.0, 'percentage' => 0.0],
                'final'       => ['max' => 0.0, 'obtained' => 0.0, 'percentage' => 0.0],
                'grand_total' => ['max' => 0.0, 'obtained' => 0.0, 'percentage' => 0.0],
            ];

            foreach ($st['subjects'] as $subRes) {
                $subId = (int) $subRes['subject_id'];
                $slotsData = [];
                $subGrandMax = 0.0;
                $subGrandObt = 0.0;
                $hasTermScore = false;

                foreach ($slotMap as $slotKey => $slotExam) {
                    if ($slotExam) {
                        $seId = (int) $slotExam['id'];
                        $mRow = $sessionMarksMap[$sid][$subId][$seId] ?? null;
                        if ($mRow) {
                            $hasTermScore = true;
                            $obt = (float) $mRow['obtained_marks'];
                            $max = (float) $mRow['max_marks'];
                            $att = $mRow['attendance'];
                            $slotsData[$slotKey] = [
                                'max'        => $max,
                                'obtained'   => $obt,
                                'attendance' => $att,
                            ];
                            $subGrandMax += $max;
                            if ($att !== 'absent') {
                                $subGrandObt += $obt;
                            }
                            if ($subRes['type'] === 'primary') {
                                $termTotals[$slotKey]['max'] += $max;
                                if ($att !== 'absent') {
                                    $termTotals[$slotKey]['obtained'] += $obt;
                                }
                            }
                        } elseif ($seId === $examId) {
                            $hasTermScore = true;
                            $obt = (float) $subRes['obtained_marks'];
                            $max = (float) $subRes['max_marks'];
                            $att = $subRes['attendance'];
                            $slotsData[$slotKey] = [
                                'max'        => $max,
                                'obtained'   => $obt,
                                'attendance' => $att,
                            ];
                            $subGrandMax += $max;
                            if ($att !== 'absent') {
                                $subGrandObt += $obt;
                            }
                            if ($subRes['type'] === 'primary') {
                                $termTotals[$slotKey]['max'] += $max;
                                if ($att !== 'absent') {
                                    $termTotals[$slotKey]['obtained'] += $obt;
                                }
                            }
                        } else {
                            $slotsData[$slotKey] = null;
                        }
                    } else {
                        $slotsData[$slotKey] = null;
                    }
                }

                if (!$hasTermScore) {
                    $obt = (float) $subRes['obtained_marks'];
                    $max = (float) $subRes['max_marks'];
                    $att = $subRes['attendance'];
                    $slotsData['half_yearly'] = [
                        'max'        => $max,
                        'obtained'   => $obt,
                        'attendance' => $att,
                    ];
                    $subGrandMax = $max;
                    if ($att !== 'absent') {
                        $subGrandObt = $obt;
                    }
                    if ($subRes['type'] === 'primary') {
                        $termTotals['half_yearly']['max'] += $max;
                        if ($att !== 'absent') {
                            $termTotals['half_yearly']['obtained'] += $obt;
                        }
                    }
                }

                $slotsData['grand_total'] = [
                    'max'      => $subGrandMax,
                    'obtained' => $subGrandObt,
                ];

                if ($subRes['type'] === 'primary') {
                    $termTotals['grand_total']['max'] += $subGrandMax;
                    $termTotals['grand_total']['obtained'] += $subGrandObt;
                }

                $subRes['term_marks'] = $slotsData;

                if ($subRes['type'] === 'extra_activity') {
                    $extraSubjects[] = $subRes;
                } else {
                    $primarySubjects[] = $subRes;
                }
            }

            foreach ($termTotals as $k => &$t) {
                $t['percentage'] = $t['max'] > 0 ? round(($t['obtained'] / $t['max']) * 100, 2) : 0.0;
            }
            unset($t);

            $marksheets[] = array_merge($st, [
                'father_name'      => $prof['father_name'] ?? 'N/A',
                'mother_name'      => $prof['mother_name'] ?? 'N/A',
                'dob'              => $prof['dob'] ?? '',
                'photo'            => $prof['photo'] ?? null,
                'admission_no'     => $prof['admission_no'] ?? '',
                'primary_subjects' => $primarySubjects,
                'extra_subjects'   => $extraSubjects,
                'term_totals'      => $termTotals,
            ]);
        }

        return Response::success([
            'school'     => [
                'name'            => $exam['firm_name'] ?: 'MADHUVAN PUBLIC SR. SEC. SCHOOL',
                'address'         => $exam['firm_address'] ?: 'SECTOR-2, MADHUVAN, BASNI, JODHPUR (RAJ.)',
                'email'           => $exam['firm_email'] ?: 'info@school.edu',
                'phone'           => $exam['firm_phone'] ?: '2721293',
                'logo'            => $exam['firm_logo'] ?: null,
                'registration_no' => $exam['firm_registration_no'] ?: '4664 Dt. 21-9-2001',
            ],
            'exam'       => $exam,
            'section'    => $section,
            'subjects'   => $subjectsList,
            'marksheets' => $marksheets,
            'term_slots' => $slotMap,
        ]);
    }
}

