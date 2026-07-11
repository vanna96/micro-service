<?php

namespace App\Models;

use Illuminate\Support\Arr;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Activity
{
    public function __construct(array $attributes = [])
    {
        if (! isset($this->connection)) {
            if (tenant()) {
                $this->setConnection(tenant()->database_connection_name ?: 'tenant');
            } else {
                $this->setConnection(config('activitylog.database_connection') ?: config('tenancy.database.central_connection', config('database.default', 'central')));
            }
        }

        parent::__construct($attributes);
    }

    public function getActorLabelAttribute(): string
    {
        $actor = (array) $this->getExtraProperty('actor', []);

        foreach (['name', 'email', 'username'] as $key) {
            $value = trim((string) Arr::get($actor, $key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return $this->causer_id ? 'User #' . $this->causer_id : 'System';
    }

    public function getActorScopeLabelAttribute(): string
    {
        $scope = (string) $this->getExtraProperty('actor.scope', '');

        return $scope !== '' ? ucfirst($scope) : 'System';
    }

    public function getSubjectLabelAttribute(): string
    {
        $subject = (array) $this->getExtraProperty('subject', []);
        $label = trim((string) Arr::get($subject, 'label', ''));

        if ($label !== '') {
            return $label;
        }

        $type = class_basename((string) ($this->subject_type ?: 'Record'));
        $id = $this->subject_id ? ' #' . $this->subject_id : '';

        return $type . $id;
    }
}
