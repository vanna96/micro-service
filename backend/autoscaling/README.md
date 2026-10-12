# Laravel automatic scaling

The `app` Compose service defines Laravel once, without a fixed container name.
The `autoscaler` service keeps replica 1 running and uses Compose to create
replica 2 when needed. The bounds are exactly one and two PHP-FPM replicas on
this Docker host. With the default `backend` project name, their container names
are `backend-app-1` and `backend-app-2`. The controller discovers replicas by
project/service labels, so a different project name also works.
Queue workers, Next.js, MySQL and the other services retain their current deployment.

Each Laravel app instance has a Docker limit of 2 CPU cores and 8 GiB RAM.
These are maximum usage limits, not reserved resources. Two instances can use
up to 4 cores and 16 GiB in total; actual memory availability depends on the
host and other services. The current host has approximately 15 GiB of RAM.

Default policy:

| Setting in backend/.env | Default | Meaning |
| --- | --- | --- |
| AUTOSCALE_INTERVAL | 15 | Seconds between CPU samples |
| AUTOSCALE_UP_CPU | 70 | Create replica 2 when replica 1 consumes at least 70% of one CPU core |
| AUTOSCALE_UP_SECONDS | 60 | Required continuous high CPU period |
| AUTOSCALE_DOWN_CPU | 25 | Remove replica 2 when combined replica CPU is at most 25% of one core |
| AUTOSCALE_DOWN_SECONDS | 300 | Required continuous low CPU period |
| AUTOSCALE_COOLDOWN | 300 | Minimum seconds since the last change before scaling down |

CPU percentages follow `docker stats`: 100% means one CPU core, rather than all
four cores on the current host. A short burst does not scale up. Missing samples
reset the observation window. High-load recovery is allowed after 60 seconds
even during the scale-down cooldown.
The CPU thresholds stay at 70% and 25% of one core, so the scale-up trigger is
35% of an instance's 2-core limit.

On startup, the controller reconciles the upstream with healthy replicas and
their current Docker network addresses. It only adds replica 2 after its health
check passes. To shrink, it removes replica 2 from new
requests, validates and gracefully reloads Nginx, waits for all old Nginx workers
to exit, then stops PHP-FPM with SIGQUIT and removes only that replica container.
Shared bind mounts and application data remain. If draining exceeds 330 seconds
or replica 1 becomes unhealthy, it keeps replica 2 running. Long-lived WebSocket
connections may therefore defer scale-down.

Nginx initializes `runtime/laravel-upstream.conf` from the one-instance template
if needed. The controller atomically updates that ignored runtime file. Both
Laravel instances continue sharing the same storage, sessions, and cache mounts.
This supports a single host; adding other hosts would require shared state.

The controller has the Docker socket to inspect CPU usage, create/remove replicas
and reload Nginx. It mounts the backend checkout read-only at `/workspace` and
discovers its host-side path so Compose creates replicas with the correct bind
mounts. This grants Docker-host administration privileges. It runs with no
network connection, a read-only root filesystem and only its runtime config and
temporary heartbeat writable. Keep its image and source trusted.

Start or apply threshold changes:

```bash
cd backend
docker compose up -d --build nginx autoscaler
docker logs --tail 30 -f laravel_autoscaler
```

`bash run.sh` also starts the controller. The default deployment starts with
one app replica. Do not change the app replica count manually while the
controller is active: it will reconcile it and eventually shrink at low load.

To hold two replicas manually, stop the controller, then use its scale-up
routine to create replica 2, wait for health, and update Nginx:

```bash
docker compose stop autoscaler
docker compose run --rm --no-deps autoscaler python3 -c 'import autoscale; autoscale.scale_up()'
```

Resume automation with `docker compose up -d autoscaler`.

Run Laravel maintenance commands through the service rather than a container name:

```bash
docker compose exec -T --index 1 app php artisan config:clear
```

Tests: `python3 -m unittest discover -s autoscaling -p 'test_*.py' -v`.
