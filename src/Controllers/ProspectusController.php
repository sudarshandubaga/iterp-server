<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Core\Validator;
use Iterp\Models\Prospectus;

/**
 * Controller for Prospectus Management.
 */
class ProspectusController
{
    protected function rules(): array
    {
        return [
            'prospectus_no'    => 'nullable|string|max:100',
            'candidate_name'   => 'required|string|max:255',
            'father_name'      => 'nullable|string|max:255',
            'mother_name'      => 'nullable|string|max:255',
            'mobile_no'        => 'nullable|string|max:50',
            'email'            => 'nullable|email|max:255',
            'class_id'         => 'nullable|integer|exists:academic_classes,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'firm_id'          => 'nullable|integer|exists:firms,id',
            'amount'           => 'nullable|numeric|min:0',
            'payment_mode'     => 'nullable|string|max:50',
            'reference_no'     => 'nullable|string|max:100',
            'issue_date'       => 'required|date',
            'status'           => 'nullable|in:issued,registered,cancelled',
            'remarks'          => 'nullable|string',
        ];
    }

    public function index(Request $request): Response
    {
        $bindings = [];
        $where = 'WHERE p.deleted_at IS NULL';

        $academicYearId = $request->query('academic_year_id');
        if ($academicYearId !== null && $academicYearId !== '') {
            $bindings['academic_year_id'] = (int) $academicYearId;
            $where .= ' AND p.academic_year_id = :academic_year_id';
        }

        $firmId = $request->query('firm_id');
        if ($firmId !== null && $firmId !== '') {
            $bindings['firm_id'] = (int) $firmId;
            $where .= ' AND p.firm_id = :firm_id';
        }

        $classId = $request->query('class_id');
        if ($classId !== null && $classId !== '') {
            $bindings['class_id'] = (int) $classId;
            $where .= ' AND p.class_id = :class_id';
        }

        $status = $request->query('status');
        if ($status !== null && $status !== '') {
            $bindings['status'] = (string) $status;
            $where .= ' AND p.status = :status';
        }

        $paymentMode = $request->query('payment_mode');
        if ($paymentMode !== null && $paymentMode !== '') {
            $bindings['payment_mode'] = (string) $paymentMode;
            $where .= ' AND p.payment_mode = :payment_mode';
        }

        $fromDate = $request->query('from_date');
        if ($fromDate !== null && $fromDate !== '') {
            $bindings['from_date'] = (string) $fromDate;
            $where .= ' AND p.issue_date >= :from_date';
        }

        $toDate = $request->query('to_date');
        if ($toDate !== null && $toDate !== '') {
            $bindings['to_date'] = (string) $toDate;
            $where .= ' AND p.issue_date <= :to_date';
        }

        $search = $request->query('search');
        if ($search !== null && trim((string) $search) !== '') {
            $term = '%' . trim((string) $search) . '%';
            $bindings['s1'] = $term;
            $bindings['s2'] = $term;
            $bindings['s3'] = $term;
            $bindings['s4'] = $term;
            $bindings['s5'] = $term;
            $where .= ' AND (p.prospectus_no LIKE :s1 OR p.candidate_name LIKE :s2
                       OR p.father_name LIKE :s3 OR p.mobile_no LIKE :s4
                       OR p.email LIKE :s5)';
        }

