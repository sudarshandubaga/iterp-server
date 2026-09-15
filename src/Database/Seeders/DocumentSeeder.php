<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Seeds student-facing documents (idempotent). These appear in the "Add
 * Student" flow and in the Documents master list.
 */
class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            ['name' => 'Birth Certificate', 'short_name' => 'Birth Cert', 'document_for' => 'student'],
            ['name' => 'Aadhaar Card', 'short_name' => 'Aadhaar', 'document_for' => 'both'],
            ['name' => 'Previous Report Card', 'short_name' => 'Report Card', 'document_for' => 'student'],
            ['name' => 'Transfer Certificate (TC)', 'short_name' => 'TC', 'document_for' => 'student'],
            ['name' => 'Passport Size Photo', 'short_name' => 'Photo', 'document_for' => 'both'],
            ['name' => 'Address Proof', 'short_name' => 'Address Proof', 'document_for' => 'both'],
        ];

        $exists = $this->connection()->prepare(
            'SELECT COUNT(*) FROM documents WHERE name = :name AND deleted_at IS NULL'
        );
        $insert = $this->connection()->prepare(
            'INSERT INTO documents (name, short_name, document_for) VALUES (:name, :short_name, :document_for)'
        );

        foreach ($documents as $doc) {
            $exists->execute(['name' => $doc['name']]);
            if ((int) $exists->fetchColumn() > 0) {
                continue; // idempotent
            }

            $insert->execute($doc);
            echo "  Seeded document: {$doc['name']}\n";
        }
    }
}