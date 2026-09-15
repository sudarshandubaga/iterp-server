<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Database;
use Iterp\Core\Migration;

/**
 * student_categories table.
 */
class CreateStudentCategoriesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS student_categories (
                id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(255) NOT NULL,
                status     ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_student_categories_name (name),
                INDEX idx_student_categories_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Seed default categories shown in the UI mockups
        $defaults = [
            ['name' => 'General', 'status' => 'active'],
            ['name' => 'Special', 'status' => 'active'],
            ['name' => 'Physically Challenged', 'status' => 'active'],
        ];

        $stmt = Database::pdo()->prepare(
            'INSERT INTO student_categories (name, status) VALUES (:name, :status)'
        );

        foreach ($defaults as $cat) {
            $stmt->execute($cat);
        }
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS student_categories');
    }
}
