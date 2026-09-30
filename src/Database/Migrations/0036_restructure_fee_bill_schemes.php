<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;
use PDO;

/**
 * Restructure Fee Bill Schemes into a 3-tier hierarchy:
 *
 * 1. fee_bill_schemes:
 *    - firm_id (FK to firms)
 *    - session_id (FK to academic_years)
 *    - name
 *    - slab (numeric: count of slabs)
 *    - description
 *
 * 2. fee_bill_scheme_slabs:
 *    - fee_bill_scheme_id (FK to fee_bill_schemes)
 *    - slab_no
 *    - due_date
 *
 * 3. fee_bill_scheme_amounts:
 *    - fee_bill_scheme_slab_id (FK to fee_bill_scheme_slabs)
 *    - fee_head_id (FK to fee_heads)
 *    - amount
 */
class RestructureFeeBillSchemesTable extends Migration
{
    public function up(): void
    {
        $pdo = $this->connection();

        // 1. Ensure fee_bill_schemes.slab is converted to INT numeric
        $colStmt = $pdo->query("SHOW COLUMNS FROM fee_bill_schemes LIKE 'slab'");
        $col = $colStmt->fetch(PDO::FETCH_ASSOC);

        if ($col && stripos((string) $col['Type'], 'int') === false) {
            // Update textual presets to numbers first
            $this->schema("
                UPDATE fee_bill_schemes 
                SET slab = CASE 
                    WHEN slab LIKE '%12%' OR LOWER(slab) LIKE '%month%' THEN '12'
                    WHEN slab LIKE '%6%'  OR LOWER(slab) LIKE '%bi-month%' THEN '6'
                    WHEN slab LIKE '%4%'  OR LOWER(slab) LIKE '%quarter%' THEN '4'
                    WHEN slab LIKE '%2%'  OR LOWER(slab) LIKE '%half%' THEN '2'
                    WHEN slab LIKE '%1%'  OR LOWER(slab) LIKE '%annual%' THEN '1'
                    WHEN slab REGEXP '^[0-9]+$' THEN slab
                    ELSE '1'
                END
                WHERE slab IS NOT NULL
            ");

            $this->schema('ALTER TABLE fee_bill_schemes MODIFY COLUMN slab INT NOT NULL DEFAULT 1');
        }

        // 2. Create fee_bill_scheme_slabs table
        $this->schema('
            CREATE TABLE IF NOT EXISTS fee_bill_scheme_slabs (
                id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fee_bill_scheme_id  BIGINT UNSIGNED NOT NULL,
                slab_no             INT NOT NULL DEFAULT 1,
                due_date            DATE NULL DEFAULT NULL,
                created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                CONSTRAINT fk_fbs_slabs_scheme FOREIGN KEY (fee_bill_scheme_id) REFERENCES fee_bill_schemes (id) ON DELETE CASCADE,
                INDEX idx_fbs_slabs_scheme (fee_bill_scheme_id),
                INDEX idx_fbs_slabs_no (slab_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // 3. Migrate fee_bill_scheme_amounts to link to fee_bill_scheme_slab_id
        $amtColStmt = $pdo->query("SHOW COLUMNS FROM fee_bill_scheme_amounts LIKE 'fee_bill_scheme_slab_id'");
        $amtCol = $amtColStmt->fetch(PDO::FETCH_ASSOC);

        if (!$amtCol) {
            // Check if old fee_bill_scheme_id exists in fee_bill_scheme_amounts
            $oldColStmt = $pdo->query("SHOW COLUMNS FROM fee_bill_scheme_amounts LIKE 'fee_bill_scheme_id'");
            $hasOldCol = (bool) $oldColStmt->fetch(PDO::FETCH_ASSOC);

            // Create temporary new table with unique foreign key names
            $this->schema('
                CREATE TABLE IF NOT EXISTS fee_bill_scheme_amounts_new (
                    id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    fee_bill_scheme_slab_id  BIGINT UNSIGNED NOT NULL,
                    fee_head_id              BIGINT UNSIGNED NULL DEFAULT NULL,
                    amount                   DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    CONSTRAINT fk_fbs_amt_slab FOREIGN KEY (fee_bill_scheme_slab_id) REFERENCES fee_bill_scheme_slabs (id) ON DELETE CASCADE,
                    CONSTRAINT fk_fbs_amt_head FOREIGN KEY (fee_head_id) REFERENCES fee_heads (id) ON DELETE SET NULL,
                    INDEX idx_fbs_amt_slab (fee_bill_scheme_slab_id),
                    INDEX idx_fbs_amt_head (fee_head_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');

            if ($hasOldCol) {
                // Populate fee_bill_scheme_slabs from existing amounts
                $oldRows = $pdo->query('SELECT * FROM fee_bill_scheme_amounts')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($oldRows as $row) {
                    $schemeId = (int) $row['fee_bill_scheme_id'];
                    $slabNo = (int) ($row['slab_no'] ?? 1);
                    $dueDate = !empty($row['due_date']) ? (string) $row['due_date'] : null;

                    // Check if slab already inserted
                    $sStmt = $pdo->prepare('SELECT id FROM fee_bill_scheme_slabs WHERE fee_bill_scheme_id = ? AND slab_no = ? LIMIT 1');
                    $sStmt->execute([$schemeId, $slabNo]);
                    $slabId = $sStmt->fetchColumn();

                    if (!$slabId) {
                        $ins = $pdo->prepare('INSERT INTO fee_bill_scheme_slabs (fee_bill_scheme_id, slab_no, due_date, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
                        $ins->execute([$schemeId, $slabNo, $dueDate]);
                        $slabId = (int) $pdo->lastInsertId();
                    }

                    // Insert amount into new table
                    $insAmt = $pdo->prepare('INSERT INTO fee_bill_scheme_amounts_new (fee_bill_scheme_slab_id, fee_head_id, amount, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
                    $insAmt->execute([
                        $slabId,
                        !empty($row['fee_head_id']) ? (int) $row['fee_head_id'] : null,
                        (float) ($row['amount'] ?? 0.0),
                    ]);
                }
            }

            // Drop old table and rename new table
            $this->schema('DROP TABLE IF EXISTS fee_bill_scheme_amounts');
            $this->schema('RENAME TABLE fee_bill_scheme_amounts_new TO fee_bill_scheme_amounts');
        }

        // View alias fee_bill_scheme -> fee_bill_schemes for convenience
        $this->schema('CREATE OR REPLACE VIEW fee_bill_scheme AS SELECT * FROM fee_bill_schemes');
    }

    public function down(): void
    {
        $this->schema('DROP VIEW IF EXISTS fee_bill_scheme');
        $this->schema('DROP TABLE IF EXISTS fee_bill_scheme_amounts');
        $this->schema('DROP TABLE IF EXISTS fee_bill_scheme_slabs');

        // Re-create old fee_bill_scheme_amounts
        $this->schema('
            CREATE TABLE IF NOT EXISTS fee_bill_scheme_amounts (
                id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fee_bill_scheme_id  BIGINT UNSIGNED NOT NULL,
                slab_no             INT NOT NULL DEFAULT 1,
                slab_name           VARCHAR(150) NOT NULL,
                fee_head_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                amount              DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                due_date            DATE NULL DEFAULT NULL,
                created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                CONSTRAINT fk_fbs_amounts_scheme FOREIGN KEY (fee_bill_scheme_id) REFERENCES fee_bill_schemes (id) ON DELETE CASCADE,
                CONSTRAINT fk_fbs_amounts_head FOREIGN KEY (fee_head_id) REFERENCES fee_heads (id) ON DELETE SET NULL,
                INDEX idx_fbs_amounts_scheme (fee_bill_scheme_id),
                INDEX idx_fbs_amounts_head (fee_head_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $this->schema('ALTER TABLE fee_bill_schemes MODIFY COLUMN slab VARCHAR(100) NOT NULL');
    }
}
