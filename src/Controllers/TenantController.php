<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Database;
use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Models\Tenant;

/**
 * CRUD API for tenants. Exposes a `resolveByDomain` endpoint used to look up
 * the tenant from the request's host — the tenant is never picked from a form.
 */
class TenantController extends CrudController
{
    protected string $model = Tenant::class;

    protected array $rules = [
        'name'   => 'required|string|max:255',
        'logo'   => 'nullable|string|max:255',
        'favicon' => 'nullable|string|max:255',
        'domain' => 'nullable|string|max:255',
        'email'  => 'nullable|email|max:255',
        'phone_no' => 'nullable|string|max:50',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name', 'domain', 'email'];

    /**
     * GET /api/tenant/resolve — match a domain against the tenants table. The
     * domain is the browser's hostname passed by the client (`?domain=...`),
     * falling back to the request's Host header. Returns the tenant payload, or
     * 404 if no tenant exists for that domain.
     */
    public function resolveByDomain(Request $request): Response
    {
        $tenant = $this->findTenantByDomain($request, $request->query('domain'));
        if ($tenant === null) {
            return Response::error('Tenant not found for this domain.', 404);
        }
        return Response::success($tenant);
    }

    /**
     * Resolve the tenant id for the request's Host header — or the explicitly
     * supplied domain (the client's own hostname) when given. Returns null when
     * no tenant matches.
     */
    public static function resolveTenantId(Request $request, ?string $domain = null): ?int
    {
        if ($domain === null || $domain === '') {
            $host = trim((string) $request->header('Host', ''));
            $domain = $host !== '' ? $host : null;
        }

        if ($domain === null) {
            return null;
        }

        $domain = strtolower(trim((string) parse_url(
            str_starts_with($domain, 'http') ? $domain : 'http://' . $domain,
            PHP_URL_HOST
        ) ?: $domain));

        $stmt = Database::pdo()->prepare(
            'SELECT id FROM tenants WHERE LOWER(domain) = :d AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['d' => $domain]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function findTenantByDomain(Request $request, ?string $domain = null): ?array
    {
        $id = self::resolveTenantId($request, $domain);
        if ($id === null) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id, name, logo, favicon, domain, email, phone_no
             FROM tenants WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}