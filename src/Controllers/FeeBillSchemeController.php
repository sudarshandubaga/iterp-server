<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\FeeBillScheme;
use Iterp\Models\FeeBillSchemeSlab;
use Iterp\Models\FeeBillSchemeAmount;
use Throwable;

/**
 * Controller for Fee Bill Scheme (manages 3 tables in hierarchy).
 *
 * 1. Parent: fee_bill_schemes
 *    - firm_id
 *    - session_id
 *    - name
 *    - slab (numeric: count of slabs)
 *    - description
 * 2. Child: fee_bill_scheme_slabs
 *    - fee_bill_scheme_id
 *    - slab_no
 *    - due_date
 * 3. Grandchild: fee_bill_scheme_amounts
 *    - fee_bill_scheme_slab_id
 *    - fee_head_id
 *    - amount
 */
class FeeBillSchemeController
{
    public function index(Request $request): Response
    {
        $query = FeeBillScheme::query();
        $query->whereNull('deleted_at');

        // Scoping by firm_id & session_id
        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $query->where('firm_id', (int) $firmId);
        }

        $sessionId = $request->query('session_id') ?? $request->query('academic_year_id');
        if ($sessionId !== null && $sessionId !== '') {
            $query->where('session_id', (int) $sessionId);
        }

        // Search by name or description
        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '') {
            $term = trim((string) $search);
            if (is_numeric($term)) {
                $query->where('slab', (int) $term);
            } else {
                $query->search(['name', 'description'], $term);
            }
        }

        $query->orderBy('id', 'DESC');

        // Pagination if requested
        $paginate = $request->query('page') !== null || $request->query('per_page') !== null;
        if ($paginate) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(100, max(1, (int) $request->query('per_page', 10)));

            $total = $query->count();
            $query->limit($perPage)->offset(($page - 1) * $perPage);

            $items = [];
            foreach ($query->get() as $model) {
                /** @var FeeBillScheme $model */
                $items[] = $model->toResponseArray();
            }

            return Response::success([
                'items'       => $items,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $total === 0 ? 1 : (int) ceil($total / $perPage),
            ]);
        }

        $items = [];
        foreach ($query->get() as $model) {
            /** @var FeeBillScheme $model */
            $items[] = $model->toResponseArray();
        }

        return Response::success($items);
    }

    public function show(Request $request, array $context, array $params): Response
    {
        $model = FeeBillScheme::find((int) $params['id']);
        if ($model === null || !empty($model->deleted_at)) {
            return Response::error('Fee bill scheme not found.', 404);
        }
        return Response::success($model->toResponseArray());
    }

    public function store(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'name'       => 'required|string|max:255',
            'slab'       => 'required|integer|min:1',
            'session_id' => 'nullable|integer|exists:academic_years,id',
            'firm_id'    => 'nullable|integer|exists:firms,id',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $slabs = $this->normalizeSlabsData($data);
        $slabCount = (int) $data['slab'];
        if ($slabCount <= 0 && $slabs !== []) {
            $slabCount = count($slabs);
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $scheme = new FeeBillScheme();
            $scheme->fill([
                'name'        => trim((string) $data['name']),
                'slab'        => $slabCount,
                'session_id'  => !empty($data['session_id']) ? (int) $data['session_id'] : null,
                'firm_id'     => !empty($data['firm_id']) ? (int) $data['firm_id'] : null,
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            ]);

            if (!$scheme->save()) {
                throw new \RuntimeException('Failed to save fee bill scheme header.');
            }

            $schemeId = $scheme->id();

            // Save slabs and nested amounts
            $this->saveSlabs($schemeId, $slabs, $slabCount);

            // Assign sections if passed
            if (isset($data['section_ids']) && is_array($data['section_ids']) && !empty($data['section_ids'])) {
                $secIds = array_filter(array_map('intval', $data['section_ids']));
                if (!empty($secIds)) {
                    $secPlaceholders = implode(',', array_fill(0, count($secIds), '?'));
                    $paramsSec = array_merge([$schemeId], $secIds);
                    $pdo->prepare("UPDATE sections SET fee_bill_scheme_id = ? WHERE id IN ({$secPlaceholders})")
                        ->execute($paramsSec);
                }
            }

            $pdo->commit();

            return Response::success($scheme->toResponseArray(), 'Fee bill scheme created successfully.', 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to create fee bill scheme: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, array $context, array $params): Response
    {
        $scheme = FeeBillScheme::find((int) $params['id']);
        if ($scheme === null || !empty($scheme->deleted_at)) {
            return Response::error('Fee bill scheme not found.', 404);
        }

        $data = $request->all();

        $validator = Validator::make($data, [
            'name'       => 'required|string|max:255',
            'slab'       => 'required|integer|min:1',
            'session_id' => 'nullable|integer|exists:academic_years,id',
            'firm_id'    => 'nullable|integer|exists:firms,id',
        ]);

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $slabCount = (int) $data['slab'];
            $slabs = $this->normalizeSlabsData($data);
            if ($slabCount <= 0 && $slabs !== []) {
                $slabCount = count($slabs);
            }

            $scheme->fill([
                'name'        => trim((string) $data['name']),
                'slab'        => $slabCount,
                'session_id'  => !empty($data['session_id']) ? (int) $data['session_id'] : null,
                'firm_id'     => !empty($data['firm_id']) ? (int) $data['firm_id'] : null,
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            ]);

            if (!$scheme->save()) {
                throw new \RuntimeException('Failed to update fee bill scheme header.');
            }

            $schemeId = $scheme->id();

            // If slabs or amounts passed, update child slabs and amounts
            if (isset($data['slabs']) || isset($data['amounts'])) {
                // Deleting slabs will CASCADE delete amounts from fee_bill_scheme_amounts
                $delStmt = $pdo->prepare('DELETE FROM fee_bill_scheme_slabs WHERE fee_bill_scheme_id = :scheme_id');
                $delStmt->execute(['scheme_id' => $schemeId]);

                $this->saveSlabs($schemeId, $slabs, $slabCount);
            }

            // Update assigned sections if explicitly passed
            if (isset($data['section_ids']) && is_array($data['section_ids'])) {
                $pdo->prepare('UPDATE sections SET fee_bill_scheme_id = NULL WHERE fee_bill_scheme_id = :scheme_id')
                    ->execute(['scheme_id' => $schemeId]);
                $secIds = array_filter(array_map('intval', $data['section_ids']));
                if (!empty($secIds)) {
                    $secPlaceholders = implode(',', array_fill(0, count($secIds), '?'));
                    $paramsSec = array_merge([$schemeId], $secIds);
                    $pdo->prepare("UPDATE sections SET fee_bill_scheme_id = ? WHERE id IN ({$secPlaceholders})")
                        ->execute($paramsSec);
                }
            }

            $pdo->commit();

            return Response::success($scheme->toResponseArray(), 'Fee bill scheme updated successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to update fee bill scheme: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, array $context, array $params): Response
    {
        $scheme = FeeBillScheme::find((int) $params['id']);
        if ($scheme === null || !empty($scheme->deleted_at)) {
            return Response::error('Fee bill scheme not found.', 404);
        }

        // Soft delete parent
        Database::pdo()->prepare(
            'UPDATE fee_bill_schemes SET deleted_at = NOW() WHERE id = :id'
        )->execute(['id' => $scheme->id()]);

        return Response::success(null, 'Fee bill scheme deleted successfully.');
    }

    /**
     * Get sections and students assignments for a specific fee bill scheme.
     */
    public function getAssignments(Request $request, array $context, array $params): Response
    {
        $schemeId = (int) $params['id'];
        $scheme = FeeBillScheme::find($schemeId);
        if ($scheme === null || !empty($scheme->deleted_at)) {
            return Response::error('Fee bill scheme not found.', 404);
        }

        $pdo = Database::pdo();

        $sessionId = $request->query('session_id');
        if ($sessionId === null || $sessionId === '') {
            $sessionId = $scheme->session_id;
        } else {
            $sessionId = (int) $sessionId;
        }

        $classId = $request->query('class_id');
        $classId = ($classId !== null && $classId !== '') ? (int) $classId : null;

        // 1. Fetch Academic Classes for filters
        $classesStmt = $pdo->query('SELECT id, name, short_name FROM academic_classes WHERE deleted_at IS NULL ORDER BY sort_order ASC, name ASC');
        $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // 2. Fetch Sections with assignment info
        $secSql = 'SELECT sec.id, sec.name, sec.class_id, sec.academic_year_id, sec.fee_bill_scheme_id,
                          ac.name AS class_name,
                          ay.name AS session_name,
                          fbs.name AS current_scheme_name,
                          CASE WHEN sec.fee_bill_scheme_id = :scheme_id THEN 1 ELSE 0 END AS is_assigned,
                          (SELECT COUNT(*) FROM students st WHERE st.section_id = sec.id AND st.deleted_at IS NULL) AS student_count
                   FROM sections sec
                   LEFT JOIN academic_classes ac ON ac.id = sec.class_id
                   LEFT JOIN academic_years ay ON ay.id = sec.academic_year_id
                   LEFT JOIN fee_bill_schemes fbs ON fbs.id = sec.fee_bill_scheme_id
                   WHERE sec.deleted_at IS NULL';
        $secParams = ['scheme_id' => $schemeId];

        if (!empty($sessionId)) {
            $secSql .= ' AND (sec.academic_year_id = :session_id OR sec.academic_year_id IS NULL)';
            $secParams['session_id'] = $sessionId;
        }
        if (!empty($classId)) {
            $secSql .= ' AND sec.class_id = :class_id';
            $secParams['class_id'] = $classId;
        }
        $secSql .= ' ORDER BY ac.sort_order ASC, ac.name ASC, sec.name ASC';

        $secStmt = $pdo->prepare($secSql);
        $secStmt->execute($secParams);
        $sections = $secStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // 3. Fetch Students for student tab
        $sectionFilter = $request->query('section_id');
        $sectionFilter = ($sectionFilter !== null && $sectionFilter !== '') ? (int) $sectionFilter : null;
        $search = trim((string) $request->query('search', ''));

        $stuSql = 'SELECT s.id AS student_id, u.id AS user_id,
                          u.first_name, u.middle_name, u.last_name, u.gender,
                          s.enrollment_number, s.scholar_number, s.roll_number,
                          s.section_id, sec.name AS section_name, sec.class_id, ac.name AS class_name,
                          s.fee_bill_scheme_id,
                          fbs.name AS student_scheme_name,
                          sec.fee_bill_scheme_id AS section_scheme_id,
                          sec_fbs.name AS section_scheme_name,
                          CASE WHEN s.fee_bill_scheme_id = :scheme_id THEN 1 ELSE 0 END AS is_directly_assigned,
                          CASE WHEN (s.fee_bill_scheme_id IS NULL OR s.fee_bill_scheme_id = 0) AND sec.fee_bill_scheme_id = :scheme_id THEN 1 ELSE 0 END AS is_inherited
                   FROM students s
                   JOIN users u ON u.id = s.user_id AND u.deleted_at IS NULL
                   LEFT JOIN sections sec ON sec.id = s.section_id
                   LEFT JOIN academic_classes ac ON ac.id = sec.class_id
                   LEFT JOIN fee_bill_schemes fbs ON fbs.id = s.fee_bill_scheme_id
                   LEFT JOIN fee_bill_schemes sec_fbs ON sec_fbs.id = sec.fee_bill_scheme_id
                   WHERE s.deleted_at IS NULL';
        $stuParams = ['scheme_id' => $schemeId];

        if (!empty($sessionId)) {
            $stuSql .= ' AND (s.academic_year_id = :session_id OR u.academic_year_id = :session_id OR sec.academic_year_id = :session_id)';
            $stuParams['session_id'] = $sessionId;
        }
        if (!empty($classId)) {
            $stuSql .= ' AND sec.class_id = :class_id';
            $stuParams['class_id'] = $classId;
        }
        if (!empty($sectionFilter)) {
            $stuSql .= ' AND s.section_id = :sec_filter';
            $stuParams['sec_filter'] = $sectionFilter;
        }
        if ($search !== '') {
            $stuSql .= ' AND (u.first_name LIKE :s1 OR u.middle_name LIKE :s2 OR u.last_name LIKE :s3 OR s.enrollment_number LIKE :s4 OR s.scholar_number LIKE :s5 OR s.roll_number LIKE :s6)';
            $term = '%' . $search . '%';
            $stuParams['s1'] = $term;
            $stuParams['s2'] = $term;
            $stuParams['s3'] = $term;
            $stuParams['s4'] = $term;
            $stuParams['s5'] = $term;
            $stuParams['s6'] = $term;
        }

        $stuSql .= ' ORDER BY ac.sort_order ASC, ac.name ASC, sec.name ASC, s.roll_number ASC, u.first_name ASC LIMIT 500';

        $stuStmt = $pdo->prepare($stuSql);
        $stuStmt->execute($stuParams);
        $students = $stuStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // 4. Overall stats for this scheme
        $secCntStmt = $pdo->prepare('SELECT COUNT(*) FROM sections WHERE fee_bill_scheme_id = :scheme_id AND deleted_at IS NULL');
        $secCntStmt->execute(['scheme_id' => $schemeId]);
        $assignedSecCount = (int) $secCntStmt->fetchColumn();

        $stuCntStmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE fee_bill_scheme_id = :scheme_id AND deleted_at IS NULL');
        $stuCntStmt->execute(['scheme_id' => $schemeId]);
        $assignedStuCount = (int) $stuCntStmt->fetchColumn();

        $inhCntStmt = $pdo->prepare('SELECT COUNT(*) FROM students st
                                     JOIN sections sec ON sec.id = st.section_id
                                     WHERE sec.fee_bill_scheme_id = :scheme_id
                                       AND st.fee_bill_scheme_id IS NULL
                                       AND st.deleted_at IS NULL
                                       AND sec.deleted_at IS NULL');
        $inhCntStmt->execute(['scheme_id' => $schemeId]);
        $inheritedStuCount = (int) $inhCntStmt->fetchColumn();

        return Response::success([
            'scheme'   => $scheme->toResponseArray(),
            'classes'  => $classes,
            'sections' => $sections,
            'students' => $students,
            'stats'    => [
                'assigned_sections_count' => $assignedSecCount,
                'assigned_students_count' => $assignedStuCount,
                'inherited_students_count'=> $inheritedStuCount,
                'total_covered_students'  => $assignedStuCount + $inheritedStuCount,
            ],
        ]);
    }

    /**
     * Bulk assign sections to a fee bill scheme.
     */
    public function assignSections(Request $request, array $context, array $params): Response
    {
        $schemeId = (int) $params['id'];
        $scheme = FeeBillScheme::find($schemeId);
        if ($scheme === null || !empty($scheme->deleted_at)) {
            return Response::error('Fee bill scheme not found.', 404);
        }

        $data = $request->all();
        $sectionIds = isset($data['section_ids']) && is_array($data['section_ids'])
            ? array_filter(array_map('intval', $data['section_ids']))
            : [];
        $unassignOthers = (bool) ($data['unassign_others'] ?? true);
        $sessionId = !empty($data['session_id']) ? (int) $data['session_id'] : ($scheme->session_id ?? null);

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            if ($unassignOthers) {
                // Clear this scheme from sections that aren't in sectionIds
                if (!empty($sessionId)) {
                    $clearSql = 'UPDATE sections SET fee_bill_scheme_id = NULL 
                                 WHERE fee_bill_scheme_id = :scheme_id AND academic_year_id = :session_id';
                    $pdo->prepare($clearSql)->execute(['scheme_id' => $schemeId, 'session_id' => $sessionId]);
                } else {
                    $clearSql = 'UPDATE sections SET fee_bill_scheme_id = NULL WHERE fee_bill_scheme_id = :scheme_id';
                    $pdo->prepare($clearSql)->execute(['scheme_id' => $schemeId]);
                }
            }

            if (!empty($sectionIds)) {
                $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
                $binds = array_merge([$schemeId], $sectionIds);
                $stmt = $pdo->prepare("UPDATE sections SET fee_bill_scheme_id = ? WHERE id IN ({$placeholders})");
                $stmt->execute($binds);
            }

            $pdo->commit();

            return Response::success([
                'assigned_count' => count($sectionIds),
            ], 'Sections successfully assigned to fee bill scheme.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Response::error('Failed to assign sections: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk assign or unassign students to a fee bill scheme.
     */
    public function assignStudents(Request $request, array $context, array $params): Response
    {
        $schemeId = (int) $params['id'];
        $scheme = FeeBillScheme::find($schemeId);
        if ($scheme === null || !empty($scheme->deleted_at)) {
            return Response::error('Fee bill scheme not found.', 404);
        }

        $data = $request->all();
        $studentIds = isset($data['student_ids']) && is_array($data['student_ids'])
            ? array_filter(array_map('intval', $data['student_ids']))
            : [];

        if (empty($studentIds)) {
            return Response::error('No students selected.', 422);
        }

        $action = (string) ($data['action'] ?? 'assign'); // 'assign' or 'unassign'
        $pdo = Database::pdo();

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

        if ($action === 'unassign') {
            // Setting students.fee_bill_scheme_id = NULL causes them to fall back to section scheme
            $sql = "UPDATE students SET fee_bill_scheme_id = NULL WHERE id IN ({$placeholders}) OR user_id IN ({$placeholders})";
            $binds = array_merge($studentIds, $studentIds);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($binds);
            $msg = 'Direct bill scheme removed from selected students. They will now inherit scheme from section.';
        } else {
            // Set student's fee_bill_scheme_id
            $sql = "UPDATE students SET fee_bill_scheme_id = ? WHERE id IN ({$placeholders}) OR user_id IN ({$placeholders})";
            $binds = array_merge([$schemeId], $studentIds, $studentIds);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($binds);
            $msg = 'Bill scheme successfully assigned to selected students.';
        }

        return Response::success([
            'updated_count' => count($studentIds),
            'action'        => $action,
        ], $msg);
    }

    /**
     * Normalize slabs data from either nested slabs structure or flat amounts array.
     */
    private function normalizeSlabsData(array $data): array
    {
        if (isset($data['slabs']) && is_array($data['slabs'])) {
            $slabs = [];
            foreach ($data['slabs'] as $idx => $s) {
                $slabNo = !empty($s['slab_no']) ? (int) $s['slab_no'] : ($idx + 1);
                $dueDate = !empty($s['due_date']) ? (string) $s['due_date'] : null;
                $amounts = [];

                if (isset($s['amounts']) && is_array($s['amounts'])) {
                    foreach ($s['amounts'] as $a) {
                        $amounts[] = [
                            'fee_head_id' => !empty($a['fee_head_id']) ? (int) $a['fee_head_id'] : null,
                            'amount'      => isset($a['amount']) ? (float) $a['amount'] : 0.00,
                        ];
                    }
                }

                $slabs[] = [
                    'slab_no'  => $slabNo,
                    'due_date' => $dueDate,
                    'amounts'  => $amounts,
                ];
            }
            return $slabs;
        }

        // Fallback: Legacy flat amounts array grouped by slab_no
        if (isset($data['amounts']) && is_array($data['amounts'])) {
            $slabsMap = [];
            foreach ($data['amounts'] as $idx => $amt) {
                $sNo = !empty($amt['slab_no']) ? (int) $amt['slab_no'] : ($idx + 1);
                if (!isset($slabsMap[$sNo])) {
                    $slabsMap[$sNo] = [
                        'slab_no'  => $sNo,
                        'due_date' => !empty($amt['due_date']) ? (string) $amt['due_date'] : null,
                        'amounts'  => [],
                    ];
                }
                $slabsMap[$sNo]['amounts'][] = [
                    'fee_head_id' => !empty($amt['fee_head_id']) ? (int) $amt['fee_head_id'] : null,
                    'amount'      => isset($amt['amount']) ? (float) $amt['amount'] : 0.00,
                ];
            }
            return array_values($slabsMap);
        }

        return [];
    }

    /**
     * Save child slabs and their grandchild amounts.
     */
    private function saveSlabs(int $schemeId, array $slabs, int $expectedCount = 0): void
    {
        $pdo = Database::pdo();

        $slabStmt = $pdo->prepare('
            INSERT INTO fee_bill_scheme_slabs (fee_bill_scheme_id, slab_no, due_date, created_at, updated_at)
            VALUES (:scheme_id, :slab_no, :due_date, NOW(), NOW())
        ');

        $amtStmt = $pdo->prepare('
            INSERT INTO fee_bill_scheme_amounts (fee_bill_scheme_slab_id, fee_head_id, amount, created_at, updated_at)
            VALUES (:slab_id, :fee_head_id, :amount, NOW(), NOW())
        ');

        $savedSlabNos = [];
        $seq = 1;

        foreach ($slabs as $slab) {
            $slabNo = !empty($slab['slab_no']) ? (int) $slab['slab_no'] : $seq;
            $dueDate = !empty($slab['due_date']) ? (string) $slab['due_date'] : null;

            $slabStmt->execute([
                'scheme_id' => $schemeId,
                'slab_no'   => $slabNo,
                'due_date'  => $dueDate,
            ]);

            $slabId = (int) $pdo->lastInsertId();
            $savedSlabNos[] = $slabNo;

            $amounts = isset($slab['amounts']) && is_array($slab['amounts']) ? $slab['amounts'] : [];
            foreach ($amounts as $amt) {
                $feeHeadId = !empty($amt['fee_head_id']) ? (int) $amt['fee_head_id'] : null;
                $amountVal = isset($amt['amount']) ? (float) $amt['amount'] : 0.00;

                // Save if head is selected or amount > 0
                if ($feeHeadId !== null || $amountVal > 0) {
                    $amtStmt->execute([
                        'slab_id'     => $slabId,
                        'fee_head_id' => $feeHeadId,
                        'amount'      => $amountVal,
                    ]);
                }
            }

            $seq++;
        }

        // If slab count requested is greater than provided slabs, fill remaining slabs
        if ($expectedCount > count($savedSlabNos)) {
            for ($i = count($savedSlabNos) + 1; $i <= $expectedCount; $i++) {
                if (!in_array($i, $savedSlabNos, true)) {
                    $slabStmt->execute([
                        'scheme_id' => $schemeId,
                        'slab_no'   => $i,
                        'due_date'  => null,
                    ]);
                }
            }
        }
    }
}
