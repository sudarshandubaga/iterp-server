<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Models\CustomFieldValue;

/**
 * Controller for importing students via Excel (parsed JSON) or CSV.
 */
class StudentImportController
{
    private const ROLE_STUDENT = 'Student';

    /**
     * Download or output a sample CSV template with standard fields.
     */
    public function downloadTemplate(Request $request): Response
    {
        $headers = [
            'S.No.',
            'Session',
            'Student NIC ID',
            'SR No.',
            'Aadhar Number',
            'Student Name',
            'Father Name',
            'Mother Name',
            'DOB',
            'Gender',
            'Social Category',
            'Religion',
            'Address',
            'Date of Admission',
            'Class',
            'Mobile Number',
            'Email Address',
        ];

        $sampleRow1 = [
            '1',
            '2025-26',
            'NIC-2025-001',
            'SR-1001',
            '234567890123',
            'Aarav Sharma',
            'Rajesh Sharma',
            'Sunita Sharma',
            '2014-05-15',
            'Male',
            'General',
            'Hindu',
            '123 Civil Lines, New Delhi',
            '2025-04-01',
            '1st',
            '9876543210',
            'aarav.sharma@example.com',
        ];

        $sampleRow2 = [
            '2',
            '2025-26',
            'NIC-2025-002',
            'SR-1002',
            '987654321098',
            'Pooja Verma',
            'Anil Verma',
            'Kavita Verma',
            '2015-08-22',
            'Female',
            'OBC',
            'Hindu',
            '45 Park Street, Jaipur',
            '2025-04-05',
            '1st',
            '9811223344',
            'pooja.verma@example.com',
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        fputcsv($output, $sampleRow1);
        fputcsv($output, $sampleRow2);
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="student_import_template.csv"');
        echo $csvContent;
        exit;
    }

    /**
     * Import students endpoint.
     * Accepts JSON: { students: [ ... ], firm_id: ..., default_academic_year_id: ... }
     * Or multipart/form-data: file (.csv), firm_id, academic_year_id
     */
    public function import(Request $request): Response
    {
        $firmId = $request->input('firm_id') ? (int) $request->input('firm_id') : 1;
        $defaultYearId = $request->input('academic_year_id') ? (int) $request->input('academic_year_id') : null;

        $rawRows = [];

        // Check if a file was uploaded
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['file']['tmp_name'];
            $fileContent = file_get_contents($tmpPath);
            $rawRows = $this->parseCsvString($fileContent);
        } else {
            // Check JSON body
            $body = $request->all();
            if (isset($body['students']) && is_array($body['students'])) {
                $rawRows = $body['students'];
            } elseif (isset($body['rows']) && is_array($body['rows'])) {
                $rawRows = $body['rows'];
            }
        }

        if (empty($rawRows)) {
            return Response::error('No student records found to import. Please upload a CSV/Excel file or provide rows.', 422);
        }

        $pdo = Database::pdo();
        $studentRoleId = $this->getOrCreateStudentRoleId($pdo);

        $results = [
            'total'            => count($rawRows),
            'imported'         => 0,
            'skipped'          => 0,
            'errors'           => [],
            'created_sessions' => [],
            'created_classes'  => [],
        ];

        // Cache for lookups during import
        $sessionCache = []; // name => id
        $classCache = [];   // name => id
        $sectionCache = []; // classId_yearId => id
        $categoryCache = []; // name => id

        foreach ($rawRows as $index => $row) {
            $rowNum = $index + 1;
            $normalized = $this->normalizeRow($row);

            $studentName = trim((string) ($normalized['student_name'] ?? ''));
            if ($studentName === '') {
                $results['errors'][] = [
                    'row'     => $rowNum,
                    'sr_no'   => $normalized['s_no'] ?? $rowNum,
                    'message' => 'Student Name is required.',
                ];
                $results['skipped']++;
                continue;
            }

            try {
                $pdo->beginTransaction();

                // 1. Session (Academic Year) - create if not present
                $sessionName = trim((string) ($normalized['session'] ?? ''));
                $yearId = $defaultYearId;

                if ($sessionName !== '') {
                    $normKey = strtolower($sessionName);
                    if (isset($sessionCache[$normKey])) {
                        $yearId = $sessionCache[$normKey];
                    } else {
                        $stmt = $pdo->prepare(
                            'SELECT id FROM academic_years WHERE LOWER(TRIM(name)) = :name AND deleted_at IS NULL LIMIT 1'
                        );
                        $stmt->execute(['name' => $normKey]);
                        $foundId = $stmt->fetchColumn();

                        if ($foundId) {
                            $yearId = (int) $foundId;
                        } else {
                            // Create session if not present
                            $dates = $this->deriveAcademicYearDates($sessionName);
                            $insStmt = $pdo->prepare(
                                'INSERT INTO academic_years (name, start_date, end_date, firm_id, created_at, updated_at)
                                 VALUES (:name, :start, :end, :firm_id, NOW(), NOW())'
                            );
                            $insStmt->execute([
                                'name'    => $sessionName,
                                'start'   => $dates['start'],
                                'end'     => $dates['end'],
                                'firm_id' => $firmId,
                            ]);
                            $yearId = (int) $pdo->lastInsertId();
                            $results['created_sessions'][] = $sessionName;
                        }
                        $sessionCache[$normKey] = $yearId;
                    }
                }

                if (!$yearId) {
                    // Fallback to latest academic year
                    $fallbackStmt = $pdo->query('SELECT id FROM academic_years WHERE deleted_at IS NULL ORDER BY start_date DESC LIMIT 1');
                    $yearId = (int) $fallbackStmt->fetchColumn() ?: null;
                }

                // 2. Class & Section
                $className = trim((string) ($normalized['class'] ?? ''));
                $sectionId = null;

                if ($className !== '') {
                    $normClass = strtolower($className);
                    $classId = null;

                    if (isset($classCache[$normClass])) {
                        $classId = $classCache[$normClass];
                    } else {
                        $stmt = $pdo->prepare(
                            'SELECT id FROM academic_classes WHERE (LOWER(TRIM(name)) = :n1 OR LOWER(TRIM(short_name)) = :n2) AND deleted_at IS NULL LIMIT 1'
                        );
                        $stmt->execute(['n1' => $normClass, 'n2' => $normClass]);
                        $foundClassId = $stmt->fetchColumn();

                        if ($foundClassId) {
                            $classId = (int) $foundClassId;
                        } else {
                            // Create class if not present
                            $shortName = substr($className, 0, 20);
                            $insClass = $pdo->prepare(
                                'INSERT INTO academic_classes (name, short_name, capacity, firm_id, created_at, updated_at)
                                 VALUES (:name, :short, 40, :firm_id, NOW(), NOW())'
                            );
                            $insClass->execute([
                                'name'    => $className,
                                'short'   => $shortName,
                                'firm_id' => $firmId,
                            ]);
                            $classId = (int) $pdo->lastInsertId();
                            $results['created_classes'][] = $className;
                        }
                        $classCache[$normClass] = $classId;
                    }

                    // Get or create Section for this class and session
                    if ($classId && $yearId) {
                        $secCacheKey = "{$classId}_{$yearId}";
                        if (isset($sectionCache[$secCacheKey])) {
                            $sectionId = $sectionCache[$secCacheKey];
                        } else {
                            $stmt = $pdo->prepare(
                                'SELECT id FROM sections WHERE class_id = :cid AND academic_year_id = :yid AND deleted_at IS NULL LIMIT 1'
                            );
                            $stmt->execute(['cid' => $classId, 'yid' => $yearId]);
                            $foundSecId = $stmt->fetchColumn();

                            if ($foundSecId) {
                                $sectionId = (int) $foundSecId;
                            } else {
                                $insSec = $pdo->prepare(
                                    'INSERT INTO sections (name, class_id, academic_year_id, created_at, updated_at)
                                     VALUES ("A", :cid, :yid, NOW(), NOW())'
                                );
                                $insSec->execute(['cid' => $classId, 'yid' => $yearId]);
                                $sectionId = (int) $pdo->lastInsertId();
                            }
                            $sectionCache[$secCacheKey] = $sectionId;
                        }
                    }
                }

                // 3. Name breakdown
                $nameParts = $this->splitFullName($studentName);
                $firstName  = $nameParts['first_name'];
                $middleName = $nameParts['middle_name'];
                $lastName   = $nameParts['last_name'];

                // 4. DOB & Date of Admission
                $dob = $this->parseDate($normalized['dob'] ?? null);
                $doj = $this->parseDate($normalized['date_of_admission'] ?? null) ?: date('Y-m-d');

                // 5. Gender
                $gender = $this->parseGender($normalized['gender'] ?? null);

                // 6. Mobile & Email
                $mobileNo = trim((string) ($normalized['mobile_number'] ?? '')) ?: null;
                $email = trim((string) ($normalized['email_address'] ?? '')) ?: null;
                if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = null;
                }

                // Check email uniqueness; if already exists on users table, nullify or alter to avoid abort
                if ($email) {
                    $chkEmail = $pdo->prepare('SELECT id FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1');
                    $chkEmail->execute(['email' => $email]);
                    if ($chkEmail->fetchColumn()) {
                        $email = null; // Prevent unique constraint crash on duplicate email
                    }
                }

                // 7. Username generation
                $username = $this->generateUniqueUsername($pdo, $firstName, $lastName);
                $hashedPassword = password_hash('Student@123', PASSWORD_BCRYPT);

                // 8. Insert into users table
                $userInsert = 'INSERT INTO users (
                    first_name, middle_name, last_name, username, password,
                    gender, role_id, dob, doj, email, mobile_no,
                    is_active, firm_id, academic_year_id, created_at, updated_at
                ) VALUES (
                    :first_name, :middle_name, :last_name, :username, :password,
                    :gender, :role_id, :dob, :doj, :email, :mobile_no,
                    "y", :firm_id, :academic_year_id, NOW(), NOW()
                )';
                $userStmt = $pdo->prepare($userInsert);
                $userStmt->execute([
                    'first_name'       => $firstName,
                    'middle_name'      => $middleName,
                    'last_name'        => $lastName,
                    'username'         => $username,
                    'password'         => $hashedPassword,
                    'gender'           => $gender,
                    'role_id'          => $studentRoleId,
                    'dob'              => $dob,
                    'doj'              => $doj,
                    'email'            => $email,
                    'mobile_no'        => $mobileNo,
                    'firm_id'          => $firmId,
                    'academic_year_id' => $yearId,
                ]);
                $userId = (int) $pdo->lastInsertId();

                // 9. Assign user_roles
                $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (:uid, :rid, NOW())')
                    ->execute(['uid' => $userId, 'rid' => $studentRoleId]);

                // 10. Student NIC ID (Enrollment Number) & SR No. (Scholar Number)
                $enrollmentNumber = trim((string) ($normalized['student_nic_id'] ?? ''));
                if ($enrollmentNumber === '') {
                    $enrollmentNumber = 'ENR-' . ($yearId ?: date('Y')) . '-' . str_pad((string) $userId, 4, '0', STR_PAD_LEFT);
                } else {
                    // Check if duplicate enrollment exists
                    $enrCheck = $pdo->prepare('SELECT id FROM students WHERE enrollment_number = :enr AND deleted_at IS NULL LIMIT 1');
                    $enrCheck->execute(['enr' => $enrollmentNumber]);
                    if ($enrCheck->fetchColumn()) {
                        $enrollmentNumber .= '-' . $userId;
                    }
                }

                $scholarNumber = trim((string) ($normalized['sr_no'] ?? ''));
                if ($scholarNumber === '') {
                    $scholarNumber = 'SCH-' . ($yearId ?: date('Y')) . '-' . str_pad((string) $userId, 4, '0', STR_PAD_LEFT);
                } else {
                    $schCheck = $pdo->prepare('SELECT id FROM students WHERE scholar_number = :sch AND deleted_at IS NULL LIMIT 1');
                    $schCheck->execute(['sch' => $scholarNumber]);
                    if ($schCheck->fetchColumn()) {
                        $scholarNumber .= '-' . $userId;
                    }
                }

                // 11. Social Category (Check student_categories)
                $socialCategory = trim((string) ($normalized['social_category'] ?? ''));
                $studentCategoryId = null;
                if ($socialCategory !== '') {
                    $normCat = strtolower($socialCategory);
                    if (isset($categoryCache[$normCat])) {
                        $studentCategoryId = $categoryCache[$normCat];
                    } else {
                        $stmt = $pdo->prepare('SELECT id FROM student_categories WHERE LOWER(TRIM(name)) = :name AND deleted_at IS NULL LIMIT 1');
                        $stmt->execute(['name' => $normCat]);
                        $foundCatId = $stmt->fetchColumn();
                        if ($foundCatId) {
                            $studentCategoryId = (int) $foundCatId;
                        } else {
                            $insCat = $pdo->prepare('INSERT INTO student_categories (name, status, created_at, updated_at) VALUES (:name, "active", NOW(), NOW())');
                            $insCat->execute(['name' => $socialCategory]);
                            $studentCategoryId = (int) $pdo->lastInsertId();
                        }
                        $categoryCache[$normCat] = $studentCategoryId;
                    }
                }

                // 12. Insert into students table
                $fatherMobile = trim((string) ($normalized['father_mobile_no'] ?? '')) ?: $mobileNo;
                $studentInsert = 'INSERT INTO students (
                    user_id, enrollment_number, scholar_number, section_id,
                    academic_year_id, student_category_id, father_mobile_no,
                    created_at, updated_at
                ) VALUES (
                    :user_id, :enr, :sch, :sec, :yid, :cat, :fmobile, NOW(), NOW()
                )';
                $pdo->prepare($studentInsert)->execute([
                    'user_id' => $userId,
                    'enr'     => $enrollmentNumber,
                    'sch'     => $scholarNumber,
                    'sec'     => $sectionId,
                    'yid'     => $yearId,
                    'cat'     => $studentCategoryId,
                    'fmobile' => $fatherMobile,
                ]);

