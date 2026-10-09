<?php

namespace App\Monitoring\DTO;

class ComponentStatus
{
    public const DISABLED = 'disabled';

    public const NOT_CONFIGURED = 'not_configured';

    public const CONNECTED = 'connected';

    public const UNAVAILABLE = 'unavailable';

    public string $name;

    public string $state;

    public ?string $detail;

    public function __construct(string $name, string $state, ?string $detail = null)
    {
        $this->name = $name;
        $this->state = $state;
        $this->detail = $detail;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'state' => $this->state,
            'detail' => $this->detail,
        ];
    }
}
