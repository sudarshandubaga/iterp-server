<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;

/**
 * Controller providing Admission Reports:
 * - Collection Report (Revenue from prospectus + registration fees)
 * - Analysis Report (Funnel, conversion rates, demographic distributions)
 * - Admission Strength (Class-wise capacity vs enrolled strength)
 */
class AdmissionReportController
{
    /**
     * GET /api/admission/reports/collection
     */
    public function collectionReport(Request $request): Response
    {
        $pdo = Database::pdo();
        $academicYearId = $request->query('academic_year_id');
        $firmId = $request->query('firm_id');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $source = $request->query('source', 'all'); // 'all', 'prospectus', 'registration'
        $paymentMode = $request->query('payment_mode');

        // Common filter fragments
        $pWhere = ['p.deleted_at IS NULL'];
        $pBindings = [];
        $rWhere = ['r.deleted_at IS NULL'];
        $rBindings = [];

        if ($academicYearId !== null && $academicYearId !== '') {
            $pWhere[] = 'p.academic_year_id = :p_ay';
            $pBindings['p_ay'] = (int) $academicYearId;
            $rWhere[] = 'r.academic_year_id = :r_ay';
            $rBindings['r_ay'] = (int) $academicYearId;
        }

        if ($firmId !== null && $firmId !== '') {
            $pWhere[] = 'p.firm_id = :p_firm';
            $pBindings['p_firm'] = (int) $firmId;
            $rWhere[] = 'r.firm_id = :r_firm';
            $rBindings['r_firm'] = (int) $firmId;
        }

        if ($fromDate !== null && $fromDate !== '') {
            $pWhere[] = 'p.issue_date >= :p_fdate';
            $pBindings['p_fdate'] = (string) $fromDate;
            $rWhere[] = 'r.registration_date >= :r_fdate';
            $rBindings['r_fdate'] = (string) $fromDate;
        }

        if ($toDate !== null && $toDate !== '') {
            $pWhere[] = 'p.issue_date <= :p_tdate';
            $pBindings['p_tdate'] = (string) $toDate;
            $rWhere[] = 'r.registration_date <= :r_tdate';
            $rBindings['r_tdate'] = (string) $toDate;
        }

        if ($paymentMode !== null && $paymentMode !== '') {
            $pWhere[] = 'p.payment_mode = :p_pmode';
            $pBindings['p_pmode'] = (string) $paymentMode;
            $rWhere[] = 'r.payment_mode = :r_pmode';
            $rBindings['r_pmode'] = (string) $paymentMode;
        }

        // Summary queries
        $pWhereSql = implode(' AND ', $pWhere);
        $rWhereSql = implode(' AND ', $rWhere);

        $prospectusTotal = 0.0;
        $prospectusCount = 0;
        if ($source === 'all' || $source === 'prospectus') {
            $pSumStmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM prospectus p WHERE {$pWhereSql}");
            $pSumStmt->execute($pBindings);
            $pRes = $pSumStmt->fetch(\PDO::FETCH_ASSOC);
            $prospectusCount = (int) ($pRes['count'] ?? 0);
            $prospectusTotal = (float) ($pRes['total'] ?? 0);
        }

        $registrationTotal = 0.0;
        $registrationCount = 0;
        if ($source === 'all' || $source === 'registration') {
            $rSumStmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(registration_fee), 0) as total FROM registrations r WHERE {$rWhereSql} AND (r.payment_status = 'paid' OR r.registration_fee > 0)");
            $rSumStmt->execute($rBindings);
            $rRes = $rSumStmt->fetch(\PDO::FETCH_ASSOC);
            $registrationCount = (int) ($rRes['count'] ?? 0);
            $registrationTotal = (float) ($rRes['total'] ?? 0);
        }

        $grandTotal = $prospectusTotal + $registrationTotal;
        $totalTransactions = $prospectusCount + $registrationCount;

