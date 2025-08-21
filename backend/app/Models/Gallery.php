<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'gallarieable_type',
        'gallarieable_id',
        'type',
        'status',
        'name'
    ];

    public function imageable()
    {
        return $this->morphTo();
    }
}