                // 13. Save Custom Fields for data not present in students/users tables
                // Fields: Aadhar Number, Father Name, Mother Name, Social Category, Religion, Address
                $customMappings = [
                    'Aadhar Number'   => $normalized['aadhar_number'] ?? null,
                    'Father Name'     => $normalized['father_name'] ?? null,
                    'Mother Name'     => $normalized['mother_name'] ?? null,
                    'Social Category' => $normalized['social_category'] ?? null,
                    'Religion'        => $normalized['religion'] ?? null,
                    'Address'         => $normalized['address'] ?? null,
                ];

                foreach ($customMappings as $cfName => $cfValue) {
                    if ($cfValue !== null && trim((string) $cfValue) !== '') {
                        CustomFieldValue::setValueByName($userId, $cfName, trim((string) $cfValue));
                    }
                }

                $pdo->commit();
                $results['imported']++;
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $results['errors'][] = [
                    'row'     => $rowNum,
                    'sr_no'   => $normalized['s_no'] ?? $rowNum,
                    'name'    => $studentName,
                    'message' => $e->getMessage(),
                ];
                $results['skipped']++;
            }
        }

        $results['created_sessions'] = array_values(array_unique($results['created_sessions']));
        $results['created_classes']  = array_values(array_unique($results['created_classes']));

        $message = sprintf(
            'Import complete: %d students imported successfully, %d skipped/failed.',
            $results['imported'],
            $results['skipped']
        );

        return Response::success($results, $message);
    }

    /**
     * Map varying CSV/Excel column names into standardized keys.
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $rawKey => $value) {
            $key = strtolower(trim((string) $rawKey));
            $cleanKey = preg_replace('/[^a-z0-9]/', '', $key);

            if (in_array($cleanKey, ['sno', 'srnoauto', 'slno', 'serialno', 'serialnumber'])) {
                $normalized['s_no'] = $value;
            } elseif (in_array($cleanKey, ['session', 'academicyear', 'year', 'sessionname'])) {
                $normalized['session'] = $value;
            } elseif (in_array($cleanKey, ['studentnicid', 'nicid', 'enrollmentnumber', 'enrollmentno', 'enrno'])) {
                $normalized['student_nic_id'] = $value;
            } elseif (in_array($cleanKey, ['srno', 'scholarnumber', 'scholarno', 'admissionno', 'admno'])) {
                $normalized['sr_no'] = $value;
            } elseif (in_array($cleanKey, ['aadharnumber', 'aadhaarnumber', 'aadharno', 'aadhaarno', 'aadhar', 'aadhaar'])) {
                $normalized['aadhar_number'] = $value;
            } elseif (in_array($cleanKey, ['studentname', 'name', 'fullname', 'nameofstudent'])) {
                $normalized['student_name'] = $value;
            } elseif (in_array($cleanKey, ['fathername', 'fathersname', 'father'])) {
                $normalized['father_name'] = $value;
            } elseif (in_array($cleanKey, ['mothername', 'mothersname', 'mother'])) {
                $normalized['mother_name'] = $value;
            } elseif (in_array($cleanKey, ['dob', 'dateofbirth', 'birthdate'])) {
                $normalized['dob'] = $value;
            } elseif (in_array($cleanKey, ['gender', 'sex'])) {
                $normalized['gender'] = $value;
            } elseif (in_array($cleanKey, ['socialcategory', 'castcategory', 'castecategory', 'category', 'caste'])) {
                $normalized['social_category'] = $value;
            } elseif (in_array($cleanKey, ['religion', 'faith'])) {
                $normalized['religion'] = $value;
            } elseif (in_array($cleanKey, ['address', 'residence', 'addressdetails', 'fulladdress'])) {
                $normalized['address'] = $value;
            } elseif (in_array($cleanKey, ['dateofadmission', 'admissiondate', 'doa', 'doj', 'joiningdate'])) {
                $normalized['date_of_admission'] = $value;
            } elseif (in_array($cleanKey, ['class', 'academicclass', 'grade', 'standard'])) {
                $normalized['class'] = $value;
            } elseif (in_array($cleanKey, ['mobilenumber', 'mobile', 'mobileno', 'phonenumber', 'phone', 'contact'])) {
                $normalized['mobile_number'] = $value;
            } elseif (in_array($cleanKey, ['emailaddress', 'email', 'emailid'])) {
                $normalized['email_address'] = $value;
            } else {
                // Keep raw key as fallback
                $normalized[$cleanKey] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Parse CSV string into array of associative rows.
     */
    private function parseCsvString(string $content): array
    {
        // Strip BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        $headerLine = array_shift($lines);
        $headers = str_getcsv($headerLine);
        $headers = array_map('trim', $headers);

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $data = str_getcsv($line);
            if (count($data) === 0 || (count($data) === 1 && trim((string)$data[0]) === '')) {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = isset($data[$i]) ? trim((string)$data[$i]) : '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Split a single Student Name into first, middle, and last name.
     */
    private function splitFullName(string $fullName): array
    {
        $fullName = preg_replace('/\s+/', ' ', trim($fullName));
        $parts = explode(' ', $fullName);

        if (count($parts) === 1) {
            return [
                'first_name'  => $parts[0],
                'middle_name' => null,
                'last_name'   => null,
            ];
        }

        if (count($parts) === 2) {
            return [
                'first_name'  => $parts[0],
                'middle_name' => null,
                'last_name'   => $parts[1],
            ];
        }

        if (count($parts) === 3) {
            return [
                'first_name'  => $parts[0],
                'middle_name' => $parts[1],
                'last_name'   => $parts[2],
            ];
        }

        // > 3 words
        $first = array_shift($parts);
        $last  = array_pop($parts);
        $middle = implode(' ', $parts);

        return [
            'first_name'  => $first,
            'middle_name' => $middle,
            'last_name'   => $last,
        ];
    }

    /**
     * Parse dates from multiple formats (Excel serial numbers, DD/MM/YYYY, YYYY-MM-DD, etc.)
     */
    private function parseDate(mixed $dateVal): ?string
    {
        if ($dateVal === null || trim((string) $dateVal) === '') {
            return null;
        }

        $str = trim((string) $dateVal);

        // Check if numeric Excel serial date (e.g. 44561)
        if (is_numeric($str) && (float) $str > 1000 && (float) $str < 100000) {
            $days = (int) $str;
            // Excel counts from 1900-01-01 (with 1900 leap year bug)
            $unixTime = ($days - 25569) * 86400;
            return date('Y-m-d', $unixTime);
        }

        // Try YYYY-MM-DD
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $str, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        // Try DD-MM-YYYY or DD/MM/YYYY
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $str, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        // Fallback to strtotime
        $timestamp = strtotime($str);
        if ($timestamp !== false && $timestamp > 0) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * Parse gender into 'm' or 'f'.
     */
    private function parseGender(mixed $genderVal): string
    {
        if (!$genderVal) return 'm';
        $g = strtolower(trim((string) $genderVal));
        if (in_array($g, ['f', 'female', 'girl', 'woman', 'w'])) {
            return 'f';
        }
        return 'm';
    }

    /**
     * Derive start and end dates for a session name (e.g. '2025-26', '2026-2027', '2026').
     */
    private function deriveAcademicYearDates(string $session): array
    {
        if (preg_match('/^(\d{4})[-\/](\d{2,4})$/', trim($session), $m)) {
            $startYear = (int) $m[1];
            $endSuffix = $m[2];
            $endYear = strlen($endSuffix) === 2 ? (int) (substr((string) $startYear, 0, 2) . $endSuffix) : (int) $endSuffix;

            return [
                'start' => sprintf('%04d-04-01', $startYear),
                'end'   => sprintf('%04d-03-31', $endYear),
            ];
        }

        if (preg_match('/^(\d{4})$/', trim($session), $m)) {
            $startYear = (int) $m[1];
            return [
                'start' => sprintf('%04d-04-01', $startYear),
                'end'   => sprintf('%04d-03-31', $startYear + 1),
            ];
        }

        // Default to current academic year
        $curYear = (int) date('Y');
        return [
            'start' => "{$curYear}-04-01",
            'end'   => ($curYear + 1) . '-03-31',
        ];
    }

    /**
     * Generate unique username in users table.
     */
    private function generateUniqueUsername(\PDO $pdo, string $first, ?string $last): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]/', '', $first . ($last ? '.' . $last : '')));
        if ($base === '') $base = 'student';

        $candidate = $base . rand(100, 999);
        $check = $pdo->prepare('SELECT id FROM users WHERE username = :uname LIMIT 1');
        $check->execute(['uname' => $candidate]);

        while ($check->fetchColumn()) {
            $candidate = $base . rand(1000, 9999);
            $check->execute(['uname' => $candidate]);
        }

        return $candidate;
    }

    /**
     * Get or create "Student" role ID.
     */
    private function getOrCreateStudentRoleId(\PDO $pdo): int
    {
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => self::ROLE_STUDENT]);
        $id = (int) $stmt->fetchColumn();

        if ($id > 0) return $id;

        $pdo->prepare('INSERT INTO roles (name, created_at, updated_at) VALUES (:name, NOW(), NOW())')
            ->execute(['name' => self::ROLE_STUDENT]);

        return (int) $pdo->lastInsertId();
    }
}
