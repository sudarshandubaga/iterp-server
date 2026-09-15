<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Request;
use Iterp\Core\Response;
use Iterp\Models\Locality;

/**
 * CRUD API for localities (Master Settings > Location > Locality).
 *
 * Tenants are auto-resolved from the client-supplied domain (`?domain=` /
 * `domain` in the body) — never from the server's own Host header. When the
 * domain doesn't match any tenant, a 404 is returned instead of a partial
 * result. Optionally filter by `?city_id=`.
 */
class LocalityController extends CrudController
{
    protected string $model = Locality::class;

    protected array $rules = [
        'city_id'    => 'required|integer|exists:cities,id',
        'name'       => 'required|string|max:255',
        'short_name' => 'required|string|max:50',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name', 'short_name'];

    protected function applyFilters(Request $request, \Iterp\Core\QueryBuilder $query): void
    {
        if ($request->query('city_id') !== null) {
            $query->where('city_id', (int) $request->query('city_id'));
        }
    }

    /**
     * Inject the tenant id (resolved from the client-supplied domain) before
     * validation and persist. Returns a 404 if no tenant matches the domain.
     */
    protected function beforePersist(Request $request, array &$data): ?Response
    {
        $tenantId = TenantController::resolveTenantId($request, $request->input('domain'));
        if ($tenantId === null) {
            return Response::error('Tenant not found for this domain.', 404);
        }
        $data['tenant_id'] = $tenantId;
        return null;
    }

    /**
     * List localities for the tenant resolved from the client-supplied domain. A
     * 404 is returned when the domain doesn't match a tenant.
     */
    public function index(Request $request): Response
    {
        $tenantId = TenantController::resolveTenantId($request, $request->input('domain'));
        if ($tenantId === null) {
            return Response::error('Tenant not found for this domain.', 404);
        }
        $_GET['tenant_id'] = (string) $tenantId; // picked up by the base scoping
        return parent::index($request);
    }
}