#!/usr/bin/env bash

set -Eeuo pipefail

NETWORK_NAME="app-network"
SERVICE_DIRS=(database file-storage fastapi backend frontend-ui5)
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

cd "$SCRIPT_DIR"

if ! command -v docker >/dev/null 2>&1; then
    echo "Error: Docker is not installed or is not available in PATH." >&2
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "Error: Docker is not running. Start Docker Desktop or the Docker service and try again." >&2
    exit 1
fi

if docker compose version >/dev/null 2>&1; then
    COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE=(docker-compose)
else
    echo "Error: Docker Compose is not installed." >&2
    exit 1
fi

backend_exec() {
    (cd "$SCRIPT_DIR/backend" && "${COMPOSE[@]}" exec -T --index 1 app "$@")
}

if ! command -v python3 >/dev/null 2>&1; then
    echo "Error: Python 3 is required to create local .env files." >&2
    exit 1
fi

python3 "$SCRIPT_DIR/scripts/bootstrap_env.py"

# Git Bash otherwise rewrites Linux container paths such as /var/www/html.
case "${OSTYPE:-}" in
    msys*|cygwin*) export MSYS_NO_PATHCONV=1 ;;
esac

for service_dir in "${SERVICE_DIRS[@]}"; do
    if [[ -d "$service_dir" ]]; then
        echo "Cleaning Docker Compose in $service_dir"
        (cd "$service_dir" && "${COMPOSE[@]}" down) || true
    else
        echo "Warning: skipping missing directory $service_dir" >&2
    fi
done

if docker network inspect "$NETWORK_NAME" >/dev/null 2>&1; then
    # This external network is shared with independent stacks such as the
    # gateway and Telegram bot. Reusing it avoids interrupting those services.
    echo "Reusing network $NETWORK_NAME"
else
    echo "Creating network $NETWORK_NAME"
    docker network create "$NETWORK_NAME" >/dev/null
fi

for service_dir in "${SERVICE_DIRS[@]}"; do
    if [[ -d "$service_dir" ]]; then
        echo "Starting Docker Compose in $service_dir"
        (cd "$service_dir" && "${COMPOSE[@]}" up -d --build --force-recreate)

        if [[ "$service_dir" == "database" ]]; then
            echo "Ensuring MySQL tenant database permissions"
            docker exec mysql_db sh \
                /docker-entrypoint-initdb.d/01-grant-tenant-database-access.sh
        elif [[ "$service_dir" == "backend" ]]; then
            echo "Preparing Laravel"
            # PHP-FPM must read .env, but the application checkout is mounted
            # read-only. Use a short-lived container with a writable file mount
            # to set the host file's group and mode safely.
            backend_image="$(cd "$SCRIPT_DIR/backend" && "${COMPOSE[@]}" images -q app)"
            docker run --rm --entrypoint sh \
                --volume "$SCRIPT_DIR/backend/.env:/app.env" \
                "$backend_image" -c 'chgrp www-data /app.env && chmod 640 /app.env'
            # Composer writes vendor metadata, so run it against a temporary
            # writable mount instead of weakening the live container mount.
            docker run --rm \
                --env COMPOSER_HOME=/tmp/composer \
                --env COMPOSER_ALLOW_SUPERUSER=1 \
                --env HOST_UID="$(id -u)" \
                --env HOST_GID="$(id -g)" \
                --volume "$SCRIPT_DIR/backend:/var/www/html" \
                --workdir /var/www/html \
                --entrypoint sh \
                "$backend_image" -c \
                    'composer install --no-interaction --prefer-dist --no-progress && chown -R "$HOST_UID:$HOST_GID" vendor'
            backend_exec php artisan migrate --force --no-interaction
            backend_exec php artisan optimize:clear
        fi
    fi
done

backend_exec chown -R www-data:www-data \
    /var/www/html/storage /var/www/html/bootstrap/cache
backend_exec chmod -R ug+rwX \
    /var/www/html/storage /var/www/html/bootstrap/cache

for container_name in laravel_queue; do
    if docker container inspect -f '{{.State.Running}}' "$container_name" 2>/dev/null | grep -q '^true$'; then
        docker exec "$container_name" chown -R www-data:www-data \
            /var/www/html/storage /var/www/html/bootstrap/cache
        docker exec "$container_name" chmod -R ug+rwX \
            /var/www/html/storage /var/www/html/bootstrap/cache
    fi
done

for _ in {1..30}; do
    init_running="$(docker container inspect -f '{{.State.Running}}' minio-init 2>/dev/null || true)"
    [[ "$init_running" != "true" ]] && break
    sleep 1
done

init_exit_code="$(docker container inspect -f '{{.State.ExitCode}}' minio-init 2>/dev/null || true)"
if [[ -n "$init_exit_code" && "$init_exit_code" != "0" ]]; then
    echo "Error: MinIO bucket initialization failed with exit code $init_exit_code." >&2
    docker logs minio-init >&2 || true
    exit 1
fi

required_containers=(
    mysql_db phpmyadmin laravel_queue laravel_redis laravel_nginx laravel_autoscaler
    laravel_soketi
    laravel_nextjs fastapi-app minio frontend-ui5
)
for container_name in "${required_containers[@]}"; do
    if ! docker container inspect -f '{{.State.Running}}' "$container_name" 2>/dev/null | grep -q '^true$'; then
        echo "Error: required container $container_name is not running." >&2
        docker logs --tail 50 "$container_name" >&2 || true
        exit 1
    fi
done

# Verify actual HTTP responses, not just running processes.
laravel_ready=false
for _ in {1..30}; do
    if docker exec laravel_nextjs node -e \
        'fetch("http://nginx/v1/api/security/client-config").then(r => process.exit(r.ok ? 0 : 1)).catch(() => process.exit(1))' \
        >/dev/null 2>&1; then
        laravel_ready=true
        break
    fi
    sleep 2
done
if [[ "$laravel_ready" != true ]]; then
    echo "Error: Laravel security API did not become ready." >&2
    docker logs --tail 50 laravel_nginx >&2 || true
    exit 1
fi

fastapi_ready=false
for _ in {1..30}; do
    if docker exec fastapi-app python -c \
        'import urllib.request; urllib.request.urlopen("http://127.0.0.1:8000/docs", timeout=3)' \
        >/dev/null 2>&1; then
        fastapi_ready=true
        break
    fi
    sleep 2
done
if [[ "$fastapi_ready" != true ]]; then
    echo "Error: FastAPI did not become ready." >&2
    docker logs --tail 50 fastapi-app >&2 || true
    exit 1
fi

ui5_ready=false
for _ in {1..60}; do
    if docker exec frontend-ui5 node -e \
        'fetch("http://127.0.0.1:9090/").then(r => process.exit(r.ok ? 0 : 1)).catch(() => process.exit(1))' \
        >/dev/null 2>&1; then
        ui5_ready=true
        break
    fi
    sleep 2
done
if [[ "$ui5_ready" != true ]]; then
    echo "Error: UI5 did not become ready." >&2
    docker logs --tail 50 frontend-ui5 >&2 || true
    exit 1
fi

echo "All services started successfully."
