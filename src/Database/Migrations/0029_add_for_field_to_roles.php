<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Add for and role_for column to roles table.
 * Supports categories: Teaching, Non-Teaching, Management, Academic (Student, Parent).
 */
class AddForFieldToRolesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            "ALTER TABLE roles 
             ADD COLUMN `for` VARCHAR(50) NOT NULL DEFAULT 'Teaching' AFTER name,
             ADD COLUMN role_for VARCHAR(50) NOT NULL DEFAULT 'Teaching' AFTER `for`,
             ADD INDEX idx_roles_for (`for`),
             ADD INDEX idx_roles_role_for (role_for)"
        );

        // Update existing roles:
        // 1. Teaching
        $this->schema(
            "UPDATE roles SET `for` = 'Teaching', role_for = 'Teaching' 
             WHERE name IN (
                 'Asst. Teacher', 'Computer Teacher', 'Art & Craft Teacher', 
                 'Hindi Teacher', 'Dance Teacher', 'Sport Teacher', 
                 'School Coordinator', 'Librarian'
             )"
        );

        // 2. Non-Teaching
        $this->schema(
            "UPDATE roles SET `for` = 'Non-Teaching', role_for = 'Non-Teaching' 
             WHERE name IN (
                 'Conductor', 'Driver', 'Guard', 'Maid', 'Office Assistant', 
                 'Sweeper', 'Rickshaw Puller', 'I.T. Assitant'
             )"
        );

        // 3. Management
        $this->schema(
            "UPDATE roles SET `for` = 'Management', role_for = 'Management' 
             WHERE name IN (
                 'Super Admin', 'Principal', 'Vice Principal', 
                 'Account Manager', 'Supervisor'
             )"
        );

        // 4. Academic (Student, Parent)
        $this->schema(
            "UPDATE roles SET `for` = 'Academic', role_for = 'Academic' 
             WHERE name IN ('Student', 'Parent')"
        );
    }

    public function down(): void
    {
        $this->schema(
            "ALTER TABLE roles 
             DROP COLUMN `for`,
             DROP COLUMN role_for"
        );
    }
}
