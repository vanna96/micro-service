<?php

namespace App\Models;

use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\Traits\QueryCacheable;

class Gallery extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache;
    
    protected $fillable = [
        'gallarieable_type',
        'gallarieable_id',
        'type',
        'status',
        'name'
    ];

    protected function getCacheBaseTags(): array
    {
        return $this->buildQueryCacheBaseTags();
    }

    public function imageable()
    {
        return $this->morphTo();
    }
}
