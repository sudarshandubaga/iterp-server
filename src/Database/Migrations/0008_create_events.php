<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * events table.
 */
class CreateEventsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS events (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                type            ENUM("event","assessment","holiday","sport") NOT NULL,
                name            VARCHAR(255) NOT NULL,
                start_date      DATE NOT NULL,
                end_date        DATE NOT NULL,
                description     TEXT NULL,
                mark_attendance ENUM("yes","no") NOT NULL DEFAULT "no",
                event_for       ENUM("employee","student","both") NOT NULL DEFAULT "both",
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at      TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_events_type (type),
                INDEX idx_events_name (name),
                INDEX idx_events_dates (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS events');
    }
}