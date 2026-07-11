<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\Activitylog\Contracts\Activity;

class TenantActivityLogger
{
    public function log(
        string $description,
        array $properties = [],
        ?Model $subject = null,
        ?Model $causer = null,
        ?string $event = null,
        ?string $logName = null
    ): ?Activity {
        if (! tenant()) {
            return null;
        }

        $logger = activity()->useLog($logName ?: config('activitylog.default_log_name'));

        if ($event !== null) {
            $logger->event($event);
        }

        if ($subject instanceof Model) {
            $logger->performedOn($subject);
        }

        if ($causer instanceof Model) {
            $logger->causedBy($causer);
        }

        $logger->tap(function (Activity $activity) use ($subject, $causer, $event): void {
            $this->enrichActivity($activity, $subject, $causer, $event);
        }, $event);

        return $logger
            ->withProperties($properties)
            ->log($description);
    }

    public function enrichActivity(Activity $activity, ?Model $subject = null, ?Model $causer = null, ?string $event = null): void
    {
        $properties = collect($activity->properties ? $activity->properties->toArray() : []);

        $properties->put('tenant', $this->tenantProperties());
        $properties->put('actor', $this->actorProperties($causer));

        if ($subject instanceof Model) {
            $properties->put('subject', $this->subjectProperties($subject));
        }

        $requestProperties = $this->requestProperties();

        if ($requestProperties !== []) {
            $properties->put('request', $requestProperties);
        }

        if ($event !== null && ! $properties->has('event_label')) {
            $properties->put('event_label', ucfirst($event));
        }

        $activity->properties = $properties;
    }

    public function actorProperties(?Model $causer = null): array
    {
        $user = $causer ?: auth()->user();

        if (! $user instanceof Model) {
            return [
                'scope' => 'system',
            ];
        }

        return [
            'id' => $user->getKey(),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'username' => $user->getAttribute('username'),
            'scope' => function_exists('admin_auth_scope') ? admin_auth_scope() : 'web',
        ];
    }

    public function subjectProperties(Model $subject): array
    {
        $label = method_exists($subject, 'activitySubjectLabel')
            ? $subject->activitySubjectLabel()
            : class_basename($subject) . ' #' . $subject->getKey();

        return [
            'type' => class_basename($subject),
            'id' => $subject->getKey(),
            'label' => $label,
        ];
    }

    public function tenantProperties(): array
    {
        if (! tenant()) {
            return [];
        }

        return [
            'id' => tenant()->getTenantKey(),
            'name' => function_exists('admin_tenant_display_name') ? admin_tenant_display_name(tenant()) : (string) tenant()->getTenantKey(),
        ];
    }

    public function requestProperties(?Request $request = null): array
    {
        $request = $request ?: request();

        if (! $request instanceof Request) {
            return [];
        }

        return array_filter([
            'method' => $request->method(),
            'route' => optional($request->route())->getName(),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], fn ($value) => filled($value));
    }
}
