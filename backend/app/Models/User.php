<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'profile_id',
        'email',
        'password',    
        'username', 
        'first_name',
        'last_name',
        'country_code',
        'phone',
        'gender',
        'dob',
        'status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // if inside a tenant request, use tenant DB
        if (tenant()) {
            $this->setConnection(tenant()->database_connection_name);
        } else {
            // fallback to central DB
            $this->setConnection('central');
        }
    }
    
    public function galleries()
    {
        return $this->morphMany(Gallery::class, 'gallarieable');
    }

    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'user_tenants', 'user_id', 'tenant_id');
    }
}
