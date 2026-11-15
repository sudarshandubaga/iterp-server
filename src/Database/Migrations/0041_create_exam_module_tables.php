<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Exam Module tables:
 * - exam_types: Types of examinations (Periodic Test, Term Exam, Unit Test, Annual Exam, etc.)
 * - exam_subjects: Primary & Extra Activity subjects configured for exams
 * - exams: Exam master records (Academic Year, Firm, Exam Name, Exam Type, Start & End Date, Status)
 * - exam_class_allocations: Multiple class/section allocations per exam
 * - exam_schedules: Exam timetable/schedule per exam, class/section & subject (dates, times, min/max marks)
 * - exam_marks: Student marks entries per exam, subject, and student (obtained marks, attendance, remarks)
 */
class CreateExamModuleTables extends Migration
{
    public function up(): void
    {
        // 1. exam_types
        $this->schema(
            'CREATE TABLE IF NOT EXISTS exam_types (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id   BIGINT UNSIGNED NULL DEFAULT NULL,
                firm_id     BIGINT UNSIGNED NULL DEFAULT NULL,
                name        VARCHAR(255) NOT NULL,
                short_name  VARCHAR(50)  NOT NULL,
                status      ENUM("active", "inactive") NOT NULL DEFAULT "active",
                description TEXT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at  TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_exam_types_firm FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE SET NULL,
                INDEX idx_exam_types_name (name),
                INDEX idx_exam_types_status (status),
                INDEX idx_exam_types_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // 2. exam_subjects
        $this->schema(
            'CREATE TABLE IF NOT EXISTS exam_subjects (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id   BIGINT UNSIGNED NULL DEFAULT NULL,
                firm_id     BIGINT UNSIGNED NULL DEFAULT NULL,
                name        VARCHAR(255) NOT NULL,
                short_name  VARCHAR(50)  NOT NULL,
                code        VARCHAR(50)  NULL DEFAULT NULL,
                type        ENUM("primary", "extra_activity") NOT NULL DEFAULT "primary",
                status      ENUM("active", "inactive") NOT NULL DEFAULT "active",
                sort_order  INT NOT NULL DEFAULT 0,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at  TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_exam_subjects_firm FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE SET NULL,
                INDEX idx_exam_subjects_name (name),
                INDEX idx_exam_subjects_type (type),
                INDEX idx_exam_subjects_status (status),
                INDEX idx_exam_subjects_sort (sort_order),
                INDEX idx_exam_subjects_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // 3. exams
        $this->schema(
            'CREATE TABLE IF NOT EXISTS exams (
                id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                firm_id           BIGINT UNSIGNED NULL DEFAULT NULL,
                academic_year_id  BIGINT UNSIGNED NOT NULL,
                exam_type_id      BIGINT UNSIGNED NOT NULL,
                name              VARCHAR(255) NOT NULL,
                start_date        DATE NOT NULL,
                end_date          DATE NOT NULL,
                status            ENUM("upcoming", "ongoing", "completed", "published", "active", "inactive") NOT NULL DEFAULT "upcoming",
                description       TEXT NULL,
                created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at        TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_exams_firm FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE SET NULL,
                CONSTRAINT fk_exams_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
                CONSTRAINT fk_exams_exam_type FOREIGN KEY (exam_type_id) REFERENCES exam_types (id) ON DELETE RESTRICT,
                INDEX idx_exams_name (name),
                INDEX idx_exams_academic_year (academic_year_id),
                INDEX idx_exams_exam_type (exam_type_id),
                INDEX idx_exams_status (status),
                INDEX idx_exams_start_date (start_date),
                INDEX idx_exams_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // 4. exam_class_allocations (multiple classes & sections per exam)
        $this->schema(
            'CREATE TABLE IF NOT EXISTS exam_class_allocations (
                id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exam_id            BIGINT UNSIGNED NOT NULL,
                academic_class_id  BIGINT UNSIGNED NOT NULL,
                section_id         BIGINT UNSIGNED NOT NULL,
                created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_exam_alloc_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
                CONSTRAINT fk_exam_alloc_class FOREIGN KEY (academic_class_id) REFERENCES academic_classes (id) ON DELETE CASCADE,
                CONSTRAINT fk_exam_alloc_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE,
                UNIQUE KEY uk_exam_section (exam_id, section_id),
                INDEX idx_exam_alloc_exam (exam_id),
                INDEX idx_exam_alloc_class (academic_class_id),
                INDEX idx_exam_alloc_section (section_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // 5. exam_schedules (timetable per exam, class/section & subject)
        $this->schema(
            'CREATE TABLE IF NOT EXISTS exam_schedules (
                id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exam_id            BIGINT UNSIGNED NOT NULL,
                academic_class_id  BIGINT UNSIGNED NOT NULL,
                section_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                subject_id         BIGINT UNSIGNED NOT NULL,
                exam_date          DATE NOT NULL,
                start_time         TIME NULL DEFAULT NULL,
                end_time           TIME NULL DEFAULT NULL,
                room_no            VARCHAR(50) NULL DEFAULT NULL,
                min_marks          DECIMAL(8, 2) NOT NULL DEFAULT 33.00,
                max_marks          DECIMAL(8, 2) NOT NULL DEFAULT 100.00,
                created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at         TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_exam_sched_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
                CONSTRAINT fk_exam_sched_class FOREIGN KEY (academic_class_id) REFERENCES academic_classes (id) ON DELETE CASCADE,
                CONSTRAINT fk_exam_sched_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE,
                CONSTRAINT fk_exam_sched_subject FOREIGN KEY (subject_id) REFERENCES exam_subjects (id) ON DELETE RESTRICT,
                INDEX idx_exam_sched_exam (exam_id),
                INDEX idx_exam_sched_class (academic_class_id),
                INDEX idx_exam_sched_section (section_id),
                INDEX idx_exam_sched_subject (subject_id),
                INDEX idx_exam_sched_date (exam_date),
                INDEX idx_exam_sched_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // 6. exam_marks (Marks entry per student & subject)
        $this->schema(
            'CREATE TABLE IF NOT EXISTS exam_marks (
                id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exam_id            BIGINT UNSIGNED NOT NULL,
                academic_class_id  BIGINT UNSIGNED NULL DEFAULT NULL,
                section_id         BIGINT UNSIGNED NOT NULL,
                subject_id         BIGINT UNSIGNED NOT NULL,
                student_id         BIGINT UNSIGNED NOT NULL,
                min_marks          DECIMAL(8, 2) NOT NULL DEFAULT 33.00,
                max_marks          DECIMAL(8, 2) NOT NULL DEFAULT 100.00,
                obtained_marks     DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
                attendance         ENUM("present", "absent") NOT NULL DEFAULT "present",
                remarks            VARCHAR(255) NULL DEFAULT NULL,
                created_by         BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at         TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_exam_marks_exam FOREIGN KEY (exam_id) REFERENCES exams (id) ON DELETE CASCADE,
                CONSTRAINT fk_exam_marks_class FOREIGN KEY (academic_class_id) REFERENCES academic_classes (id) ON DELETE SET NULL,
                CONSTRAINT fk_exam_marks_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE,
                CONSTRAINT fk_exam_marks_subject FOREIGN KEY (subject_id) REFERENCES exam_subjects (id) ON DELETE RESTRICT,
                CONSTRAINT fk_exam_marks_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
                UNIQUE KEY uk_exam_marks_entry (exam_id, subject_id, student_id),
                INDEX idx_exam_marks_exam (exam_id),
                INDEX idx_exam_marks_section (section_id),
                INDEX idx_exam_marks_subject (subject_id),
                INDEX idx_exam_marks_student (student_id),
                INDEX idx_exam_marks_attendance (attendance),
                INDEX idx_exam_marks_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS exam_marks');
        $this->schema('DROP TABLE IF EXISTS exam_schedules');
        $this->schema('DROP TABLE IF EXISTS exam_class_allocations');
        $this->schema('DROP TABLE IF EXISTS exams');
        $this->schema('DROP TABLE IF EXISTS exam_subjects');
        $this->schema('DROP TABLE IF EXISTS exam_types');
    }
}