        $paginate = $request->query('page') !== null || $request->query('per_page') !== null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 15);
        if ($perPage < 1) $perPage = 15;
        if ($perPage > 100) $perPage = 100;

        $countSql = "SELECT COUNT(*) FROM prospectus p {$where}";
        $countStmt = Database::pdo()->prepare($countSql);
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT p.*,
                       ac.name AS class_name,
                       ac.short_name AS class_short_name,
                       ay.name AS academic_year_name,
                       f.name AS firm_name
                FROM prospectus p
                LEFT JOIN academic_classes ac ON ac.id = p.class_id
                LEFT JOIN academic_years ay ON ay.id = p.academic_year_id
                LEFT JOIN firms f ON f.id = p.firm_id
                {$where}
                ORDER BY p.issue_date DESC, p.id DESC";

        if ($paginate) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = Database::pdo()->prepare($sql);
        if ($paginate) {
            $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        }
        foreach ($bindings as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($paginate) {
            return Response::paginated($items, $total, $page, $perPage);
        }
        return Response::success($items);
    }

    public function show(Request $request, array $context = [], array $params = []): Response
    {
        $id = (int) ($params['id'] ?? $context['id'] ?? 0);
        $sql = "SELECT p.*,
                       ac.name AS class_name,
                       ac.short_name AS class_short_name,
                       ay.name AS academic_year_name,
                       f.name AS firm_name
                FROM prospectus p
                LEFT JOIN academic_classes ac ON ac.id = p.class_id
                LEFT JOIN academic_years ay ON ay.id = p.academic_year_id
                LEFT JOIN firms f ON f.id = p.firm_id
                WHERE p.id = :id AND p.deleted_at IS NULL
                LIMIT 1";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$record) {
            return Response::error('Prospectus not found.', 404);
        }

        return Response::success($record);
    }

    public function store(Request $request): Response
    {
        $data = $request->all();
        $validator = Validator::make($data, $this->rules());

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        $academicYearId = !empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : null;

        // Auto-generate prospectus_no if blank
        $prospectusNo = trim((string) ($data['prospectus_no'] ?? ''));
        if ($prospectusNo === '') {
            $prospectusNo = Prospectus::generateNextNumber($academicYearId);
        }

        // Uniqueness check
        $existsStmt = Database::pdo()->prepare(
            'SELECT id FROM prospectus WHERE prospectus_no = :no AND deleted_at IS NULL LIMIT 1'
        );
        $existsStmt->execute(['no' => $prospectusNo]);
        if ($existsStmt->fetchColumn()) {
            return Response::error("Prospectus number '{$prospectusNo}' is already in use.", 422, [
                'prospectus_no' => ["Prospectus number '{$prospectusNo}' already exists."],
            ]);
        }

        $data['prospectus_no'] = $prospectusNo;
        $data['status'] = $data['status'] ?? 'issued';
        $data['payment_mode'] = $data['payment_mode'] ?? 'Cash';
        $data['amount'] = isset($data['amount']) ? (float) $data['amount'] : 0.00;

        $prospectus = new Prospectus();
        $prospectus->fill($data);
        $prospectus->save();

        return Response::success(
            $this->show($request, [], ['id' => $prospectus->id()])->data ?? $prospectus->attributes,
            'Prospectus issued successfully.',
            201
        );
    }

    public function update(Request $request, array $context = [], array $params = []): Response
    {
        $id = (int) ($params['id'] ?? $context['id'] ?? 0);
        $prospectus = Prospectus::find($id);

        if (!$prospectus || $prospectus->deleted_at !== null) {
            return Response::error('Prospectus not found.', 404);
        }

        $data = $request->all();
        $validator = Validator::make($data, $this->rules());

        if ($validator->fails()) {
            return Response::error('Validation failed.', 422, $validator->errors());
        }

        if (!empty($data['prospectus_no']) && $data['prospectus_no'] !== $prospectus->prospectus_no) {
            $existsStmt = Database::pdo()->prepare(
                'SELECT id FROM prospectus WHERE prospectus_no = :no AND id != :id AND deleted_at IS NULL LIMIT 1'
            );
            $existsStmt->execute(['no' => $data['prospectus_no'], 'id' => $id]);
            if ($existsStmt->fetchColumn()) {
                return Response::error("Prospectus number is already in use.", 422, [
                    'prospectus_no' => ["Prospectus number already exists."],
                ]);
            }
        }

        $prospectus->fill($data);
        $prospectus->save();

        return Response::success(
            $this->show($request, [], ['id' => $id])->data ?? $prospectus->attributes,
            'Prospectus updated successfully.'
        );
    }

    public function destroy(Request $request, array $context = [], array $params = []): Response
    {
        $id = (int) ($params['id'] ?? $context['id'] ?? 0);
        $stmt = Database::pdo()->prepare(
            'UPDATE prospectus SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            return Response::error('Prospectus not found or already deleted.', 404);
        }

        return Response::success(null, 'Prospectus deleted successfully.');
    }

    public function nextNumber(Request $request): Response
    {
        $yearId = $request->query('academic_year_id');
        $num = Prospectus::generateNextNumber($yearId ? (int) $yearId : null);
        return Response::success(['next_number' => $num]);
    }
}
