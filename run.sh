#!/bin/bash

set -e  # Exit immediately if a command exits with a non-zero status

NETWORK_NAME="app-network"
SERVICE_DIRS="database backend fastapi file-storage frontend-ui5"

# Stop and remove containers from each Compose project, including containers
# that are not attached to app-network.
for service_dir in $SERVICE_DIRS; do
    if [ -d "$service_dir" ]; then
        echo "🧹 Cleaning Docker Compose in $service_dir"
        (cd "$service_dir" && docker-compose down --remove-orphans || true)
    fi
done

# Stop all containers using the network
CONTAINERS=$(docker network inspect $NETWORK_NAME -f '{{range $k,$v := .Containers}}{{$k}} {{end}}' 2>/dev/null || true)

if [ -n "$CONTAINERS" ]; then
    echo "⚠️ Stopping containers using $NETWORK_NAME..."
    docker stop $CONTAINERS
fi

# Delete the network if exists
if docker network ls --format '{{.Name}}' | grep -q "^$NETWORK_NAME$"; then
    echo "⚠️ Deleting network $NETWORK_NAME..."
    docker network rm $NETWORK_NAME
fi

# Recreate network
echo "🌐 Creating network $NETWORK_NAME..."
docker network create $NETWORK_NAME

# Start Docker Compose for each service
for service_dir in $SERVICE_DIRS; do
    if [ -d "$service_dir" ]; then
        echo "🚀 Starting Docker Compose in $service_dir"
        (cd "$service_dir" && docker-compose up -d --force-recreate)
    else
        echo "❌ Directory $service_dir does not exist"
    fi
done

# Laravel writes logs, cache, sessions, and compiled views at runtime.
# Keep these writable after containers are recreated.
echo "🔐 Fixing Laravel writable directory permissions..."
for container in laravel_app1 laravel_app2 laravel_queue; do
    if docker ps --format '{{.Names}}' | grep -q "^$container$"; then
        docker exec "$container" chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
        docker exec "$container" chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache
    fi
done
