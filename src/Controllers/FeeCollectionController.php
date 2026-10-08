<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\AcademicYear;
use Iterp\Models\FeeBillScheme;
use Iterp\Models\FeeCollection;
use PDO;

/**
 * Controller for Fee Collection & Fee Charge Management.
 */
class FeeCollectionController
{
    protected function rules(): array
    {
        return [
            'student_id'         => 'required|integer|exists:students,id',
            'session_id'         => 'nullable|integer|exists:academic_years,id',
            'firm_id'            => 'nullable|integer|exists:firms,id',
            'receipt_no'         => 'required|string|max:100',
            'payment_date'       => 'required|date',
            'payment_mode'       => 'required|string|max:50',
            'reference_no'       => 'nullable|string|max:100',
            'fee_bill_scheme_id' => 'nullable|integer|exists:fee_bill_schemes,id',
            'concession_id'      => 'nullable|integer|exists:fee_concessions,id',
            'subtotal_amount'    => 'required|numeric|min:0',
            'concession_amount'  => 'nullable|numeric|min:0',
            'discount_type'      => 'nullable|in:percentage,fixed',
            'discount_value'     => 'nullable|numeric|min:0',
            'discount_amount'    => 'nullable|numeric|min:0',
            'discount_reason'    => 'nullable|string|max:255',
            'total_amount'       => 'required|numeric|min:0',
            'paid_amount'        => 'required|numeric|min:0',
            'balance_amount'     => 'nullable|numeric|min:0',
            'remarks'            => 'nullable|string',
            'items'              => 'required|array',
            'slab_ids'           => 'nullable|array',
        ];
    }

