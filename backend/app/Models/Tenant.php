<?php

namespace App\Models;

use App\Models\Concerns\UsesQueryCache;
use Rennokki\QueryCache\Traits\QueryCacheable;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, QueryCacheable, UsesQueryCache;

    protected $fillable = [
        'id',
        "db_name",
        "db_host",
        "db_username",
        "db_password",
        "db_connection",
        "db_port",
        'status',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
    ];

    protected function getCacheBaseTags(): array
    {
        return $this->buildQueryCacheBaseTags();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_tenants', 'tenant_id', 'user_id');
    }
}
