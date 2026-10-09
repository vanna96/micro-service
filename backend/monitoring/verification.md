# Monitoring verification — 2026-10-03

The monitoring review is complete. No failures remain in the checks below.
This records the verified behavior; it does not guarantee the absence of every
possible defect under other configurations or workloads.

## Issues corrected

- Caddy HTTP queries selected `reverse_proxy`, while the running ingress exports
  its public request metrics under the outer `encode` handler. Queries now use
  that handler to count requests once, excluding metrics scrapes. Caddy documents
  why handlers must not be summed indiscriminately in its
  [metrics guide](https://caddyserver.com/docs/metrics).
- A failed individual Prometheus query previously prevented later dashboard
  sections from loading. Queries now fail independently. A connection failure
  stops further requests during that refresh rather than repeating timeouts.
- Disabled or unavailable components could display stale Prometheus readings.
  Their current values and chart queries now respect component availability.
- The live metrics file had reached its series limit. New counters and gauges
  now reclaim space from optional histogram buckets, keeping dashboard metrics
  within the configured limit.
- Pulse status previously checked only whether the package existed. It now
  verifies its database storage tables.
- Pulse server samples were absent because `pulse:check` was not running. The
  optional Compose stack now includes a dedicated collector, following the
  [Pulse server recorder requirements](https://laravel.com/framework/docs/12.x/pulse#servers).
- Nginx sent Pulse and Livewire requests to Next.js. It now routes `/pulse` and
  both standard and hashed Livewire paths to Laravel.
- Outgoing connection failures use a new Laravel request wrapper. Timing now
  follows the underlying PSR request, and weak references prevent abandoned
  timing records from accumulating in long-running workers.
- HTTP exceptions retain their actual status instead of being counted as 500.
- Scheduler health uses the local heartbeat if Prometheus is unavailable.
- An in-flight browser refresh now preserves the Paused indicator.
- The monitoring status panel used an ID reserved by the theme's preloader CSS.
  Its unique ID now keeps it in the normal page layout.
- The Monitoring screen now uses a Livewire component to morph changed content.
  Chart instances stay mounted and receive series updates rather than being
  destroyed and recreated. Detail panels retain their open state. Pause,
  resume and manual refresh retain the existing per-administrator preferences.

The earlier queue fixes remain verified: central and tenant totals, incomplete
database reporting, healthy idle rates, and immediate Pulse ingestion per job.

## Validation

| Area | Result |
| --- | --- |
| PHP monitoring tests | 37 passed, 222 assertions |
| Browser controls | Pause/resume, persisted pause, manual/automatic refresh, in-flight pause, server pause, failed refresh/save recovery passed |
| Charts and layout | Six overview charts and four optional historical graphs render with actual Livewire/ApexCharts; changed text and chart series update without recreating chart instances; capacity percentages, unavailable-data messages and recovery verified; open panels survive refresh; no duplicate charts or browser exceptions; overview cards fit desktop/mobile and status panel stays in document flow |
| Access | Guest requests and tenant sessions cannot access the dashboard or change preferences, including Livewire updates after a session changes; tenant-domain Livewire updates are rejected; preference properties are locked; tenant sessions cannot access Pulse |
| Preferences | Invalid input rejected; administrator preferences isolated; refresh interval clamped to 5–300 seconds |
| Metrics | Success/failure queue events, outgoing requests, slow queries, scheduler events, bounded history, cumulative buckets, HTTP exception status, disabled recording, corrupt/unwritable storage checked |
| Prometheus | All five scrape targets up; configuration passes `promtool check config` |
| Live dashboard | CPU, RAM, temperature sensors, CPU frequency, fan, disk/inodes, network, containers, API, Caddy HTTP, queues, scheduler and chart queries return successfully |
| Pulse | Collector running and writing fresh system samples; public unauthenticated Pulse returns 403; current Livewire JavaScript returns 200 |
| Metrics ingress | Private scrape returns 200; public metrics returns 404, including after the trailing-slash redirect |
| Infrastructure | Compose validation, Nginx configuration validation, PHP formatting and diff checks pass |

Redis is not configured on this installation; its live availability could not
be exercised. Tests use isolated SQLite databases and mocked failures; browser
checks use rendered Laravel/Livewire response fixtures and actual local Livewire
and ApexCharts assets. The
production scrape, ingress, collector, database and scheduler checks are live.
Hardware readings depend on the sensors available on the host.

## Live recheck — 2026-10-03, 18:53 Asia/Phnom_Penh

- All 11 Compose services were running; services with health checks were healthy.
  The tenant queue worker, cron process and Pulse collector were present.
- Read-only checks of the central database and all 27 tenant databases found no
  unavailable databases, pending/reserved/delayed jobs, or failed jobs.
- Two temporary local canary jobs, one central and one tenant, completed through
  the existing worker. They only wrote completion markers, which were removed.
  The processed-job counter increased from 38 to 40; the dashboard's processed
  rate then became nonzero. No business jobs were retried, deleted or modified.
- Scheduler heartbeat was 32 seconds old. The hourly `test:queue-cron` task last
  ran at 18:00; its next run was scheduled for 19:00 local time.
- All five Prometheus targets were up with no scrape errors. All six Monitoring
  components reported connected. Pulse's latest server sample was 4 seconds old.
- Worker logs contained no tenant-worker errors. The latest recorded exception
  was an unsupported diagnostic CLI option, rather than a queue failure.
- PHP checks passed again: 37 tests, 222 assertions. Browser checks passed with
  actual Livewire and ApexCharts assets, including chart persistence, pause,
  refresh, failed-request handling, empty-data recovery, and mobile layout.
- Guest Monitoring access returned 302; the public Livewire asset returned 200.
  Redis remains unconfigured; this installation uses database queues and file
  cache, so Redis is not required for the verified queue operation.

## Job-handler review and live recheck — 2026-10-03, 19:14 Asia/Phnom_Penh

An empty queue did not establish that the handlers report failures correctly.
The deeper review found and corrected these issues:

- Telegram message jobs ignored failed delivery results; order notification jobs
  caught failures without rethrowing them. Failed deliveries now trigger up to
  three attempts with backoff, then populate failed-job storage and monitoring
  failure counters. Disabled order notifications and deleted sales still skip
  normally. Unsupported notification types now fail explicitly.
- Job handlers and tenant queue scans could leave the worker in another tenant's
  context after exceptions. A shared context helper restores the previous tenant,
  database, storage and queue context in `finally`. Missing tenants fail instead
  of using another store's data or Telegram configuration. Stock deductions use
  the sale model's connection for their transaction.
- Queue scans treated freshly reserved jobs as available. With `--once`, this
  could prevent a ready central job from running. Scans now match the configured
  queue and Laravel's ready-or-expired-reservation conditions.
- Two existing Telegram test fixtures were broken: one referenced absent Order
  models; another queried the real central database and asserted no behavior.
  The formatter now uses a local fixture and the job test uses isolated SQLite.

Validation and current runtime:

- Combined monitoring, Telegram and stock-deduction checks passed: **72 tests,
  351 assertions**. This includes three failed delivery attempts, failed-job/Pulse
  recording, context restoration after exceptions, missing tenants, stock
  deduction idempotency, and reserved-job handling. The Telegram tests were
  rerun after replacing a deprecated mock API: 17 tests, 58 assertions passed
  without deprecations. Telegram HTTP calls were faked; no real messages were sent.
- Browser checks passed again with actual Livewire and ApexCharts assets,
  including refresh, pause, chart persistence, failure recovery and mobile layout.
- The queue worker restarted gracefully and a new process was confirmed. Both
  temporary central and tenant canary jobs completed through it; the processed
  counter increased from 41 to 43. Completion markers were removed.
- All 11 Compose services were running. The central database and all 27 tenant
  databases were reachable, with **0 pending/reserved/delayed jobs and 0 failed
  jobs**. All six monitoring components were connected and five Prometheus
  targets were up without scrape errors.
- The scheduler was healthy. Its most recent recorded heartbeat was 10 seconds
  old; `test:queue-cron` ran at 19:00 and is next scheduled for 20:00 local time.
  Pulse's most recent server sample was 13 seconds old.
- Guest Monitoring access returned 302 and the Livewire JavaScript returned 200.
  The latest recorded exception remains the earlier unsupported diagnostic CLI
  option. The application log also contains expected Telegram delivery failures
  from the isolated retry regression test; their stacks identify
  `QueueMonitoringTest`. The test now uses the null log channel to keep future
  simulated failures out of runtime logs. Worker logs contain no tenant-worker
  errors. Database queues are in use; Redis remains unconfigured.

## Repeat the PHP checks

Use separate cache paths so the tests do not load production's cached config or
routes:

```bash
docker exec \
  -e APP_CONFIG_CACHE=/tmp/monitoring-test-config.php \
  -e APP_ROUTES_CACHE=/tmp/monitoring-test-routes.php \
  laravel_app1 php vendor/bin/phpunit \
    tests/Feature/Monitoring tests/Unit/Monitoring \
    tests/Feature/PosStockDeductionTest.php tests/Unit/TelegramNotificationServiceTest.php
```

For browser checks, export fixtures by adding
`-e MONITORING_BROWSER_FIXTURES=/tmp/monitoring-fixtures` to that invocation,
copy `index.html`, `refresh.json`, and `empty-refresh.json` out of the container, and run
`tests/browser/monitoring.cjs <fixture-directory>` with Playwright available.
