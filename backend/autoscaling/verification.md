# Verification: 12 October 2026

- Eleven unit tests passed for sustained CPU thresholds, burst rejection,
  cooldown, missing samples, invalid Nginx configuration rollback, request
  draining, primary failure, project discovery, scale bounds, Compose host paths
  and changes to replica addresses.
- Docker Compose configuration, shell syntax and `git diff --check` passed.
- The built controller was exercised against replicas of the single `app` service with
  temporary 2-second sampling, 6-second scale-up and 10-second scale-down timers.
- Observed replica count: 1 → 2 → 1. A bounded 25-second CPU workload triggered
  Compose to create replica 2. Nginx added it only after its health check passed,
  and drained old workers before stopping and removing it. Replica 1 retained
  the same container ID throughout the cycle.
- Both replicas had Docker limits of 2 cores and 8 GiB RAM, and their application
  bind mounts resolved to the actual backend checkout on the host.
- The concurrent API probe received 41 HTTP 200 responses and no failures.
  `X-Backend-Server` confirmed requests reached both Laravel instances during the
  two-instance portion of the test.
- The separate fixed-name application services were replaced by the single
  `app` service. Legacy containers were removed after traffic switched and
  old workers drained. Shared bind mounts and application data were preserved.
- The temporary verification controller was removed, and the regular controller
  is healthy with 15-second sampling, 60-second scale-up, 300-second scale-down
  and 300-second down cooldown. The HTTPS API check also returned HTTP 200.

This validates lifecycle and routing behavior; it is not a throughput benchmark.
