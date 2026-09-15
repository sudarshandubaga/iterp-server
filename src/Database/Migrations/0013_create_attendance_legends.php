<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * attendance_legends table.
 */
class CreateAttendanceLegendsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS attendance_legends (
                id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name         VARCHAR(255) NOT NULL,
                short_name   VARCHAR(50)  NOT NULL,
                treat_as     ENUM("present","absent") NOT NULL,
                total_leaves INT NOT NULL DEFAULT 0,
                created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at   TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_attendance_legends_name (name),
                INDEX idx_attendance_legends_treat_as (treat_as)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS attendance_legends');
    }
}