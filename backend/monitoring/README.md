# Optional monitoring

The monitoring stack is isolated from the application lifecycle. Prometheus,
Node Exporter, and cAdvisor use the `monitoring` Compose profile and publish no
host ports. Caddy metrics use the internal monitoring network on port `9180`.
The Laravel metrics endpoint is blocked at public Caddy ingress.

## Enable

Set these values in `.env`:

```dotenv
MONITORING_ENABLED=true
PROMETHEUS_ENABLED=true
NODE_EXPORTER_ENABLED=true
CADVISOR_ENABLED=true
CADDY_METRICS_ENABLED=true
LARAVEL_METRICS_ENABLED=true
```

Then run:

```bash
docker exec laravel_app1 php artisan config:clear
docker exec laravel_app1 php artisan queue:restart
docker compose --profile monitoring up -d prometheus node-exporter cadvisor pulse
```

The authenticated dashboard is available at `/admin/monitoring` to central
administrators.

The dashboard includes current and one-hour history for CPU, RAM, CPU/package
temperature, network traffic, API request/error rates, and queue activity. It
also reports per-core thermal sensors, CPU frequency, fan RPM, disk/inode
pressure, network errors/drops, container uptime, failed/pending jobs, central
database status, Redis configuration status, and a Laravel scheduler heartbeat.
Queue totals include the central database and each tenant database. An
unreachable database produces an incomplete-totals warning while counts from
the remaining databases stay visible. Idle queue rates show zero when the
Laravel metrics scrape is healthy.
Hardware readings show as unavailable on hosts that do not expose the matching
Linux sensors.

The UI shows key indicators and six overview charts, with detailed sections and
additional historical graphs collapsed by default. Livewire morphs changed
content during refreshes. ApexCharts instances stay mounted in `wire:ignore`
containers and receive data updates in place. Open panels stay open, and the
additional historical charts are updated only while their panel is open.

The dashboard's **Pause / Resume** choice is stored per administrator in the
central database. Pausing survives page reloads and stops automatic browser
refreshes. **Refresh now** still performs one explicit refresh. Prometheus
scraping stays on the private Docker network and does not consume public
internet bandwidth.

The Monitoring Livewire component rechecks administrator access on every
request. Central-domain and administrator middleware also persist across
Livewire updates. Metric history stays out of the component snapshot; live
preference and refresh interval properties are locked against client updates.

## Laravel Pulse

Pulse is installed but recording is disabled by default. To enable its sampled
application telemetry, set:

```dotenv
PULSE_ENABLED=true
PULSE_DB_CONNECTION=central
```

Then refresh Laravel's cached configuration and restart long-running workers:

```bash
docker exec laravel_app1 php artisan optimize:clear
docker exec laravel_app1 php artisan optimize
docker exec laravel_app1 php artisan queue:restart
docker exec laravel_app1 php artisan pulse:restart
docker compose --profile monitoring up -d --build pulse
```

Pulse is available at `/pulse` to authenticated central administrators only.
Set `PULSE_ENABLED=false` and repeat the cache/worker commands to stop recording.
Existing Pulse samples expire according to `PULSE_STORAGE_KEEP` (seven days by
default).

The custom `tenants:queue-work` worker flushes Pulse telemetry after each job.
This is required because its nested `queue:work --once` calls do not emit the
daemon lifecycle events Pulse normally uses to save buffered queue records.
The `pulse` Compose service runs `pulse:check` for the server card, as required
by the [Laravel Pulse documentation](https://laravel.com/framework/docs/12.x/pulse#servers).
Restart it with `pulse:restart` after changing cached configuration or code.

## Disable

Set `MONITORING_ENABLED=false` (and optionally each component flag to `false`),
then run:

```bash
docker exec laravel_app1 php artisan config:clear
docker exec laravel_app1 php artisan queue:restart
docker compose --profile monitoring stop prometheus node-exporter cadvisor pulse
```

With monitoring disabled, Laravel does not query Prometheus and the internal
Laravel metrics endpoint returns 404. Normal application traffic is independent
of the monitoring containers.
