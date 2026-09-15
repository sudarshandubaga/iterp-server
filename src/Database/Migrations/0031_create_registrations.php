<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * registrations table.
 *
 * Stores admission applications/registrations:
 * - registration_no (unique serial number, e.g. REG-2026-0001)
 * - prospectus_id (optional link to prospectus purchased)
 * - academic_year_id, firm_id, class_id, section_id, student_category_id
 * - candidate personal info, photo (AVIF), contact, address
 * - parents / guardian details
 * - previous academic background
 * - registration fee, payment mode, payment status
 * - admission status ('applied', 'under_review', 'shortlisted', 'admitted', 'rejected', 'cancelled')
 * - student_id (linked to students table upon admission confirmation)
 */
class CreateRegistrationsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS registrations (
                id                         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                registration_no            VARCHAR(100) NOT NULL,
                prospectus_id              BIGINT UNSIGNED NULL DEFAULT NULL,
                academic_year_id           BIGINT UNSIGNED NULL DEFAULT NULL,
                firm_id                    BIGINT UNSIGNED NULL DEFAULT NULL,
                class_id                   BIGINT UNSIGNED NOT NULL,
                section_id                 BIGINT UNSIGNED NULL DEFAULT NULL,
                student_category_id        BIGINT UNSIGNED NULL DEFAULT NULL,
                
                -- Candidate Personal Details
                first_name                 VARCHAR(255) NOT NULL,
                middle_name                VARCHAR(255) NULL DEFAULT NULL,
                last_name                  VARCHAR(255) NULL DEFAULT NULL,
                gender                     ENUM("m", "f", "other") NULL DEFAULT NULL,
                dob                        DATE NULL DEFAULT NULL,
                email                      VARCHAR(255) NULL DEFAULT NULL,
                mobile_no                  VARCHAR(50) NULL DEFAULT NULL,
                photo                      VARCHAR(255) NULL DEFAULT NULL,
                blood_group                VARCHAR(10) NULL DEFAULT NULL,
                religion                   VARCHAR(50) NULL DEFAULT NULL,
                nationality                VARCHAR(50) NULL DEFAULT "Indian",
                aadhaar_no                 VARCHAR(30) NULL DEFAULT NULL,
                
                -- Address Details
                address                    TEXT NULL DEFAULT NULL,
                city_id                    BIGINT UNSIGNED NULL DEFAULT NULL,
                state_id                   BIGINT UNSIGNED NULL DEFAULT NULL,
                pincode                    VARCHAR(20) NULL DEFAULT NULL,
                
                -- Parent / Guardian Details
                father_name                VARCHAR(255) NULL DEFAULT NULL,
                father_occupation          VARCHAR(100) NULL DEFAULT NULL,
                father_mobile_no           VARCHAR(50) NULL DEFAULT NULL,
                father_email               VARCHAR(255) NULL DEFAULT NULL,
                mother_name                VARCHAR(255) NULL DEFAULT NULL,
                mother_occupation          VARCHAR(100) NULL DEFAULT NULL,
                mother_mobile_no           VARCHAR(50) NULL DEFAULT NULL,
                guardian_name              VARCHAR(255) NULL DEFAULT NULL,
                guardian_relation          VARCHAR(50) NULL DEFAULT NULL,
                guardian_mobile_no         VARCHAR(50) NULL DEFAULT NULL,
                
                -- Previous Academic Details
                previous_school            VARCHAR(255) NULL DEFAULT NULL,
                previous_class             VARCHAR(100) NULL DEFAULT NULL,
                previous_marks_percentage  DECIMAL(5, 2) NULL DEFAULT NULL,
                transfer_certificate_no    VARCHAR(100) NULL DEFAULT NULL,
                
                -- Fee & Payment
                registration_fee           DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                payment_mode               VARCHAR(50) NOT NULL DEFAULT "Cash",
                payment_status             ENUM("paid", "partial", "pending") NOT NULL DEFAULT "paid",
                transaction_no             VARCHAR(100) NULL DEFAULT NULL,
                registration_date          DATE NOT NULL,
                
                -- Admission Status & Enrolled Student Link
                status                     ENUM("applied", "under_review", "shortlisted", "admitted", "rejected", "cancelled") NOT NULL DEFAULT "applied",
                admission_date             DATE NULL DEFAULT NULL,
                student_id                 BIGINT UNSIGNED NULL DEFAULT NULL,
                admission_no               VARCHAR(100) NULL DEFAULT NULL,
                
                remarks                    TEXT NULL DEFAULT NULL,
                created_by                 BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at                 TIMESTAMP NULL DEFAULT NULL,
                
                UNIQUE KEY uq_registrations_no (registration_no),
                CONSTRAINT fk_registrations_prospectus FOREIGN KEY (prospectus_id) REFERENCES prospectus (id) ON DELETE SET NULL,
                CONSTRAINT fk_registrations_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL,
                CONSTRAINT fk_registrations_firm FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE SET NULL,
                CONSTRAINT fk_registrations_class FOREIGN KEY (class_id) REFERENCES academic_classes (id) ON DELETE CASCADE,
                CONSTRAINT fk_registrations_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE SET NULL,
                CONSTRAINT fk_registrations_category FOREIGN KEY (student_category_id) REFERENCES student_categories (id) ON DELETE SET NULL,
                CONSTRAINT fk_registrations_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL,
                
                INDEX idx_registrations_no (registration_no),
                INDEX idx_registrations_class (class_id),
                INDEX idx_registrations_academic_year (academic_year_id),
                INDEX idx_registrations_firm (firm_id),
                INDEX idx_registrations_status (status),
                INDEX idx_registrations_date (registration_date),
                INDEX idx_registrations_student (student_id),
                INDEX idx_registrations_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS registrations');
    }
}
