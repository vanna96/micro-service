<?php

namespace App\Repositories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TenantRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.tenants';
    protected $model = Tenant::class;

    public function getAdminListing(string $search = ''): Collection
    {
        return $this->createModel()
            ->newQuery()
            ->with('domains')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('id', 'like', "%{$search}%")
                        ->orWhere('alias', 'like', "%{$search}%")
                        ->orWhere('data->db_name', 'like', "%{$search}%")
                        ->orWhere('data->db_host', 'like', "%{$search}%")
                        ->orWhere('data->db_username', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function loadForAdminEdit(Tenant $tenant): Tenant
    {
        return tap($tenant)->load('domains');
    }

    public function createForAdmin(array $attributes): Tenant
    {
        $domain = $attributes['domain'] ?? null;
        unset($attributes['domain']);

        /** @var \App\Models\Tenant $tenant */
        $tenant = $this->createModel();
        $tenant->fill($attributes);
        $tenant->save();

        $domainValue = ! empty($domain) ? strtolower(trim((string) $domain)) : $this->makeTenantDomain($tenant->alias);

        $tenant->domains()->create([
            'domain' => $domainValue,
        ]);

        return $tenant;
    }

    public function updateForAdmin(Tenant $tenant, array $attributes): Tenant
    {
        $domain = $attributes['domain'] ?? null;
        unset($attributes['domain']);

        $tenant->fill($attributes);
        $tenant->save();

        $domainValue = ! empty($domain) ? strtolower(trim((string) $domain)) : $this->makeTenantDomain($tenant->alias);

        $existingDomain = $tenant->domains()->first();
        if ($existingDomain) {
            $existingDomain->update(['domain' => $domainValue]);
        } else {
            $tenant->domains()->create(['domain' => $domainValue]);
        }

        return $tenant;
    }

    public function deleteForAdmin(Tenant $tenant): void
    {
        DB::connection('central')->table('user_tenants')->where('tenant_id', $tenant->id)->delete();
        $tenant->delete();
    }

    private function makeTenantDomain(?string $alias): string
    {
        return ($alias ?: 'tenant') . '.' . config('tenancy.tenant_host', 'localhost');
    }
}