        // Payment mode breakdown
        $modes = [];
        if ($source === 'all' || $source === 'prospectus') {
            $pModeStmt = $pdo->prepare("SELECT payment_mode, COUNT(*) as count, SUM(amount) as total FROM prospectus p WHERE {$pWhereSql} GROUP BY payment_mode");
            $pModeStmt->execute($pBindings);
            foreach ($pModeStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $m = $row['payment_mode'] ?: 'Unknown';
                $modes[$m] = ($modes[$m] ?? 0.0) + (float) $row['total'];
            }
        }
        if ($source === 'all' || $source === 'registration') {
            $rModeStmt = $pdo->prepare("SELECT payment_mode, COUNT(*) as count, SUM(registration_fee) as total FROM registrations r WHERE {$rWhereSql} AND (r.payment_status = 'paid' OR r.registration_fee > 0) GROUP BY payment_mode");
            $rModeStmt->execute($rBindings);
            foreach ($rModeStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $m = $row['payment_mode'] ?: 'Unknown';
                $modes[$m] = ($modes[$m] ?? 0.0) + (float) $row['total'];
            }
        }

        $paymentModeBreakdown = [];
        foreach ($modes as $mode => $amount) {
            $paymentModeBreakdown[] = [
                'mode'       => $mode,
                'amount'     => round($amount, 2),
                'percentage' => $grandTotal > 0 ? round(($amount / $grandTotal) * 100, 1) : 0,
            ];
        }

        // Timeline breakdown (daily)
        $timelineMap = [];
        if ($source === 'all' || $source === 'prospectus') {
            $pTimeStmt = $pdo->prepare("SELECT issue_date as `date`, SUM(amount) as total FROM prospectus p WHERE {$pWhereSql} GROUP BY issue_date ORDER BY issue_date ASC");
            $pTimeStmt->execute($pBindings);
            foreach ($pTimeStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $d = $row['date'];
                if (!isset($timelineMap[$d])) {
                    $timelineMap[$d] = ['date' => $d, 'prospectus' => 0.0, 'registration' => 0.0, 'total' => 0.0];
                }
                $timelineMap[$d]['prospectus'] += (float) $row['total'];
                $timelineMap[$d]['total'] += (float) $row['total'];
            }
        }
        if ($source === 'all' || $source === 'registration') {
            $rTimeStmt = $pdo->prepare("SELECT registration_date as `date`, SUM(registration_fee) as total FROM registrations r WHERE {$rWhereSql} AND (r.payment_status = 'paid' OR r.registration_fee > 0) GROUP BY registration_date ORDER BY registration_date ASC");
            $rTimeStmt->execute($rBindings);
            foreach ($rTimeStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $d = $row['date'];
                if (!isset($timelineMap[$d])) {
                    $timelineMap[$d] = ['date' => $d, 'prospectus' => 0.0, 'registration' => 0.0, 'total' => 0.0];
                }
                $timelineMap[$d]['registration'] += (float) $row['total'];
                $timelineMap[$d]['total'] += (float) $row['total'];
            }
        }
        ksort($timelineMap);
        $timeline = array_values($timelineMap);

        // Itemized transactions list
        $transactions = [];
        if ($source === 'all' || $source === 'prospectus') {
            $pTxStmt = $pdo->prepare(
                "SELECT p.id, p.prospectus_no as receipt_no, p.issue_date as `date`,
                        p.candidate_name, ac.name as class_name, 'Prospectus' as fee_type,
                        p.payment_mode, p.reference_no, p.amount, p.status
                 FROM prospectus p
                 LEFT JOIN academic_classes ac ON ac.id = p.class_id
                 WHERE {$pWhereSql}
                 ORDER BY p.issue_date DESC, p.id DESC
                 LIMIT 50"
            );
            $pTxStmt->execute($pBindings);
            foreach ($pTxStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $row['amount'] = (float) $row['amount'];
                $transactions[] = $row;
            }
        }
        if ($source === 'all' || $source === 'registration') {
            $rTxStmt = $pdo->prepare(
                "SELECT r.id, r.registration_no as receipt_no, r.registration_date as `date`,
                        CONCAT(r.first_name, ' ', COALESCE(r.last_name, '')) as candidate_name,
                        ac.name as class_name, 'Registration' as fee_type,
                        r.payment_mode, r.transaction_no as reference_no,
                        r.registration_fee as amount, r.payment_status as status
                 FROM registrations r
                 LEFT JOIN academic_classes ac ON ac.id = r.class_id
                 WHERE {$rWhereSql} AND (r.payment_status = 'paid' OR r.registration_fee > 0)
                 ORDER BY r.registration_date DESC, r.id DESC
                 LIMIT 50"
            );
            $rTxStmt->execute($rBindings);
            foreach ($rTxStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $row['amount'] = (float) $row['amount'];
                $transactions[] = $row;
            }
        }

