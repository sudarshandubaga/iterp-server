<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\ExamSchedule;
use Throwable;

/**
 * Controller for Exam Schedules (Timetable, Dates, Times & Min/Max Marks).
 */
class ExamScheduleController
{
    public function index(Request $request): Response
    {
        $pdo = Database::pdo();
        $where = ['es.deleted_at IS NULL'];
        $params = [];

        $examId = $request->query('exam_id');
        if ($examId !== null && $examId !== '') {
            $where[] = 'es.exam_id = :exam_id';
            $params['exam_id'] = (int) $examId;
        }

        $classId = $request->query('academic_class_id') ?? $request->query('class_id');
        if ($classId !== null && $classId !== '') {
            $where[] = 'es.academic_class_id = :class_id';
            $params['class_id'] = (int) $classId;
        }

        $sectionId = $request->query('section_id');
        if ($sectionId !== null && $sectionId !== '') {
            $where[] = '(es.section_id = :section_id OR es.section_id IS NULL)';
            $params['section_id'] = (int) $sectionId;
        }

        $subjectId = $request->query('subject_id');
        if ($subjectId !== null && $subjectId !== '') {
            $where[] = 'es.subject_id = :subject_id';
            $params['subject_id'] = (int) $subjectId;
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT es.*,
                       e.name AS exam_name,
                       ac.name AS class_name,
                       sec.name AS section_name,
                       sub.name AS subject_name,
                       sub.short_name AS subject_short_name,
                       sub.code AS subject_code,
                       sub.type AS subject_type
                FROM exam_schedules es
                LEFT JOIN exams e ON e.id = es.exam_id
                LEFT JOIN academic_classes ac ON ac.id = es.academic_class_id
                LEFT JOIN sections sec ON sec.id = es.section_id
                LEFT JOIN exam_subjects sub ON sub.id = es.subject_id
                WHERE {$whereSql}
                ORDER BY es.exam_date ASC, es.start_time ASC, sub.sort_order ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::success($items);
    }

    public function show(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $pdo = Database::pdo();

        $stmt = $pdo->prepare("SELECT es.*,
                                      e.name AS exam_name,
                                      ac.name AS class_name,
                                      sec.name AS section_name,
                                      sub.name AS subject_name,
                                      sub.short_name AS subject_short_name,
                                      sub.type AS subject_type
                               FROM exam_schedules es
                               LEFT JOIN exams e ON e.id = es.exam_id
                               LEFT JOIN academic_classes ac ON ac.id = es.academic_class_id
                               LEFT JOIN sections sec ON sec.id = es.section_id
                               LEFT JOIN exam_subjects sub ON sub.id = es.subject_id
                               WHERE es.id = :id AND es.deleted_at IS NULL
                               LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return Response::error('Exam schedule entry not found.', 404);
        }

        return Response::success($row);
    }

    public function store(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'exam_id'           => 'required|integer',
            'academic_class_id' => 'required|integer',
            'subject_id'        => 'required|integer',
            'exam_date'         => 'required|date',
            'min_marks'         => 'required|numeric',
            'max_marks'         => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $pdo = Database::pdo();
        try {
            $stmt = $pdo->prepare("INSERT INTO exam_schedules (exam_id, academic_class_id, section_id, subject_id, exam_date, start_time, end_time, room_no, min_marks, max_marks, created_at, updated_at)
                                   VALUES (:exam_id, :academic_class_id, :section_id, :subject_id, :exam_date, :start_time, :end_time, :room_no, :min_marks, :max_marks, NOW(), NOW())");
            $stmt->execute([
                'exam_id'           => (int) $data['exam_id'],
                'academic_class_id' => (int) $data['academic_class_id'],
                'section_id'        => !empty($data['section_id']) ? (int) $data['section_id'] : null,
                'subject_id'        => (int) $data['subject_id'],
                'exam_date'         => (string) $data['exam_date'],
                'start_time'        => !empty($data['start_time']) ? (string) $data['start_time'] : null,
                'end_time'          => !empty($data['end_time']) ? (string) $data['end_time'] : null,
                'room_no'           => !empty($data['room_no']) ? (string) $data['room_no'] : null,
                'min_marks'         => (float) $data['min_marks'],
                'max_marks'         => (float) $data['max_marks'],
            ]);

            return Response::success(['id' => (int) $pdo->lastInsertId(), 'message' => 'Exam schedule added successfully.'], 201);
        } catch (Throwable $e) {
            return Response::error('Failed to create schedule: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $data = $request->all();

        $validator = Validator::make($data, [
            'exam_id'           => 'required|integer',
            'academic_class_id' => 'required|integer',
            'subject_id'        => 'required|integer',
            'exam_date'         => 'required|date',
            'min_marks'         => 'required|numeric',
            'max_marks'         => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $pdo = Database::pdo();
        try {
            $stmt = $pdo->prepare("UPDATE exam_schedules
                                   SET exam_id = :exam_id,
                                       academic_class_id = :academic_class_id,
                                       section_id = :section_id,
                                       subject_id = :subject_id,
                                       exam_date = :exam_date,
                                       start_time = :start_time,
                                       end_time = :end_time,
                                       room_no = :room_no,
                                       min_marks = :min_marks,
                                       max_marks = :max_marks,
                                       updated_at = NOW()
                                   WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([
                'id'                => $id,
                'exam_id'           => (int) $data['exam_id'],
                'academic_class_id' => (int) $data['academic_class_id'],
                'section_id'        => !empty($data['section_id']) ? (int) $data['section_id'] : null,
                'subject_id'        => (int) $data['subject_id'],
                'exam_date'         => (string) $data['exam_date'],
                'start_time'        => !empty($data['start_time']) ? (string) $data['start_time'] : null,
                'end_time'          => !empty($data['end_time']) ? (string) $data['end_time'] : null,
                'room_no'           => !empty($data['room_no']) ? (string) $data['room_no'] : null,
                'min_marks'         => (float) $data['min_marks'],
                'max_marks'         => (float) $data['max_marks'],
            ]);

            return Response::success(['id' => $id, 'message' => 'Exam schedule updated successfully.']);
        } catch (Throwable $e) {
            return Response::error('Failed to update schedule: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $pdo = Database::pdo();

        $stmt = $pdo->prepare("UPDATE exam_schedules SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return Response::success(['message' => 'Exam schedule deleted successfully.']);
    }

    /**
     * Batch save schedule items for an exam and class.
     */
    public function batchSave(Request $request): Response
    {
        $data = $request->all();
        $examId = (int) ($data['exam_id'] ?? 0);
        $classId = (int) ($data['academic_class_id'] ?? $data['class_id'] ?? 0);
        $schedules = isset($data['schedules']) && is_array($data['schedules']) ? $data['schedules'] : [];

        if (!$examId || !$classId) {
            return Response::error('exam_id and academic_class_id are required.', 422);
        }

        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();
            $insertStmt = $pdo->prepare("INSERT INTO exam_schedules (exam_id, academic_class_id, section_id, subject_id, exam_date, start_time, end_time, room_no, min_marks, max_marks, created_at, updated_at)
                                         VALUES (:exam_id, :academic_class_id, :section_id, :subject_id, :exam_date, :start_time, :end_time, :room_no, :min_marks, :max_marks, NOW(), NOW())");

            $updateStmt = $pdo->prepare("UPDATE exam_schedules
                                         SET exam_date = :exam_date,
                                             start_time = :start_time,
                                             end_time = :end_time,
                                             room_no = :room_no,
                                             min_marks = :min_marks,
                                             max_marks = :max_marks,
                                             section_id = :section_id,
                                             updated_at = NOW()
                                         WHERE id = :id AND deleted_at IS NULL");

            $savedCount = 0;
            foreach ($schedules as $item) {
                $subId = (int) ($item['subject_id'] ?? 0);
                $examDate = (string) ($item['exam_date'] ?? '');
                if (!$subId || !$examDate) {
                    continue;
                }

                $secId = !empty($item['section_id']) ? (int) $item['section_id'] : null;
                $startTime = !empty($item['start_time']) ? (string) $item['start_time'] : null;
                $endTime = !empty($item['end_time']) ? (string) $item['end_time'] : null;
                $roomNo = !empty($item['room_no']) ? (string) $item['room_no'] : null;
                $minMarks = isset($item['min_marks']) ? (float) $item['min_marks'] : 33.00;
                $maxMarks = isset($item['max_marks']) ? (float) $item['max_marks'] : 100.00;

                if (!empty($item['id'])) {
                    $updateStmt->execute([
                        'id'         => (int) $item['id'],
                        'exam_date'  => $examDate,
                        'start_time' => $startTime,
                        'end_time'   => $endTime,
                        'room_no'    => $roomNo,
                        'min_marks'  => $minMarks,
                        'max_marks'  => $maxMarks,
                        'section_id' => $secId,
                    ]);
                } else {
                    $insertStmt->execute([
                        'exam_id'           => $examId,
                        'academic_class_id' => $classId,
                        'section_id'        => $secId,
                        'subject_id'        => $subId,
                        'exam_date'         => $examDate,
                        'start_time'        => $startTime,
                        'end_time'          => $endTime,
                        'room_no'           => $roomNo,
                        'min_marks'         => $minMarks,
                        'max_marks'         => $maxMarks,
                    ]);
                }
                $savedCount++;
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            return Response::success(['message' => 'Exam schedules saved successfully.', 'saved_count' => $savedCount]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to batch save schedule: ' . $e->getMessage(), 500);
        }
    }
}

