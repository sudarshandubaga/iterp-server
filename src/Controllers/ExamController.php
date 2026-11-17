<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\Exam;
use Iterp\Models\ExamClassAllocation;
use Throwable;

/**
 * Controller for Exam Master & Class Allocations.
 */
class ExamController
{
    public function index(Request $request): Response
    {
        $pdo = Database::pdo();

        $where = ['e.deleted_at IS NULL'];
        $params = [];

        $ayId = $request->query('academic_year_id');
        if ($ayId !== null && $ayId !== '') {
            $where[] = 'e.academic_year_id = :ay_id';
            $params['ay_id'] = (int) $ayId;
        }

        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $where[] = 'e.firm_id = :firm_id';
            $params['firm_id'] = (int) $firmId;
        }

        $typeId = $request->query('exam_type_id');
        if ($typeId !== null && $typeId !== '') {
            $where[] = 'e.exam_type_id = :type_id';
            $params['type_id'] = (int) $typeId;
        }

        $status = $request->query('status');
        if ($status !== null && $status !== '') {
            $where[] = 'e.status = :status';
            $params['status'] = (string) $status;
        }

        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '') {
            $where[] = '(e.name LIKE :search OR et.name LIKE :search OR et.short_name LIKE :search)';
            $params['search'] = '%' . trim((string) $search) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT e.*,
                       et.name AS exam_type_name,
                       et.short_name AS exam_type_short_name,
                       ay.name AS academic_year_name,
                       f.name AS firm_name,
                       (SELECT COUNT(DISTINCT eca.academic_class_id) FROM exam_class_allocations eca WHERE eca.exam_id = e.id) AS allocated_classes_count,
                       (SELECT COUNT(eca.id) FROM exam_class_allocations eca WHERE eca.exam_id = e.id) AS allocated_sections_count
                FROM exams e
                LEFT JOIN exam_types et ON et.id = e.exam_type_id
                LEFT JOIN academic_years ay ON ay.id = e.academic_year_id
                LEFT JOIN firms f ON f.id = e.firm_id
                WHERE {$whereSql}
                ORDER BY e.sort_order ASC, e.start_date ASC, e.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::success($items);
    }

    public function show(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $pdo = Database::pdo();

        $stmt = $pdo->prepare("SELECT e.*,
                                      et.name AS exam_type_name,
                                      et.short_name AS exam_type_short_name,
                                      ay.name AS academic_year_name,
                                      f.name AS firm_name
                               FROM exams e
                               LEFT JOIN exam_types et ON et.id = e.exam_type_id
                               LEFT JOIN academic_years ay ON ay.id = e.academic_year_id
                               LEFT JOIN firms f ON f.id = e.firm_id
                               WHERE e.id = :id AND e.deleted_at IS NULL
                               LIMIT 1");
        $stmt->execute(['id' => $id]);
        $exam = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$exam) {
            return Response::error('Exam not found.', 404);
        }

        // Fetch allocated sections & classes
        $allocStmt = $pdo->prepare("SELECT eca.id, eca.academic_class_id, eca.section_id,
                                           ac.name AS class_name,
                                           sec.name AS section_name
                                    FROM exam_class_allocations eca
                                    LEFT JOIN academic_classes ac ON ac.id = eca.academic_class_id
                                    LEFT JOIN sections sec ON sec.id = eca.section_id
                                    WHERE eca.exam_id = :exam_id
                                    ORDER BY ac.sort_order ASC, ac.name ASC, sec.name ASC");
        $allocStmt->execute(['exam_id' => $id]);
        $exam['allocations'] = $allocStmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::success($exam);
    }

    public function store(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'academic_year_id' => 'required|integer',
            'exam_type_id'      => 'required|integer',
            'name'              => 'required|string|max:255',
            'start_date'        => 'required|date',
            'end_date'          => 'required|date',
            'status'            => 'nullable|string',
            'description'       => 'nullable|string',
            'firm_id'           => 'nullable|integer',
            'sort_order'        => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $sortOrder = isset($data['sort_order']) && $data['sort_order'] !== ''
                ? (int) $data['sort_order']
                : (int) $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM exams WHERE deleted_at IS NULL")->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO exams (academic_year_id, exam_type_id, name, start_date, end_date, status, description, firm_id, sort_order, created_at, updated_at)
                                   VALUES (:academic_year_id, :exam_type_id, :name, :start_date, :end_date, :status, :description, :firm_id, :sort_order, NOW(), NOW())");
            $stmt->execute([
                'academic_year_id' => (int) $data['academic_year_id'],
                'exam_type_id'     => (int) $data['exam_type_id'],
                'name'             => trim((string) $data['name']),
                'start_date'       => (string) $data['start_date'],
                'end_date'         => (string) $data['end_date'],
                'status'           => !empty($data['status']) ? (string) $data['status'] : 'upcoming',
                'description'      => !empty($data['description']) ? (string) $data['description'] : null,
                'firm_id'          => !empty($data['firm_id']) ? (int) $data['firm_id'] : null,
                'sort_order'       => $sortOrder,
            ]);

            $examId = (int) $pdo->lastInsertId();

            // If section_ids passed during create, allocate them
            if (!empty($data['section_ids']) && is_array($data['section_ids'])) {
                $this->saveSectionAllocations($pdo, $examId, $data['section_ids']);
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            return Response::success(['id' => $examId, 'message' => 'Exam created successfully.'], 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to create exam: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $data = $request->all();

        $validator = Validator::make($data, [
            'academic_year_id' => 'required|integer',
            'exam_type_id'      => 'required|integer',
            'name'              => 'required|string|max:255',
            'start_date'        => 'required|date',
            'end_date'          => 'required|date',
            'status'            => 'nullable|string',
            'description'       => 'nullable|string',
            'firm_id'           => 'nullable|integer',
            'sort_order'        => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            $sortOrder = isset($data['sort_order']) && $data['sort_order'] !== '' ? (int) $data['sort_order'] : null;

            $stmt = $pdo->prepare("UPDATE exams SET academic_year_id = :academic_year_id,
                                                   exam_type_id = :exam_type_id,
                                                   name = :name,
                                                   start_date = :start_date,
                                                   end_date = :end_date,
                                                   status = :status,
                                                   description = :description,
                                                   firm_id = :firm_id,
                                                   sort_order = COALESCE(:sort_order, sort_order),
                                                   updated_at = NOW()
                                   WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([
                'id'               => $id,
                'academic_year_id' => (int) $data['academic_year_id'],
                'exam_type_id'     => (int) $data['exam_type_id'],
                'name'             => trim((string) $data['name']),
                'start_date'       => (string) $data['start_date'],
                'end_date'         => (string) $data['end_date'],
                'status'           => !empty($data['status']) ? (string) $data['status'] : 'upcoming',
                'description'      => !empty($data['description']) ? (string) $data['description'] : null,
                'firm_id'          => !empty($data['firm_id']) ? (int) $data['firm_id'] : null,
                'sort_order'       => $sortOrder,
            ]);

            // If section_ids provided in payload, update allocations
            if (isset($data['section_ids']) && is_array($data['section_ids'])) {
                $this->saveSectionAllocations($pdo, $id, $data['section_ids']);
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            return Response::success(['id' => $id, 'message' => 'Exam updated successfully.']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to update exam: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $pdo = Database::pdo();

        $stmt = $pdo->prepare("UPDATE exams SET deleted_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return Response::success(['message' => 'Exam deleted successfully.']);
    }

    /**
     * Batch update sort_order for drag-and-drop exam reordering.
     * Accepts:
     *   { "order": [id1, id2, ...] } or { "ids": [id1, id2, ...] }
     *   or
     *   { "items": [ { "id": 1, "sort_order": 1 }, ... ] }
     */
    public function reorder(Request $request): Response
    {
        $data = $request->all();
        $pdo = Database::pdo();

        $orderIds = $data['order'] ?? $data['ids'] ?? null;
        $items = $data['items'] ?? null;

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE exams SET sort_order = :sort_order, updated_at = NOW() WHERE id = :id");

            // Mode 1: simple array of ordered IDs: [5, 2, 8, 1]
            if (is_array($orderIds) && !empty($orderIds)) {
                $pos = 1;
                foreach ($orderIds as $id) {
                    $stmt->execute([
                        'sort_order' => $pos++,
                        'id'         => (int) $id,
                    ]);
                }
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                return Response::success(['updated' => count($orderIds)], 200);
            }

            // Mode 2: array of items: [{ id: 5, sort_order: 1 }, ...]
            if (is_array($items) && !empty($items)) {
                $count = 0;
                foreach ($items as $item) {
                    if (isset($item['id'], $item['sort_order'])) {
                        $stmt->execute([
                            'sort_order' => (int) $item['sort_order'],
                            'id'         => (int) $item['id'],
                        ]);
                        $count++;
                    }
                }
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                return Response::success(['updated' => $count], 200);
            }

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Invalid reorder payload. Provide `order` array of IDs or `items` array.', 422);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to update exam sort order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get allocations for an exam, alongside full roster of classes & sections.
     */
    public function getAllocations(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $pdo = Database::pdo();

        // 1. Fetch exam
        $examStmt = $pdo->prepare("SELECT e.id, e.name, e.academic_year_id, e.status, ay.name AS academic_year_name
                                   FROM exams e
                                   LEFT JOIN academic_years ay ON ay.id = e.academic_year_id
                                   WHERE e.id = :id AND e.deleted_at IS NULL
                                   LIMIT 1");
        $examStmt->execute(['id' => $id]);
        $exam = $examStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$exam) {
            return Response::error('Exam not found.', 404);
        }

        // 2. Fetch allocated section IDs
        $allocStmt = $pdo->prepare("SELECT academic_class_id, section_id FROM exam_class_allocations WHERE exam_id = :exam_id");
        $allocStmt->execute(['exam_id' => $id]);
        $allocatedRows = $allocStmt->fetchAll(\PDO::FETCH_ASSOC);

        $allocatedSectionIds = array_map('intval', array_column($allocatedRows, 'section_id'));
        $allocatedClassIds = array_unique(array_map('intval', array_column($allocatedRows, 'academic_class_id')));

        // 3. Fetch all classes and their sections
        $classesStmt = $pdo->prepare("SELECT id, name, short_name, sort_order FROM academic_classes WHERE deleted_at IS NULL ORDER BY sort_order ASC, name ASC");
        $classesStmt->execute();
        $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC);

        $sectionsStmt = $pdo->prepare("SELECT s.id, s.name, s.class_id, s.academic_year_id
                                       FROM sections s
                                       WHERE s.deleted_at IS NULL
                                       ORDER BY s.name ASC");
        $sectionsStmt->execute();
        $allSections = $sectionsStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Group sections by class_id
        $sectionsByClass = [];
        foreach ($allSections as $sec) {
            $cid = (int) $sec['class_id'];
            if (!isset($sectionsByClass[$cid])) {
                $sectionsByClass[$cid] = [];
            }
            $sec['is_allocated'] = in_array((int) $sec['id'], $allocatedSectionIds, true);
            $sectionsByClass[$cid][] = $sec;
        }

        $classRoster = [];
        $allocatedClassesOnly = [];
        foreach ($classes as $c) {
            $cid = (int) $c['id'];
            $c['sections'] = $sectionsByClass[$cid] ?? [];
            $c['is_allocated'] = in_array($cid, $allocatedClassIds, true);
            $c['total_sections'] = count($c['sections']);
            $c['allocated_sections'] = count(array_filter($c['sections'], fn($s) => $s['is_allocated']));
            $classRoster[] = $c;

            if ($c['is_allocated'] && $c['allocated_sections'] > 0) {
                $cCopy = $c;
                $cCopy['sections'] = array_values(array_filter($c['sections'], fn($s) => $s['is_allocated']));
                $allocatedClassesOnly[] = $cCopy;
            }
        }

        return Response::success([
            'exam'                  => $exam,
            'allocated_section_ids' => $allocatedSectionIds,
            'allocated_class_ids'   => array_values($allocatedClassIds),
            'allocated_classes'     => $allocatedClassesOnly,
            'classes'               => $classRoster,
        ]);
    }

    /**
     * Get only the allocated classes and their allocated sections for an exam.
     */
    public function getAllocatedClasses(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $pdo = Database::pdo();

        $sql = "SELECT DISTINCT ac.id AS class_id, ac.name AS class_name, ac.short_name AS class_short_name, ac.sort_order,
                                sec.id AS section_id, sec.name AS section_name
                FROM exam_class_allocations eca
                INNER JOIN academic_classes ac ON ac.id = eca.academic_class_id AND ac.deleted_at IS NULL
                INNER JOIN sections sec ON sec.id = eca.section_id AND sec.deleted_at IS NULL
                WHERE eca.exam_id = :exam_id
                ORDER BY ac.sort_order ASC, ac.name ASC, sec.name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['exam_id' => $id]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $cid = (int) $row['class_id'];
            if (!isset($grouped[$cid])) {
                $grouped[$cid] = [
                    'id'         => $cid,
                    'name'       => $row['class_name'],
                    'short_name' => $row['class_short_name'],
                    'sort_order' => (int) $row['sort_order'],
                    'sections'   => [],
                ];
            }
            $grouped[$cid]['sections'][] = [
                'id'       => (int) $row['section_id'],
                'name'     => $row['section_name'],
                'class_id' => $cid,
            ];
        }

        return Response::success(array_values($grouped));
    }

    /**
     * Batch save class/section allocations for an exam.
     */
    public function saveAllocations(Request $request, array $context, array $params): Response
    {
        $id = (int) $params['id'];
        $pdo = Database::pdo();

        $examStmt = $pdo->prepare("SELECT id FROM exams WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $examStmt->execute(['id' => $id]);
        if (!$examStmt->fetch()) {
            return Response::error('Exam not found.', 404);
        }

        $data = $request->all();
        $sectionIds = isset($data['section_ids']) && is_array($data['section_ids'])
            ? array_filter(array_map('intval', $data['section_ids']))
            : [];

        try {
            $pdo->beginTransaction();
            $this->saveSectionAllocations($pdo, $id, $sectionIds);
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            return Response::success([
                'message'                  => 'Class allocations saved successfully.',
                'allocated_sections_count' => count($sectionIds),
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to save allocations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Internal helper to sync section allocations.
     */
    private function saveSectionAllocations(\PDO $pdo, int $examId, array $sectionIds): void
    {
        // 1. Delete existing allocations
        $delStmt = $pdo->prepare("DELETE FROM exam_class_allocations WHERE exam_id = :exam_id");
        $delStmt->execute(['exam_id' => $examId]);

        if (empty($sectionIds)) {
            return;
        }

        // 2. Fetch class_id for each section
        $inPlaceholders = implode(',', array_fill(0, count($sectionIds), '?'));
        $secStmt = $pdo->prepare("SELECT id, class_id FROM sections WHERE id IN ({$inPlaceholders})");
        $secStmt->execute(array_values($sectionIds));
        $secRows = $secStmt->fetchAll(\PDO::FETCH_ASSOC);

        $insertStmt = $pdo->prepare("INSERT INTO exam_class_allocations (exam_id, academic_class_id, section_id, created_at, updated_at)
                                     VALUES (:exam_id, :academic_class_id, :section_id, NOW(), NOW())");

        foreach ($secRows as $row) {
            $insertStmt->execute([
                'exam_id'           => $examId,
                'academic_class_id' => (int) $row['class_id'],
                'section_id'        => (int) $row['id'],
            ]);
        }
    }
}

