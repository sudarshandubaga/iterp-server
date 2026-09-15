<?php

declare(strict_types=1);

namespace Iterp\Database\Seeders;

use Iterp\Core\Seeder;

/**
 * Seeds sample events.
 */
class EventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            [
                'type'            => 'event',
                'name'            => 'Annual Day',
                'start_date'      => '2025-12-20',
                'end_date'        => '2025-12-20',
                'description'     => 'Annual cultural programme for staff and students.',
                'mark_attendance' => 'yes',
                'event_for'       => 'both',
            ],
            [
                'type'            => 'assessment',
                'name'            => 'Mid-Term Examination',
                'start_date'      => '2025-09-15',
                'end_date'        => '2025-09-25',
                'description'     => 'Mid-term exams for all classes.',
                'mark_attendance' => 'no',
                'event_for'       => 'student',
            ],
            [
                'type'            => 'holiday',
                'name'            => 'Independence Day',
                'start_date'      => '2025-08-15',
                'end_date'        => '2025-08-15',
                'description'     => 'National holiday.',
                'mark_attendance' => 'no',
                'event_for'       => 'both',
            ],
            [
                'type'            => 'sport',
                'name'            => 'Annual Sports Meet',
                'start_date'      => '2026-01-10',
                'end_date'        => '2026-01-12',
                'description'     => 'Inter-house sports competition.',
                'mark_attendance' => 'yes',
                'event_for'       => 'student',
            ],
            [
                'type'            => 'event',
                'name'            => 'Staff Development Workshop',
                'start_date'      => '2025-11-05',
                'end_date'        => '2025-11-06',
                'description'     => 'Training workshop for employees.',
                'mark_attendance' => 'yes',
                'event_for'       => 'employee',
            ],
        ];

        $exists = $this->connection()->prepare(
            'SELECT COUNT(*) FROM events WHERE type = :type AND name = :name AND deleted_at IS NULL'
        );
        $insert = $this->connection()->prepare(
            'INSERT INTO events (type, name, start_date, end_date, description, mark_attendance, event_for)
             VALUES (:type, :name, :start_date, :end_date, :description, :mark_attendance, :event_for)'
        );

        foreach ($events as $event) {
            $binds = ['type' => $event['type'], 'name' => $event['name']];
            $exists->execute($binds);
            if ((int) $exists->fetchColumn() > 0) {
                continue; // idempotent
            }

            $insert->execute($event);
            echo "  Seeded event: {$event['name']}\n";
        }
    }
}