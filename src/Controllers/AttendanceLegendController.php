<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Models\AttendanceLegend;
use Throwable;

/**
 * CRUD API for attendance legends (Annual Settings > Attendance Legends).
 * Supports sort_order with drag-and-drop reordering.
 */
class AttendanceLegendController extends CrudController
{
    protected string $model = AttendanceLegend::class;

    protected array $rules = [
        'name'         => 'required|string|max:255',
        'short_name'   => 'required|string|max:50',
        'treat_as'     => 'required|in:present,absent',
        'total_leaves' => 'nullable|integer',
        'legend_for'   => 'nullable|in:student,employee,both',
        'sort_order'   => 'nullable|integer',
    ];

    protected string $orderBy = 'sort_order';

    protected array $searchable = ['name', 'short_name'];

    /**
     * Self-healing check: ensure `sort_order` column exists on `attendance_legends`
     * so sorting & drag-and-drop work even before CLI migrations are run.
     */
    public static function ensureSortOrderColumn(\PDO $pdo): void
    {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM attendance_legends LIKE 'sort_order'")->fetchAll();
            if (empty($cols)) {
                $pdo->exec(
                    'ALTER TABLE attendance_legends
                        ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_leaves,
                        ADD INDEX idx_attendance_legends_sort_order (sort_order)'
                );

                $rows = $pdo->query(
                    "SELECT id FROM attendance_legends ORDER BY FIELD(treat_as, 'present', 'absent'), id ASC"
                )->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $upStmt = $pdo->prepare('UPDATE attendance_legends SET sort_order = :ord WHERE id = :id');
                    $pos = 1;
                    foreach ($rows as $row) {
                        $upStmt->execute([
                            'id'  => (int) $row['id'],
                            'ord' => $pos++,
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {
            // Best-effort self-healing; ignore if column already exists or DDL restricted.
        }
    }

    public function index(Request $request): Response
    {
        self::ensureSortOrderColumn(Database::pdo());
        return parent::index($request);
    }

    public function store(Request $request): Response
    {
        self::ensureSortOrderColumn(Database::pdo());
        return parent::store($request);
    }

    public function update(Request $request, array $context, array $params): Response
    {
        self::ensureSortOrderColumn(Database::pdo());
        return parent::update($request, $context, $params);
    }

    public function reorder(Request $request): Response
    {
        self::ensureSortOrderColumn(Database::pdo());
        return parent::reorder($request);
    }

    protected function payload(array $data): array
    {
        if (array_key_exists('sort_order', $data) && ($data['sort_order'] === '' || $data['sort_order'] === null)) {
            unset($data['sort_order']);
        }
        if (!isset($data['sort_order']) && empty($data['id'])) {
            try {
                $maxOrder = (int) Database::pdo()->query('SELECT COALESCE(MAX(sort_order), 0) FROM attendance_legends')->fetchColumn();
                $data['sort_order'] = $maxOrder + 1;
            } catch (Throwable $e) {
                $data['sort_order'] = 0;
            }
        }
        return $data;
    }
}
