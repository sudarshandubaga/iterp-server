<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;

/**
 * Controller for Student & Teacher ID Cards generation.
 */
class IdCardController
{
    /**
     * Get student list & school profile for ID Cards.
     */
    public function students(Request $request): Response
    {
        $pdo = Database::pdo();
        $where = ['u.deleted_at IS NULL', 's.deleted_at IS NULL', 'u.is_active = "y"'];
        $bindings = [];

        // Firm / School Info
        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $where[] = 'u.firm_id = :firm_id';
            $bindings['firm_id'] = (int) $firmId;
        }

        $sessionParam = $request->query('academic_year_id') ?? $request->query('session_id');
        if ($sessionParam !== null && $sessionParam !== '') {
            $where[] = '(s.academic_year_id = :session_id OR u.academic_year_id = :session_id2)';
            $bindings['session_id'] = (int) $sessionParam;
            $bindings['session_id2'] = (int) $sessionParam;
        }

        $classId = $request->query('class_id');
        if ($classId !== null && $classId !== '') {
            $where[] = 'sec.class_id = :class_id';
            $bindings['class_id'] = (int) $classId;
        }

        $sectionId = $request->query('section_id');
        if ($sectionId !== null && $sectionId !== '') {
            $where[] = 's.section_id = :section_id';
            $bindings['section_id'] = (int) $sectionId;
        }

