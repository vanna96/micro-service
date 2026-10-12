<?php

namespace App\Models;

use App\Models\Concerns\UsesQueryCache;
use Rennokki\QueryCache\Traits\QueryCacheable;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

class Domain extends BaseDomain
{
    use QueryCacheable, UsesQueryCache;

    protected function getCacheBaseTags(): array
    {
        return $this->buildQueryCacheBaseTags();
    }
}
