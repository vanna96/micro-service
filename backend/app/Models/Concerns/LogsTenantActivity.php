<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsTenantActivity
{
    use LogsActivity {
        shouldLogEvent as protected shouldLogTenantActivityEvent;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->activityLogName())
            ->logFillable()
            ->logExcept($this->activityLogExceptAttributes())
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly($this->activityLogIgnoredOnlyAttributes())
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => $eventName);
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        app(\App\Services\TenantActivityLogger::class)
            ->enrichActivity($activity, $this, auth()->user(), $eventName);
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        if (! tenant()) {
            return false;
        }

        return $this->shouldLogTenantActivityEvent($eventName);
    }

    protected function activityLogName(): string
    {
        return Str::snake(class_basename($this));
    }

    protected function activityLogExceptAttributes(): array
    {
        return ['password', 'remember_token'];
    }

    protected function activityLogIgnoredOnlyAttributes(): array
    {
        return [];
    }

    public function activitySubjectLabel(): string
    {
        foreach (['name', 'title', 'code', 'username', 'email', 'sku'] as $attribute) {
            $value = trim((string) $this->getAttribute($attribute));

            if ($value !== '') {
                return $value;
            }
        }

        return class_basename($this) . ' #' . $this->getKey();
    }
}
