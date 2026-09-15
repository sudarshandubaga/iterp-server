<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Adds multi-tenancy reference columns to tables that were created before the
 * firm / tenant scoping existed.
 *
 *   - firm_id  -> academic_classes, academic_years, events, roles, users
 *   - tenant_id-> attendance_legends, documents, custom_field_categories,
 *                 titles, users
 *
 * Columns are added NULL so existing (already-seeded) rows stay valid; records
 * can be back-filled and assigned to a firm/tenant afterwards.
 */
class AddFirmAndTenantReferencesTable extends Migration
{
    public function up(): void
    {
        // --- firm_id -----------------------------------------------------
        foreach ([
            'academic_classes' => 'academic_classes',
            'academic_years'   => 'academic_years',
            'events'           => 'events',
            'roles'            => 'roles',
        ] as $table => $label) {
            $this->schema("ALTER TABLE `{$table}`
                ADD COLUMN firm_id BIGINT UNSIGNED NULL DEFAULT NULL,
                ADD INDEX `idx_{$table}_firm` (firm_id),
                ADD CONSTRAINT `fk_{$table}_firm`
                    FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE RESTRICT");
        }

        // users carries both a firm and a tenant.
        $this->schema('ALTER TABLE `users`
            ADD COLUMN firm_id BIGINT UNSIGNED NULL DEFAULT NULL,
            ADD INDEX `idx_users_firm` (firm_id),
            ADD CONSTRAINT `fk_users_firm`
                FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE RESTRICT');

        // --- tenant_id ----------------------------------------------------
        foreach ([
            'attendance_legends'      => 'attendance_legends',
            'documents'               => 'documents',
            'custom_field_categories' => 'custom_field_categories',
            'titles'                  => 'titles',
        ] as $table => $label) {
            $this->schema("ALTER TABLE `{$table}`
                ADD COLUMN tenant_id BIGINT UNSIGNED NULL DEFAULT NULL,
                ADD INDEX `idx_{$table}_tenant` (tenant_id),
                ADD CONSTRAINT `fk_{$table}_tenant`
                    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT");
        }

        $this->schema('ALTER TABLE `users`
            ADD COLUMN tenant_id BIGINT UNSIGNED NULL DEFAULT NULL,
            ADD INDEX `idx_users_tenant` (tenant_id),
            ADD CONSTRAINT `fk_users_tenant`
                FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        // --- firm_id -----------------------------------------------------
        foreach (['academic_classes', 'academic_years', 'events', 'roles'] as $table) {
            $this->schema("ALTER TABLE `{$table}` DROP FOREIGN KEY `fk_{$table}_firm`");
            $this->schema("ALTER TABLE `{$table}` DROP INDEX `idx_{$table}_firm`");
            $this->schema("ALTER TABLE `{$table}` DROP COLUMN firm_id");
        }

        $this->schema('ALTER TABLE `users` DROP FOREIGN KEY `fk_users_firm`');
        $this->schema('ALTER TABLE `users` DROP INDEX `idx_users_firm`');
        $this->schema('ALTER TABLE `users` DROP COLUMN firm_id');

        // --- tenant_id ----------------------------------------------------
        foreach (['attendance_legends', 'documents', 'custom_field_categories', 'titles'] as $table) {
            $this->schema("ALTER TABLE `{$table}` DROP FOREIGN KEY `fk_{$table}_tenant`");
            $this->schema("ALTER TABLE `{$table}` DROP INDEX `idx_{$table}_tenant`");
            $this->schema("ALTER TABLE `{$table}` DROP COLUMN tenant_id");
        }

        $this->schema('ALTER TABLE `users` DROP FOREIGN KEY `fk_users_tenant`');
        $this->schema('ALTER TABLE `users` DROP INDEX `idx_users_tenant`');
        $this->schema('ALTER TABLE `users` DROP COLUMN tenant_id');
    }
}