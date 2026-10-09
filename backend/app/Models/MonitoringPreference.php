<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringPreference extends Model
{
    protected $fillable = [
        'user_id',
        'live_enabled',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'live_enabled' => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(
            config('tenancy.database.central_connection') ?: config('database.default', 'central')
        );
    }
}
