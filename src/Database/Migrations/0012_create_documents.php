<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * documents table.
 */
class CreateDocumentsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS documents (
                id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name         VARCHAR(255) NOT NULL,
                short_name   VARCHAR(50)  NOT NULL,
                document_for ENUM("student","employee","both") NOT NULL DEFAULT "both",
                created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at   TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_documents_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS documents');
    }
}