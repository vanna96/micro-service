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
    attached_containers=()
    while IFS= read -r container_name; do
        [[ -n "$container_name" ]] && attached_containers+=("$container_name")
    done < <(
        docker network inspect "$NETWORK_NAME" \
            --format '{{range .Containers}}{{.Name}}{{"\n"}}{{end}}'
    )

    if (( ${#attached_containers[@]} > 0 )); then
        echo "Stopping containers still attached to $NETWORK_NAME"
        docker stop "${attached_containers[@]}"
    fi

    docker network rm "$NETWORK_NAME"
fi

echo "Creating network $NETWORK_NAME"
docker network create "$NETWORK_NAME" >/dev/null

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
            # PHP-FPM runs as www-data and must be able to read the bind-mounted .env.
            docker exec laravel_app1 chgrp www-data /var/www/html/.env
            docker exec laravel_app1 chmod 640 /var/www/html/.env
            docker exec laravel_app1 composer install \
                --no-interaction --prefer-dist --no-progress
            docker exec laravel_app1 php artisan migrate --force --no-interaction
            docker exec laravel_app1 php artisan optimize:clear
        fi
    fi
done

for container_name in laravel_app1 laravel_app2 laravel_queue; do
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
    mysql_db phpmyadmin laravel_app1 laravel_app2 laravel_queue laravel_nginx
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