    public function index(Request $request): Response
    {
        $pdo = Database::pdo();
        $bindings = [];
        $where = 'WHERE fc.deleted_at IS NULL';

        $studentId = $request->query('student_id');
        if ($studentId !== null && $studentId !== '') {
            $bindings['student_id'] = (int) $studentId;
            $where .= ' AND fc.student_id = :student_id';
        }

        $sessionId = $request->query('session_id') ?: $request->query('academic_year_id');
        if ($sessionId !== null && $sessionId !== '') {
            $bindings['session_id'] = (int) $sessionId;
            $where .= ' AND fc.session_id = :session_id';
        }

        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $bindings['firm_id'] = (int) $firmId;
            $where .= ' AND fc.firm_id = :firm_id';
        }

        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '') {
            $term = '%' . trim((string) $search) . '%';
            $bindings['s1'] = $term;
            $bindings['s2'] = $term;
            $bindings['s3'] = $term;
            $bindings['s4'] = $term;
            $where .= ' AND (fc.receipt_no LIKE :s1 OR fc.reference_no LIKE :s2 OR u.first_name LIKE :s3 OR s.enrollment_number LIKE :s4)';
        }

        $sql = "SELECT fc.*,
                       CONCAT(u.first_name, IF(u.last_name IS NOT NULL AND u.last_name != '', CONCAT(' ', u.last_name), '')) AS student_name,
                       s.enrollment_number, s.roll_number,
                       sec.name AS section_name, ac.name AS class_name,
                       ay.name AS session_name, f.name AS firm_name,
                       fbs.name AS scheme_name,
                       fcon.name AS concession_name
                FROM fee_collections fc
                JOIN students s ON s.id = fc.student_id
                JOIN users u ON u.id = s.user_id
                LEFT JOIN sections sec ON sec.id = s.section_id
                LEFT JOIN academic_classes ac ON ac.id = sec.class_id
                LEFT JOIN academic_years ay ON ay.id = fc.session_id
                LEFT JOIN firms f ON f.id = fc.firm_id
                LEFT JOIN fee_bill_schemes fbs ON fbs.id = fc.fee_bill_scheme_id
                LEFT JOIN fee_concessions fcon ON fcon.id = fc.concession_id
                {$where}
                ORDER BY fc.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::success($rows);
    }

    public function show(Request $request, array $context = [], array $params = []): Response
    {
        $id = (int) ($params['id'] ?? $context['id'] ?? 0);
        $pdo = Database::pdo();

        $sql = "SELECT fc.*,
                       CONCAT(u.first_name, IF(u.last_name IS NOT NULL AND u.last_name != '', CONCAT(' ', u.last_name), '')) AS student_name,
                       u.email AS student_email, u.mobile_no AS student_mobile,
                       s.enrollment_number, s.scholar_number, s.roll_number,
                       s.father_mobile_no, s.father_email,
                       reg.father_name,
                       sec.name AS section_name, ac.name AS class_name,
                       ay.name AS session_name, f.name AS firm_name,
                       fbs.name AS scheme_name,
                       fcon.name AS concession_name
                FROM fee_collections fc
                JOIN students s ON s.id = fc.student_id
                JOIN users u ON u.id = s.user_id
                LEFT JOIN registrations reg ON reg.student_id = s.id
                LEFT JOIN sections sec ON sec.id = s.section_id
                LEFT JOIN academic_classes ac ON ac.id = sec.class_id
                LEFT JOIN academic_years ay ON ay.id = fc.session_id
                LEFT JOIN firms f ON f.id = fc.firm_id
                LEFT JOIN fee_bill_schemes fbs ON fbs.id = fc.fee_bill_scheme_id
                LEFT JOIN fee_concessions fcon ON fcon.id = fc.concession_id
                WHERE fc.id = :id AND fc.deleted_at IS NULL
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $collection = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$collection) {
            return Response::error('Fee collection record not found.', 404);
        }

        // Load items
        $itemStmt = $pdo->prepare(
            'SELECT fci.*, fh.name AS fee_head_name
             FROM fee_collection_items fci
             LEFT JOIN fee_heads fh ON fh.id = fci.fee_head_id
             WHERE fci.fee_collection_id = :id
             ORDER BY fci.id ASC'
        );
        $itemStmt->execute(['id' => $id]);
        $collection['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        // Load slabs
        $slabStmt = $pdo->prepare(
            'SELECT fcs.*, s.slab_no, s.due_date
             FROM fee_collection_slabs fcs
             JOIN fee_bill_scheme_slabs s ON s.id = fcs.fee_bill_scheme_slab_id
             WHERE fcs.fee_collection_id = :id
             ORDER BY s.slab_no ASC'
        );
        $slabStmt->execute(['id' => $id]);
        $collection['slabs'] = $slabStmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::success($collection);
    }

    public function store(Request $request): Response
    {
        $data = $request->all();
        $validator = Validator::make($data, $this->rules());

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            // Check receipt uniqueness
            $checkStmt = $pdo->prepare(
                'SELECT id FROM fee_collections WHERE receipt_no = :no AND deleted_at IS NULL LIMIT 1'
            );
            $checkStmt->execute(['no' => $data['receipt_no']]);
            if ($checkStmt->fetchColumn()) {
                // If collision, auto re-generate
                $data['receipt_no'] = FeeCollection::generateNextNumber(
                    !empty($data['session_id']) ? (int) $data['session_id'] : null
                );
            }

            // Insert into fee_collections
            $insertSql = 'INSERT INTO fee_collections (
                firm_id, session_id, student_id, receipt_no, payment_date, payment_mode,
                reference_no, fee_bill_scheme_id, concession_id, subtotal_amount,
                concession_amount, discount_type, discount_value, discount_amount,
                discount_reason, total_amount, paid_amount, balance_amount, remarks, created_by,
                created_at, updated_at
            ) VALUES (
                :firm_id, :session_id, :student_id, :receipt_no, :payment_date, :payment_mode,
                :reference_no, :fee_bill_scheme_id, :concession_id, :subtotal_amount,
                :concession_amount, :discount_type, :discount_value, :discount_amount,
                :discount_reason, :total_amount, :paid_amount, :balance_amount, :remarks, :created_by,
                NOW(), NOW()
            )';

            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                'firm_id'            => !empty($data['firm_id']) ? (int) $data['firm_id'] : null,
                'session_id'         => !empty($data['session_id']) ? (int) $data['session_id'] : null,
                'student_id'         => (int) $data['student_id'],
                'receipt_no'         => (string) $data['receipt_no'],
                'payment_date'       => (string) $data['payment_date'],
                'payment_mode'       => (string) ($data['payment_mode'] ?? 'Cash'),
                'reference_no'       => !empty($data['reference_no']) ? (string) $data['reference_no'] : null,
                'fee_bill_scheme_id' => !empty($data['fee_bill_scheme_id']) ? (int) $data['fee_bill_scheme_id'] : null,
                'concession_id'      => !empty($data['concession_id']) ? (int) $data['concession_id'] : null,
                'subtotal_amount'    => (float) ($data['subtotal_amount'] ?? 0),
                'concession_amount'  => (float) ($data['concession_amount'] ?? 0),
                'discount_type'      => !empty($data['discount_type']) ? (string) $data['discount_type'] : null,
                'discount_value'     => (float) ($data['discount_value'] ?? 0),
                'discount_amount'    => (float) ($data['discount_amount'] ?? 0),
                'discount_reason'    => !empty($data['discount_reason']) ? (string) $data['discount_reason'] : null,
                'total_amount'       => (float) ($data['total_amount'] ?? 0),
                'paid_amount'        => (float) ($data['paid_amount'] ?? 0),
                'balance_amount'     => (float) ($data['balance_amount'] ?? 0),
                'remarks'            => !empty($data['remarks']) ? (string) $data['remarks'] : null,
                'created_by'         => \Iterp\Core\Auth::user($request)?->id ?? null,
            ]);

            $collectionId = (int) $pdo->lastInsertId();

            // Insert items
            if (!empty($data['items']) && is_array($data['items'])) {
                $itemStmt = $pdo->prepare('INSERT INTO fee_collection_items (
                    fee_collection_id, fee_bill_scheme_slab_id, fee_head_id,
                    amount, concession, discount_amount, total, paid_amount, created_at, updated_at
                ) VALUES (
                    :fee_collection_id, :slab_id, :fee_head_id,
                    :amount, :concession, :discount_amount, :total, :paid_amount, NOW(), NOW()
                )');

                $collectionDiscount = (float) ($data['discount_amount'] ?? 0);
                $sumItemDiscounts = 0.0;
                $sumItemsNet = 0.0;
                foreach ($data['items'] as $it) {
                    $sumItemDiscounts += (float) ($it['discount_amount'] ?? 0);
                    $sumItemsNet += max(0.0, (float) ($it['total'] ?? $it['amount'] ?? 0));
                }

                $needAutoDistributeDiscount = ($collectionDiscount > 0.001 && $sumItemDiscounts <= 0.001);
                $allocatedDiscount = 0.0;
                $itemsCount = count($data['items']);
                $itemIndex = 0;

                foreach ($data['items'] as $item) {
                    $itemIndex++;
                    if (empty($item['fee_head_id'])) {
                        continue;
                    }

                    $headAmount = (float) ($item['amount'] ?? 0);
                    $headConcession = (float) ($item['concession'] ?? 0);
                    $headTotal = (float) ($item['total'] ?? max(0.0, $headAmount - $headConcession));
                    $headPaid = (float) ($item['paid_amount'] ?? 0);
                    $headDiscount = (float) ($item['discount_amount'] ?? 0);

                    if ($needAutoDistributeDiscount) {
                        if ($itemIndex === $itemsCount) {
                            $headDiscount = round(max(0.0, $collectionDiscount - $allocatedDiscount), 2);
                        } else {
                            $share = $sumItemsNet > 0 ? ($headTotal / $sumItemsNet) : (1.0 / max(1, $itemsCount));
                            $headDiscount = round($collectionDiscount * $share, 2);
                            $allocatedDiscount += $headDiscount;
                        }
                    }

                    $itemStmt->execute([
                        'fee_collection_id' => $collectionId,
                        'slab_id'           => !empty($item['fee_bill_scheme_slab_id']) ? (int) $item['fee_bill_scheme_slab_id'] : null,
                        'fee_head_id'       => (int) $item['fee_head_id'],
                        'amount'            => $headAmount,
                        'concession'        => $headConcession,
                        'discount_amount'   => $headDiscount,
                        'total'             => $headTotal,
                        'paid_amount'       => $headPaid,
                    ]);
                }
            }

            // Insert slabs
            if (!empty($data['slab_ids']) && is_array($data['slab_ids'])) {
                $slabStmt = $pdo->prepare('INSERT INTO fee_collection_slabs (
                    fee_collection_id, fee_bill_scheme_slab_id, created_at
                ) VALUES (:fee_collection_id, :slab_id, NOW())');

                foreach ($data['slab_ids'] as $slabId) {
                    $slabStmt->execute([
                        'fee_collection_id' => $collectionId,
                        'slab_id'           => (int) $slabId,
                    ]);
                }
            }

            $pdo->commit();

            return Response::success(
                $this->show($request, [], ['id' => $collectionId])->getBody()['data'] ?? null,
                'Fee collected successfully. Receipt generated.',
                201
            );
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return Response::error('Failed to record fee collection: ' . $e->getMessage(), 500);
        }
    }

    public function nextNumber(Request $request): Response
    {
        $yearId = $request->query('session_id') ?: $request->query('academic_year_id');
        $num = FeeCollection::generateNextNumber($yearId ? (int) $yearId : null);
        return Response::success(['next_number' => $num]);
    }

    /**
     * Get complete dues, schemes, slabs, concession rules, and past payments for a student.
     */
    public function studentDues(Request $request): Response
    {
        $pdo = Database::pdo();
        $studentId = (int) $request->query('student_id');

        if ($studentId <= 0) {
            return Response::error('Student ID is required.', 422);
        }

        // 1. Fetch student info
        $studentSql = "SELECT s.id AS student_id, s.user_id,
                              s.enrollment_number, s.scholar_number, s.roll_number,
                              s.father_email, s.father_mobile_no, s.photo,
                              s.section_id, s.academic_year_id, s.student_category_id,
                              s.fee_bill_scheme_id AS assigned_scheme_id,
                              s.fee_concession_id AS assigned_concession_id,
                              u.first_name, u.middle_name, u.last_name, u.gender, u.dob,
                              u.email AS student_email, u.mobile_no AS student_mobile_no,
                              u.firm_id,
                              sec.name AS section_name, sec.class_id,
                              sec.fee_bill_scheme_id AS section_scheme_id,
                              ac.name AS class_name,
                              sc.name AS student_category_name,
                              reg.father_name, reg.mother_name
                       FROM students s
                       JOIN users u ON u.id = s.user_id
                       LEFT JOIN sections sec ON sec.id = s.section_id
                       LEFT JOIN academic_classes ac ON ac.id = sec.class_id
                       LEFT JOIN student_categories sc ON sc.id = s.student_category_id
                       LEFT JOIN registrations reg ON reg.student_id = s.id
                       WHERE (s.id = :id1 OR s.user_id = :id2) AND s.deleted_at IS NULL AND u.deleted_at IS NULL
                       LIMIT 1";

        $studentStmt = $pdo->prepare($studentSql);
        $studentStmt->execute(['id1' => $studentId, 'id2' => $studentId]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return Response::error('Student not found.', 404);
        }

        $sessionId = (int) ($request->query('session_id') ?: $student['academic_year_id'] ?: 0);
        $firmId = (int) ($student['firm_id'] ?: 0);

        // 2. Fetch available Fee Bill Schemes
        $schemesSql = 'SELECT id, name, slab, session_id, firm_id, description
                       FROM fee_bill_schemes
                       WHERE deleted_at IS NULL';
        $schemesBindings = [];
        if ($sessionId > 0) {
            $schemesSql .= ' AND (session_id = :session_id OR session_id IS NULL)';
            $schemesBindings['session_id'] = $sessionId;
        }
        $schemesSql .= ' ORDER BY id DESC';
        $schemesStmt = $pdo->prepare($schemesSql);
        $schemesStmt->execute($schemesBindings);
        $schemes = $schemesStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Fetch available Concession Policies
        $concessionsSql = 'SELECT id, name, session_id, firm_id, description
                           FROM fee_concessions
                           WHERE deleted_at IS NULL';
        $concessionsBindings = [];
        if ($sessionId > 0) {
            $concessionsSql .= ' AND (session_id = :session_id OR session_id IS NULL)';
            $concessionsBindings['session_id'] = $sessionId;
        }
        $concessionsSql .= ' ORDER BY id DESC';
        $concessionsStmt = $pdo->prepare($concessionsSql);
        $concessionsStmt->execute($concessionsBindings);
        $concessions = $concessionsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Determine active scheme with precedence:
        // Priority 1: User explicitly supplied fee_bill_scheme_id in request query
        // Priority 2: Student directly assigned scheme (student.fee_bill_scheme_id) -> Highest priority
        // Priority 3: Student's section assigned scheme (sec.fee_bill_scheme_id) -> Secondary priority
        // Priority 4: Fallback to the first available scheme
        $requestedSchemeId = (int) $request->query('fee_bill_scheme_id');
        $studentSchemeId   = (int) ($student['assigned_scheme_id'] ?? 0);
        $sectionSchemeId   = (int) ($student['section_scheme_id'] ?? 0);

        $selectedSchemeId = 0;
        $schemeSource = 'default';

        if ($requestedSchemeId > 0) {
            $selectedSchemeId = $requestedSchemeId;
            $schemeSource = 'manual';
        } elseif ($studentSchemeId > 0) {
            $selectedSchemeId = $studentSchemeId;
            $schemeSource = 'student';
        } elseif ($sectionSchemeId > 0) {
            $selectedSchemeId = $sectionSchemeId;
            $schemeSource = 'section';
        } elseif (!empty($schemes)) {
            $selectedSchemeId = (int) $schemes[0]['id'];
            $schemeSource = 'default';
        }

        // Decorate schemes with priority badges
        foreach ($schemes as &$sc) {
            $scId = (int) $sc['id'];
            $sc['is_student_scheme'] = ($scId === $studentSchemeId);
            $sc['is_section_scheme'] = ($scId === $sectionSchemeId);
            if ($scId === $studentSchemeId) {
                $sc['priority_label'] = 'Assigned to Student (Prior)';
            } elseif ($scId === $sectionSchemeId) {
                $sc['priority_label'] = 'Assigned to Section (' . ($student['section_name'] ?? 'Section') . ')';
            } else {
                $sc['priority_label'] = null;
            }
        }
        unset($sc);

        // Determine active concession
        $selectedConcessionId = (int) $request->query('concession_id');
        if ($selectedConcessionId <= 0) {
            $selectedConcessionId = (int) ($student['assigned_concession_id'] ?? 0);
        }

        // 4. Fetch concession items if concession is active
        $concessionItems = [];
        if ($selectedConcessionId > 0) {
            $ciStmt = $pdo->prepare(
                'SELECT fee_head_id, amount_type, amount_value
                 FROM fee_concession_items
                 WHERE concession_id = :c_id'
            );
            $ciStmt->execute(['c_id' => $selectedConcessionId]);
            $concessionItems = $ciStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Map concession items by fee_head_id
        $concessionMap = [];
        foreach ($concessionItems as $ci) {
            $concessionMap[(int) $ci['fee_head_id']] = [
                'type'  => $ci['amount_type'],
                'value' => (float) $ci['amount_value'],
            ];
        }

        // 5. Fetch Slabs and amounts for active scheme
        $slabs = [];
        if ($selectedSchemeId > 0) {
            $slabStmt = $pdo->prepare(
                'SELECT id, fee_bill_scheme_id, slab_no, due_date
                 FROM fee_bill_scheme_slabs
                 WHERE fee_bill_scheme_id = :scheme_id
                 ORDER BY slab_no ASC'
            );
            $slabStmt->execute(['scheme_id' => $selectedSchemeId]);
            $rawSlabs = $slabStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch all past collections for this student
            $pastColSql = 'SELECT fc.id, fc.discount_amount, fc.subtotal_amount, fc.total_amount, fc.paid_amount, fc.balance_amount
                           FROM fee_collections fc
                           WHERE fc.student_id = :student_id AND fc.deleted_at IS NULL';
            $pastColStmt = $pdo->prepare($pastColSql);
            $pastColStmt->execute(['student_id' => $studentId]);
            $pastCollections = $pastColStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch slab associations for past collections
            $pastSlabLinksStmt = $pdo->prepare(
                'SELECT fcs.fee_collection_id, fcs.fee_bill_scheme_slab_id
                 FROM fee_collection_slabs fcs
                 JOIN fee_collections fc ON fc.id = fcs.fee_collection_id
                 WHERE fc.student_id = :student_id AND fc.deleted_at IS NULL'
            );
            $pastSlabLinksStmt->execute(['student_id' => $studentId]);
            $pastSlabLinks = $pastSlabLinksStmt->fetchAll(PDO::FETCH_ASSOC);

            $colToSlabs = [];
            foreach ($pastSlabLinks as $link) {
                $cId = (int) $link['fee_collection_id'];
                $sId = (int) $link['fee_bill_scheme_slab_id'];
                if (!isset($colToSlabs[$cId])) {
                    $colToSlabs[$cId] = [];
                }
                $colToSlabs[$cId][] = $sId;
            }

            // Fetch collection items for this student
            $pastItemsStmt = $pdo->prepare(
                'SELECT fci.id, fci.fee_collection_id, fci.fee_bill_scheme_slab_id,
                        fci.fee_head_id, fci.amount, fci.concession, fci.discount_amount,
                        fci.total, fci.paid_amount
                 FROM fee_collection_items fci
                 JOIN fee_collections fc ON fc.id = fci.fee_collection_id
                 WHERE fc.student_id = :student_id AND fc.deleted_at IS NULL'
            );
            $pastItemsStmt->execute(['student_id' => $studentId]);
            $pastItems = $pastItemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $itemsByCol = [];
            foreach ($pastItems as $item) {
                $cId = (int) $item['fee_collection_id'];
                $itemsByCol[$cId][] = $item;
            }

            // Aggregators for payments and discounts
            $paidBySlabHead = [];
            $discountBySlabHead = [];
            $paidBySlab = [];
            $discountBySlab = [];

            foreach ($pastCollections as $pc) {
                $cId = (int) $pc['id'];
                $cDiscount = (float) ($pc['discount_amount'] ?? 0);
                $slabsForCol = $colToSlabs[$cId] ?? [];
                $itemsForCol = $itemsByCol[$cId] ?? [];

                // Check if items have discount_amount populated
                $itemDiscountSum = 0.0;
                $itemNetSum = 0.0;
                foreach ($itemsForCol as $it) {
                    $itemDiscountSum += (float) ($it['discount_amount'] ?? 0);
                    $itemNetSum += max(0.0, (float) ($it['total'] ?? $it['amount'] ?? 0));
                }

                $needDistributeDiscount = ($cDiscount > 0.001 && $itemDiscountSum <= 0.001);
                $remDiscountToDistribute = $cDiscount;
                $itCount = count($itemsForCol);

                foreach ($itemsForCol as $idx => $it) {
                    $slabId = !empty($it['fee_bill_scheme_slab_id']) ? (int) $it['fee_bill_scheme_slab_id'] : 0;
                    if ($slabId === 0 && count($slabsForCol) === 1) {
                        $slabId = $slabsForCol[0];
                    }

                    $headId = (int) $it['fee_head_id'];
                    $paid = (float) ($it['paid_amount'] ?? 0);
                    $disc = (float) ($it['discount_amount'] ?? 0);

                    if ($needDistributeDiscount) {
                        if ($idx === $itCount - 1) {
                            $disc = max(0.0, $remDiscountToDistribute);
                        } else {
                            $headNet = max(0.0, (float) ($it['total'] ?? $it['amount'] ?? 0));
                            $share = $itemNetSum > 0 ? ($headNet / $itemNetSum) : (1.0 / max(1, $itCount));
                            $disc = round($cDiscount * $share, 2);
                            $remDiscountToDistribute -= $disc;
                        }
                    }

                    if ($slabId > 0) {
                        $paidBySlabHead[$slabId][$headId] = ($paidBySlabHead[$slabId][$headId] ?? 0.0) + $paid;
                        $discountBySlabHead[$slabId][$headId] = ($discountBySlabHead[$slabId][$headId] ?? 0.0) + $disc;

                        $paidBySlab[$slabId] = ($paidBySlab[$slabId] ?? 0.0) + $paid;
                        $discountBySlab[$slabId] = ($discountBySlab[$slabId] ?? 0.0) + $disc;
                    }
                }

                // If collection had slabs linked but no items, attribute evenly to slabs
                if (empty($itemsForCol) && !empty($slabsForCol)) {
                    $cPaid = (float) ($pc['paid_amount'] ?? 0);
                    $eachPaid = round($cPaid / count($slabsForCol), 2);
                    $eachDisc = round($cDiscount / count($slabsForCol), 2);
                    foreach ($slabsForCol as $sId) {
                        $paidBySlab[$sId] = ($paidBySlab[$sId] ?? 0.0) + $eachPaid;
                        $discountBySlab[$sId] = ($discountBySlab[$sId] ?? 0.0) + $eachDisc;
                    }
                }
            }

            foreach ($rawSlabs as $slab) {
                $sId = (int) $slab['id'];

                // Load fee head amounts for this slab
                $amtStmt = $pdo->prepare(
                    'SELECT a.id, a.fee_head_id, a.amount, fh.name AS fee_head_name
                     FROM fee_bill_scheme_amounts a
                     LEFT JOIN fee_heads fh ON fh.id = a.fee_head_id
                     WHERE a.fee_bill_scheme_slab_id = :slab_id
                     ORDER BY a.id ASC'
                );
                $amtStmt->execute(['slab_id' => $sId]);
                $amounts = $amtStmt->fetchAll(PDO::FETCH_ASSOC);

                $slabSubtotal = 0.0;
                $slabConcession = 0.0;
                $slabProcessedAmounts = [];

                foreach ($amounts as $amt) {
                    $headId = (int) $amt['fee_head_id'];
                    $baseAmt = (float) $amt['amount'];
                    $concessionAmt = 0.0;

                    if (isset($concessionMap[$headId])) {
                        $rule = $concessionMap[$headId];
                        if (strcasecmp($rule['type'], 'Percentage') === 0) {
                            $concessionAmt = round(($baseAmt * $rule['value']) / 100.0, 2);
                        } else {
                            $concessionAmt = min($baseAmt, (float) $rule['value']);
                        }
                    }

                    $netHeadAmt = max(0.0, round($baseAmt - $concessionAmt, 2));
                    $slabSubtotal += $baseAmt;
                    $slabConcession += $concessionAmt;

                    $headPaid = round($paidBySlabHead[$sId][$headId] ?? 0.0, 2);
                    $headDiscount = round($discountBySlabHead[$sId][$headId] ?? 0.0, 2);
                    $headSettled = round($headPaid + $headDiscount, 2);
                    $headBalance = max(0.0, round($netHeadAmt - $headSettled, 2));

                    $slabProcessedAmounts[] = [
                        'fee_head_id'     => $headId,
                        'fee_head_name'   => $amt['fee_head_name'] ?: "Head #{$headId}",
                        'amount'          => $baseAmt,
                        'concession'      => $concessionAmt,
                        'total'           => $netHeadAmt,
                        'paid_amount'     => $headPaid,
                        'discount_amount' => $headDiscount,
                        'settled_amount'  => $headSettled,
                        'balance'         => $headBalance,
                    ];
                }

                $slabNet = max(0.0, round($slabSubtotal - $slabConcession, 2));
                $slabPaidSoFar = round($paidBySlab[$sId] ?? 0.0, 2);
                $slabDiscountSoFar = round($discountBySlab[$sId] ?? 0.0, 2);
                $slabSettled = round($slabPaidSoFar + $slabDiscountSoFar, 2);
                $slabBalance = max(0.0, round($slabNet - $slabSettled, 2));

                $isPaid = ($slabBalance <= 0.001 && $slabNet > 0);
                $isPartial = ($slabSettled > 0.001 && $slabBalance > 0.001);

                $today = date('Y-m-d');
                $isOverdue = (!$isPaid && !empty($slab['due_date']) && $slab['due_date'] < $today);

                $slabs[] = [
                    'id'                 => $sId,
                    'fee_bill_scheme_id' => (int) $slab['fee_bill_scheme_id'],
                    'slab_no'            => (int) $slab['slab_no'],
                    'due_date'           => $slab['due_date'],
                    'amounts'            => $slabProcessedAmounts,
                    'subtotal'           => $slabSubtotal,
                    'concession'         => $slabConcession,
                    'total'              => $slabNet,
                    'paid_amount'        => $slabPaidSoFar,
                    'discount_amount'    => $slabDiscountSoFar,
                    'settled_amount'     => $slabSettled,
                    'balance'            => $slabBalance,
                    'is_paid'            => $isPaid,
                    'is_partial'         => $isPartial,
                    'is_overdue'         => $isOverdue,
                    'status'             => $isPaid ? 'paid' : ($isPartial ? 'partial' : ($isOverdue ? 'overdue' : 'due')),
                ];
            }
        }

        // 6. Recent collections for this student
        $historyStmt = $pdo->prepare(
            'SELECT id, receipt_no, payment_date, payment_mode, reference_no,
                    subtotal_amount, concession_amount, discount_amount,
                    total_amount, paid_amount, balance_amount, created_at
             FROM fee_collections
             WHERE student_id = :student_id AND deleted_at IS NULL
             ORDER BY id DESC LIMIT 10'
        );
        $historyStmt->execute(['student_id' => $studentId]);
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::success([
            'student'              => $student,
            'schemes'              => $schemes,
            'concessions'          => $concessions,
            'active_scheme_id'     => $selectedSchemeId,
            'scheme_source'        => $schemeSource,
            'student_scheme_id'    => $studentSchemeId,
            'section_scheme_id'    => $sectionSchemeId,
            'active_concession_id' => $selectedConcessionId,
            'slabs'                => $slabs,
            'past_collections'     => $history,
        ]);
    }
}
