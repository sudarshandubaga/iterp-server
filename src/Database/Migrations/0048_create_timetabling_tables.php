<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Timetabling Module tables:
 * - timetables: Master timetables bound to Academic Session, Firm, and Name
 * - timetable_lectures: Lectures/Periods with times, Interval, Assembly, Zero Period Cum Fruit Break
 * - timetable_class_allocations: Mapping of Classes and Sections to a Timetable
 * - timetable_slots: Scheduled slots (day_of_week, lecture, class, section, group, subject, teacher, room)
 * - timetable_substitutions: Daily Teacher substitutions with slot details, original and substitute teachers
 */
class CreateTimetablingTablesTable extends Migration
{
    public function up(): void
    {
        // 1. Master Timetable
        $this->schema(
            'CREATE TABLE IF NOT EXISTS timetables (
                id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                firm_id           BIGINT UNSIGNED NULL DEFAULT NULL,
                academic_year_id  BIGINT UNSIGNED NOT NULL,
                name              VARCHAR(255) NOT NULL,
                description       TEXT NULL DEFAULT NULL,
                status            ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at        TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_timetables_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
                CONSTRAINT fk_timetables_firm FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE SET NULL,
                INDEX idx_timetables_ay (academic_year_id),
                INDEX idx_timetables_firm (firm_id),
                INDEX idx_timetables_status (status),
                INDEX idx_timetables_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // 2. Timetable Lectures / Periods
        $this->schema(
            'CREATE TABLE IF NOT EXISTS timetable_lectures (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                timetable_id    BIGINT UNSIGNED NOT NULL,
                lecture_name    VARCHAR(100) NOT NULL,
                start_time      TIME NOT NULL,
                end_time        TIME NOT NULL,
                is_interval     TINYINT(1) NOT NULL DEFAULT 0,
                is_assembly     TINYINT(1) NOT NULL DEFAULT 0,
                is_zero_period  TINYINT(1) NOT NULL DEFAULT 0,
                sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at      TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_tt_lectures_timetable FOREIGN KEY (timetable_id) REFERENCES timetables (id) ON DELETE CASCADE,
                INDEX idx_tt_lectures_tt (timetable_id),
                INDEX idx_tt_lectures_sort (sort_order),
                INDEX idx_tt_lectures_flags (is_interval, is_assembly, is_zero_period),
                INDEX idx_tt_lectures_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // 3. Timetable Class / Section Allocations
        $this->schema(
            'CREATE TABLE IF NOT EXISTS timetable_class_allocations (
                id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                timetable_id       BIGINT UNSIGNED NOT NULL,
                academic_class_id  BIGINT UNSIGNED NOT NULL,
                section_id         BIGINT UNSIGNED NOT NULL,
                created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at         TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_tt_alloc_timetable FOREIGN KEY (timetable_id) REFERENCES timetables (id) ON DELETE CASCADE,
                CONSTRAINT fk_tt_alloc_class FOREIGN KEY (academic_class_id) REFERENCES academic_classes (id) ON DELETE CASCADE,
                CONSTRAINT fk_tt_alloc_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE,
                INDEX idx_tt_alloc_tt (timetable_id),
                INDEX idx_tt_alloc_class_sec (academic_class_id, section_id),
                INDEX idx_tt_alloc_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // 4. Timetable Scheduled Slots (The Grid Matrix)
        $this->schema(
            'CREATE TABLE IF NOT EXISTS timetable_slots (
                id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                timetable_id       BIGINT UNSIGNED NOT NULL,
                academic_class_id  BIGINT UNSIGNED NOT NULL,
                section_id         BIGINT UNSIGNED NOT NULL,
                group_id           BIGINT UNSIGNED NULL DEFAULT NULL,
                lecture_id         BIGINT UNSIGNED NOT NULL,
                day_of_week        ENUM("monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday") NOT NULL,
                subject_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                subject_name       VARCHAR(255) NULL DEFAULT NULL,
                teacher_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                room_no            VARCHAR(100) NULL DEFAULT NULL,
                created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at         TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_tt_slots_timetable FOREIGN KEY (timetable_id) REFERENCES timetables (id) ON DELETE CASCADE,
                CONSTRAINT fk_tt_slots_class FOREIGN KEY (academic_class_id) REFERENCES academic_classes (id) ON DELETE CASCADE,
                CONSTRAINT fk_tt_slots_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE,
                CONSTRAINT fk_tt_slots_group FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE SET NULL,
                CONSTRAINT fk_tt_slots_lecture FOREIGN KEY (lecture_id) REFERENCES timetable_lectures (id) ON DELETE CASCADE,
                CONSTRAINT fk_tt_slots_subject FOREIGN KEY (subject_id) REFERENCES exam_subjects (id) ON DELETE SET NULL,
                CONSTRAINT fk_tt_slots_teacher FOREIGN KEY (teacher_id) REFERENCES employees (id) ON DELETE SET NULL,
                INDEX idx_tt_slots_tt (timetable_id),
                INDEX idx_tt_slots_class_sec (academic_class_id, section_id),
                INDEX idx_tt_slots_day_lec (day_of_week, lecture_id),
                INDEX idx_tt_slots_teacher (teacher_id, day_of_week, lecture_id),
                INDEX idx_tt_slots_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // 5. Timetable Substitutions
        $this->schema(
            'CREATE TABLE IF NOT EXISTS timetable_substitutions (
                id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                timetable_id           BIGINT UNSIGNED NOT NULL,
                timetable_slot_id      BIGINT UNSIGNED NULL DEFAULT NULL,
                substitution_date      DATE NOT NULL,
                day_of_week            ENUM("monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday") NOT NULL,
                lecture_id             BIGINT UNSIGNED NOT NULL,
                academic_class_id      BIGINT UNSIGNED NOT NULL,
                section_id             BIGINT UNSIGNED NOT NULL,
                group_id               BIGINT UNSIGNED NULL DEFAULT NULL,
                subject_id             BIGINT UNSIGNED NULL DEFAULT NULL,
                subject_name           VARCHAR(255) NULL DEFAULT NULL,
                original_teacher_id    BIGINT UNSIGNED NOT NULL,
                substitute_teacher_id  BIGINT UNSIGNED NOT NULL,
                status                 ENUM("assigned", "completed", "cancelled") NOT NULL DEFAULT "assigned",
                remarks                TEXT NULL DEFAULT NULL,
                created_by             BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at             TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_tt_sub_timetable FOREIGN KEY (timetable_id) REFERENCES timetables (id) ON DELETE CASCADE,
                CONSTRAINT fk_tt_sub_slot FOREIGN KEY (timetable_slot_id) REFERENCES timetable_slots (id) ON DELETE SET NULL,
                CONSTRAINT fk_tt_sub_lecture FOREIGN KEY (lecture_id) REFERENCES timetable_lectures (id) ON DELETE CASCADE,
                CONSTRAINT fk_tt_sub_class FOREIGN KEY (academic_class_id) REFERENCES academic_classes (id) ON DELETE CASCADE,
                CONSTRAINT fk_tt_sub_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE CASCADE,
                CONSTRAINT fk_tt_sub_orig_teacher FOREIGN KEY (original_teacher_id) REFERENCES employees (id) ON DELETE CASCADE,
                CONSTRAINT fk_tt_sub_new_teacher FOREIGN KEY (substitute_teacher_id) REFERENCES employees (id) ON DELETE CASCADE,
                INDEX idx_tt_sub_date_lec (substitution_date, lecture_id),
                INDEX idx_tt_sub_orig (original_teacher_id, substitution_date),
                INDEX idx_tt_sub_new (substitute_teacher_id, substitution_date),
                INDEX idx_tt_sub_status (status),
                INDEX idx_tt_sub_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS timetable_substitutions');
        $this->schema('DROP TABLE IF EXISTS timetable_slots');
        $this->schema('DROP TABLE IF EXISTS timetable_class_allocations');
        $this->schema('DROP TABLE IF EXISTS timetable_lectures');
        $this->schema('DROP TABLE IF EXISTS timetables');
    }
}

