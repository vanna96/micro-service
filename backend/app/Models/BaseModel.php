<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// use Spatie\Activitylog\LogOptions;
// use App\Traits\LogTrait;

class BaseModel extends Model
{
    // use LogTrait;
    use HasFactory;

    protected static $recordEvents = [
        'deleted',
        'created',
        'updated',
    ];

    // public function getActivitylogOptions(): LogOptions
    // {
    //     return LogOptions::defaults()
    //         ->logOnly($this->fillable ?? [])
    //         ->logOnlyDirty()
    //         ->dontSubmitEmptyLogs();
    // }

    public function getImageUrlAttribute(): string
    {
        $image = $this->image ?? $this->thumbnail ?? $this->banner;
        if (
            empty($image) ||
            !\Storage::disk($this::$disk)->exists($image)
        ) return null;

        return \Storage::disk($this::$disk)->url($image);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
