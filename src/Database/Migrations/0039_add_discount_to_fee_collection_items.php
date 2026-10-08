<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Add discount_amount to fee_collection_items table.
 */
class AddDiscountToFeeCollectionItemsTable extends Migration
{
    public function up(): void
    {
        $pdo = $this->connection();

        $cols = $pdo->query("SHOW COLUMNS FROM fee_collection_items LIKE 'discount_amount'")->fetchAll();
        if (empty($cols)) {
            $this->schema('ALTER TABLE fee_collection_items ADD COLUMN discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER concession');
        }
    }

    public function down(): void
    {
        $pdo = $this->connection();

        $cols = $pdo->query("SHOW COLUMNS FROM fee_collection_items LIKE 'discount_amount'")->fetchAll();
        if (!empty($cols)) {
            $this->schema('ALTER TABLE fee_collection_items DROP COLUMN discount_amount');
        }
    }
}