        $studentId = $request->query('student_id');
        if ($studentId !== null && $studentId !== '') {
            $where[] = '(s.id = :student_id OR u.id = :student_id2)';
            $bindings['student_id'] = (int) $studentId;
            $bindings['student_id2'] = (int) $studentId;
        }

        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '') {
            $term = '%' . trim((string) $search) . '%';
            $where[] = '(u.first_name LIKE :search1 OR u.last_name LIKE :search2 OR s.enrollment_number LIKE :search3 OR s.roll_number LIKE :search4)';
            $bindings['search1'] = $term;
            $bindings['search2'] = $term;
            $bindings['search3'] = $term;
            $bindings['search4'] = $term;
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT s.id AS student_id,
                       u.id AS user_id,
                       CONCAT_WS(' ', u.first_name, NULLIF(u.middle_name, ''), u.last_name) AS student_name,
                       u.first_name,
                       u.middle_name,
                       u.last_name,
                       u.gender,
                       u.dob,
                       COALESCE(NULLIF(s.father_mobile_no, ''), NULLIF(u.mobile_no, ''), reg.father_mobile_no, reg.mother_mobile_no) AS mobile_no,
                       u.email,
                       s.enrollment_number,
                       s.scholar_number,
                       s.roll_number,
                       s.photo,
                       sec.id AS section_id,
                       sec.name AS section_name,
                       sec.class_id,
                       ac.name AS class_name,
                       ay.id AS academic_year_id,
                       ay.name AS academic_year_name,
                       COALESCE(
                           NULLIF(reg.father_name, ''),
                           (SELECT cfv.value
                            FROM custom_field_values cfv
                            INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
                            WHERE cfv.user_id = u.id
                              AND LOWER(TRIM(cf.name)) IN ('father name', 'fathers name', 'father_name', 'f / h name', 'father / husband name')
                              AND cf.deleted_at IS NULL
                            LIMIT 1)
                       ) AS father_name,
                       COALESCE(
                           NULLIF(reg.mother_name, ''),
                           (SELECT cfv.value
                            FROM custom_field_values cfv
                            INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
                            WHERE cfv.user_id = u.id
                              AND LOWER(TRIM(cf.name)) IN ('mother name', 'mothers name', 'mother_name')
                              AND cf.deleted_at IS NULL
                            LIMIT 1)
                       ) AS mother_name,
                       COALESCE(
                           NULLIF(reg.address, ''),
                           (SELECT cfv.value
                            FROM custom_field_values cfv
                            INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
                            WHERE cfv.user_id = u.id
                              AND LOWER(TRIM(cf.name)) IN ('address', 'permanent address', 'residential address', 'current address')
                              AND cf.deleted_at IS NULL
                            LIMIT 1),
                           NULLIF(CONCAT_WS(', ', c.name, st.name), '')
                       ) AS address,
                       reg.blood_group,
                       f.id AS firm_id,
                       f.name AS firm_name,
                       f.address AS firm_address,
                       f.phone_no AS firm_phone,
                       f.email AS firm_email,
                       f.logo AS firm_logo
                FROM students s
                INNER JOIN users u ON u.id = s.user_id
                LEFT JOIN sections sec ON sec.id = s.section_id
                LEFT JOIN academic_classes ac ON ac.id = sec.class_id
                LEFT JOIN academic_years ay ON ay.id = COALESCE(s.academic_year_id, u.academic_year_id)
                LEFT JOIN registrations reg ON reg.student_id = s.id
                LEFT JOIN cities c ON c.id = u.city_id
                LEFT JOIN states st ON st.id = c.state_id
                LEFT JOIN firms f ON f.id = u.firm_id
                WHERE {$whereSql}
                ORDER BY ac.sort_order ASC, ac.name ASC, sec.name ASC, CAST(s.roll_number AS UNSIGNED) ASC, s.roll_number ASC, u.first_name ASC
                LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fetch School profile (Firm)
        $school = $this->getSchoolProfile($pdo, !empty($bindings['firm_id']) ? $bindings['firm_id'] : null);

        return Response::success([
            'school'   => $school,
            'total'    => count($students),
            'students' => $students,
        ]);
    }

    /**
     * Get teacher & staff list & school profile for ID Cards.
     */
    public function teachers(Request $request): Response
    {
        $pdo = Database::pdo();
        $where = ['u.deleted_at IS NULL', 'e.deleted_at IS NULL', 'u.is_active = "y"'];
        $bindings = [];

        // Firm / School Info
        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $where[] = 'u.firm_id = :firm_id';
            $bindings['firm_id'] = (int) $firmId;
        }

        $type = $request->query('employee_type');
        if ($type !== null && $type !== '') {
            $where[] = 'e.employee_type = :emp_type';
            $bindings['emp_type'] = (string) $type;
        }

        $roleId = $request->query('role_id');
        if ($roleId !== null && $roleId !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = :role_id)';
            $bindings['role_id'] = (int) $roleId;
        }

        $employeeId = $request->query('employee_id');
        if ($employeeId !== null && $employeeId !== '') {
            $where[] = '(e.id = :emp_id OR u.id = :emp_id2)';
            $bindings['emp_id'] = (int) $employeeId;
            $bindings['emp_id2'] = (int) $employeeId;
        }

        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '') {
            $term = '%' . trim((string) $search) . '%';
            $where[] = '(u.first_name LIKE :search1 OR u.last_name LIKE :search2 OR e.attendance_code LIKE :search3 OR u.mobile_no LIKE :search4)';
            $bindings['search1'] = $term;
            $bindings['search2'] = $term;
            $bindings['search3'] = $term;
            $bindings['search4'] = $term;
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT e.id AS employee_id,
                       u.id AS user_id,
                       CONCAT_WS(' ', u.first_name, NULLIF(u.middle_name, ''), u.last_name) AS employee_name,
                       u.first_name,
                       u.middle_name,
                       u.last_name,
                       u.gender,
                       u.dob,
                       u.doj,
                       u.mobile_no,
                       u.email,
                       e.employee_type,
                       e.attendance_code,
                       e.photo,
                       (SELECT GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ')
                        FROM user_roles ur
                        INNER JOIN roles r ON r.id = ur.role_id
                        WHERE ur.user_id = u.id AND r.deleted_at IS NULL) AS role_names,
                       COALESCE(
                           (SELECT cfv.value
                            FROM custom_field_values cfv
                            INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
                            WHERE cfv.user_id = u.id
                              AND LOWER(TRIM(cf.name)) IN ('father name', 'fathers name', 'father_name', 'f / h name', 'husband name', 'father / husband name')
                              AND cf.deleted_at IS NULL
                            LIMIT 1)
                       ) AS father_name,
                       COALESCE(
                           (SELECT cfv.value
                            FROM custom_field_values cfv
                            INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
                            WHERE cfv.user_id = u.id
                              AND LOWER(TRIM(cf.name)) IN ('address', 'permanent address', 'residential address', 'current address')
                              AND cf.deleted_at IS NULL
                            LIMIT 1),
                           NULLIF(CONCAT_WS(', ', c.name, st.name), '')
                       ) AS address,
                       (SELECT cfv.value
                        FROM custom_field_values cfv
                        INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
                        WHERE cfv.user_id = u.id
                          AND LOWER(TRIM(cf.name)) IN ('blood group', 'blood_group', 'blood')
                          AND cf.deleted_at IS NULL
                        LIMIT 1) AS blood_group,
                       f.id AS firm_id,
                       f.name AS firm_name,
                       f.address AS firm_address,
                       f.phone_no AS firm_phone,
                       f.email AS firm_email,
                       f.logo AS firm_logo
                FROM employees e
                INNER JOIN users u ON u.id = e.user_id
                LEFT JOIN cities c ON c.id = u.city_id
                LEFT JOIN states st ON st.id = c.state_id
                LEFT JOIN firms f ON f.id = u.firm_id
                WHERE {$whereSql}
                ORDER BY e.employee_type ASC, u.first_name ASC
                LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fetch School profile (Firm)
        $school = $this->getSchoolProfile($pdo, !empty($bindings['firm_id']) ? $bindings['firm_id'] : null);

        return Response::success([
            'school'    => $school,
            'total'     => count($employees),
            'employees' => $employees,
        ]);
    }

    /**
     * Helper to load firm / school branding info.
     */
    private function getSchoolProfile(\PDO $pdo, ?int $firmId): array
    {
        if ($firmId) {
            $stmt = $pdo->prepare("SELECT id, name, address, phone_no, email, logo FROM firms WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $firmId]);
            $firm = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($firm) {
                return [
                    'name'    => $firm['name'],
                    'address' => $firm['address'] ?: 'Sector-2, Madhuban Housing Board, Basni 1st Phase, Jodhpur',
                    'phone'   => $firm['phone_no'] ?: '+91 9141132967',
                    'email'   => $firm['email'] ?: 'info@madhuban.edu.in',
                    'logo'    => $firm['logo'] ?: null,
                ];
            }
        }

        // Fallback to first active firm or default school info
        $stmt = $pdo->query("SELECT id, name, address, phone_no, email, logo FROM firms WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1");
        $firstFirm = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($firstFirm) {
            return [
                'name'    => $firstFirm['name'],
                'address' => $firstFirm['address'] ?: 'Sector-2, Madhuban Housing Board, Basni 1st Phase, Jodhpur',
                'phone'   => $firstFirm['phone_no'] ?: '+91 9141132967',
                'email'   => $firstFirm['email'] ?: 'info@madhuban.edu.in',
                'logo'    => $firstFirm['logo'] ?: null,
            ];
        }

        return [
            'name'    => 'MADHUBAN PUBLIC SR. SEC. SCHOOL',
            'address' => 'Sector-2, Madhuban Housing Board, Basni, 1st Phase, Jodhpur',
            'phone'   => '+91 9141132967',
            'email'   => 'info@madhubanschool.in',
            'logo'    => null,
        ];
    }
}