        // Sort combined transactions by date desc
        usort($transactions, fn($a, $b) => strcmp($b['date'] . $b['id'], $a['date'] . $a['id']));
        $transactions = array_slice($transactions, 0, 100);

        return Response::success([
            'summary' => [
                'grand_total'        => round($grandTotal, 2),
                'total_transactions' => $totalTransactions,
                'prospectus_total'   => round($prospectusTotal, 2),
                'prospectus_count'   => $prospectusCount,
                'registration_total' => round($registrationTotal, 2),
                'registration_count' => $registrationCount,
            ],
            'payment_modes' => $paymentModeBreakdown,
            'timeline'      => $timeline,
            'transactions'  => $transactions,
        ]);
    }

    /**
     * GET /api/admission/reports/analysis
     */
    public function analysisReport(Request $request): Response
    {
        $pdo = Database::pdo();
        $academicYearId = $request->query('academic_year_id');
        $firmId = $request->query('firm_id');
        $classId = $request->query('class_id');

        $pWhere = ['p.deleted_at IS NULL'];
        $pBindings = [];
        $rWhere = ['r.deleted_at IS NULL'];
        $rBindings = [];

        if ($academicYearId !== null && $academicYearId !== '') {
            $pWhere[] = 'p.academic_year_id = :p_ay';
            $pBindings['p_ay'] = (int) $academicYearId;
            $rWhere[] = 'r.academic_year_id = :r_ay';
            $rBindings['r_ay'] = (int) $academicYearId;
        }

        if ($firmId !== null && $firmId !== '') {
            $pWhere[] = 'p.firm_id = :p_firm';
            $pBindings['p_firm'] = (int) $firmId;
            $rWhere[] = 'r.firm_id = :r_firm';
            $rBindings['r_firm'] = (int) $firmId;
        }

        if ($classId !== null && $classId !== '') {
            $pWhere[] = 'p.class_id = :p_class';
            $pBindings['p_class'] = (int) $classId;
            $rWhere[] = 'r.class_id = :r_class';
            $rBindings['r_class'] = (int) $classId;
        }

        $pWhereSql = implode(' AND ', $pWhere);
        $rWhereSql = implode(' AND ', $rWhere);

        // Funnel counts
        $pStmt = $pdo->prepare("SELECT COUNT(*) FROM prospectus p WHERE {$pWhereSql}");
        $pStmt->execute($pBindings);
        $totalProspectus = (int) $pStmt->fetchColumn();

        $rStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations r WHERE {$rWhereSql}");
        $rStmt->execute($rBindings);
        $totalRegistrations = (int) $rStmt->fetchColumn();

        $admittedStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations r WHERE {$rWhereSql} AND r.status = 'admitted'");
        $admittedStmt->execute($rBindings);
        $totalAdmitted = (int) $admittedStmt->fetchColumn();

        $shortlistedStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations r WHERE {$rWhereSql} AND r.status IN ('shortlisted', 'under_review')");
        $shortlistedStmt->execute($rBindings);
        $totalShortlisted = (int) $shortlistedStmt->fetchColumn();

        $rejectedStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations r WHERE {$rWhereSql} AND r.status = 'rejected'");
        $rejectedStmt->execute($rBindings);
        $totalRejected = (int) $rejectedStmt->fetchColumn();

        // Conversion Rates
        $convProspectusToReg = $totalProspectus > 0 ? round(($totalRegistrations / $totalProspectus) * 100, 1) : 0;
        $convRegToAdmitted = $totalRegistrations > 0 ? round(($totalAdmitted / $totalRegistrations) * 100, 1) : 0;
        $overallConversionRate = $totalProspectus > 0 ? round(($totalAdmitted / $totalProspectus) * 100, 1) : ($totalRegistrations > 0 ? $convRegToAdmitted : 0);

        // Gender Distribution
        $genderStmt = $pdo->prepare("SELECT gender, COUNT(*) as count FROM registrations r WHERE {$rWhereSql} GROUP BY gender");
        $genderStmt->execute($rBindings);
        $genderRows = $genderStmt->fetchAll(\PDO::FETCH_ASSOC);
        $genderDist = [
            'male'   => 0,
            'female' => 0,
            'other'  => 0,
        ];
        foreach ($genderRows as $g) {
            $k = strtolower((string) ($g['gender'] ?? ''));
            if ($k === 'm') $genderDist['male'] += (int) $g['count'];
            elseif ($k === 'f') $genderDist['female'] += (int) $g['count'];
            else $genderDist['other'] += (int) $g['count'];
        }

        // Category Distribution
        $catStmt = $pdo->prepare(
            "SELECT COALESCE(sc.name, 'General / Unassigned') as category_name, COUNT(r.id) as count
             FROM registrations r
             LEFT JOIN student_categories sc ON sc.id = r.student_category_id
             WHERE {$rWhereSql}
             GROUP BY sc.id, sc.name"
        );
        $catStmt->execute($rBindings);
        $categoryDist = $catStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Status Breakdown
        $statusStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM registrations r WHERE {$rWhereSql} GROUP BY status");
        $statusStmt->execute($rBindings);
        $statusDist = $statusStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Class-wise Demand
        $classStmt = $pdo->prepare(
            "SELECT ac.id as class_id, ac.name as class_name,
                    COUNT(r.id) as applied_count,
                    SUM(CASE WHEN r.status = 'admitted' THEN 1 ELSE 0 END) as admitted_count
             FROM academic_classes ac
             LEFT JOIN registrations r ON r.class_id = ac.id AND {$rWhereSql}
             WHERE ac.deleted_at IS NULL
             GROUP BY ac.id, ac.name
             ORDER BY ac.sort_order ASC, ac.id ASC"
        );
        $classStmt->execute($rBindings);
        $classDemand = $classStmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::success([
            'funnel' => [
                'total_prospectus'        => $totalProspectus,
                'total_registrations'     => $totalRegistrations,
                'total_shortlisted'       => $totalShortlisted,
                'total_admitted'          => $totalAdmitted,
                'total_rejected'          => $totalRejected,
                'conv_prospectus_to_reg'  => $convProspectusToReg,
                'conv_reg_to_admitted'    => $convRegToAdmitted,
                'overall_conversion_rate' => $overallConversionRate,
            ],
            'gender_distribution'   => $genderDist,
            'category_distribution' => $categoryDist,
            'status_distribution'   => $statusDist,
            'class_demand'          => $classDemand,
        ]);
    }

    /**
     * GET /api/admission/reports/strength
     */
    public function strengthReport(Request $request): Response
    {
        $pdo = Database::pdo();
        $academicYearId = $request->query('academic_year_id');
        $firmId = $request->query('firm_id');

        $classWhere = ['ac.deleted_at IS NULL'];
        $params = [];

        $studentFirmClause = '';
        $regFirmClause = '';
        if ($firmId !== null && $firmId !== '') {
            $classWhere[] = '(ac.firm_id = :class_firm_id OR ac.firm_id IS NULL)';
            $params['class_firm_id'] = (int) $firmId;
            $studentFirmClause = 'AND (u.firm_id = :student_firm_id OR u.firm_id IS NULL)';
            $params['student_firm_id'] = (int) $firmId;
            $regFirmClause = 'AND (r.firm_id = :reg_firm_id OR r.firm_id IS NULL)';
            $params['reg_firm_id'] = (int) $firmId;
        }

        $classWhereSql = implode(' AND ', $classWhere);

        // Subquery or counts for students enrolled and new admissions
        $studentAyClause = '';
        $regAyClause = '';

        if ($academicYearId !== null && $academicYearId !== '') {
            $studentAyClause = 'AND u.academic_year_id = :student_ay_id';
            $params['student_ay_id'] = (int) $academicYearId;
            $regAyClause = 'AND r.academic_year_id = :reg_ay_id';
            $params['reg_ay_id'] = (int) $academicYearId;
        }

        $sql = "SELECT
                    ac.id as class_id,
                    ac.name as class_name,
                    ac.short_name as class_short_name,
                    COALESCE(ac.capacity, 40) as capacity,
                    
                    -- Count sections for this class
                    (SELECT COUNT(*) FROM sections sec WHERE sec.class_id = ac.id AND sec.deleted_at IS NULL) as section_count,
                    
                    -- Existing enrolled students (linked to section -> class or student record)
                    (SELECT COUNT(DISTINCT s.id)
                     FROM students s
                     JOIN users u ON u.id = s.user_id AND u.deleted_at IS NULL
                     LEFT JOIN sections sec ON sec.id = s.section_id
                     WHERE (sec.class_id = ac.id OR s.section_id IN (SELECT id FROM sections WHERE class_id = ac.id))
                       {$studentAyClause}
                       {$studentFirmClause}
                    ) as existing_students,

                    -- New admissions from registration table
                    (SELECT COUNT(DISTINCT r.id)
                     FROM registrations r
                     WHERE r.class_id = ac.id
                       AND r.status = 'admitted'
                       AND r.deleted_at IS NULL
                       {$regAyClause}
                       {$regFirmClause}
                    ) as new_admissions

                FROM academic_classes ac
                WHERE {$classWhereSql}
                ORDER BY ac.sort_order ASC, ac.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $classes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalCapacity = 0;
        $totalExisting = 0;
        $totalNewAdmissions = 0;
        $totalStrength = 0;
        $totalVacant = 0;

        $rows = [];
        foreach ($classes as $c) {
            $cap = max(1, (int) $c['capacity']);
            $secCount = (int) $c['section_count'];
            // If class has multiple sections, effective capacity is capacity * section_count
            $effectiveCapacity = $secCount > 1 ? ($cap * $secCount) : $cap;

            $exist = (int) $c['existing_students'];
            $newAdm = (int) $c['new_admissions'];
            $strength = $exist + $newAdm;
            $vacant = max(0, $effectiveCapacity - $strength);
            $utilization = round(($strength / $effectiveCapacity) * 100, 1);

            $totalCapacity += $effectiveCapacity;
            $totalExisting += $exist;
            $totalNewAdmissions += $newAdm;
            $totalStrength += $strength;
            $totalVacant += $vacant;

            $rows[] = [
                'class_id'           => (int) $c['class_id'],
                'class_name'         => $c['class_name'],
                'class_short_name'   => $c['class_short_name'],
                'capacity'           => $effectiveCapacity,
                'section_count'      => $secCount,
                'existing_students'  => $exist,
                'new_admissions'     => $newAdm,
                'total_strength'     => $strength,
                'vacant_seats'       => $vacant,
                'utilization_rate'   => $utilization,
            ];
        }

        $overallUtilization = $totalCapacity > 0 ? round(($totalStrength / $totalCapacity) * 100, 1) : 0;

        return Response::success([
            'summary' => [
                'total_sanctioned_capacity' => $totalCapacity,
                'total_existing_students'   => $totalExisting,
                'total_new_admissions'      => $totalNewAdmissions,
                'total_current_strength'    => $totalStrength,
                'total_vacant_seats'        => $totalVacant,
                'overall_utilization_rate'  => $overallUtilization,
            ],
            'classes' => $rows,
        ]);
    }
}
