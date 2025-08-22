#!/bin/bash

set -e  # Exit immediately if a command exits with a non-zero status

NETWORK_NAME="app-network"

# Stop all containers using the network
CONTAINERS=$(docker network inspect $NETWORK_NAME -f '{{range $k,$v := .Containers}}{{$k}} {{end}}')

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
for service_dir in database backend fastapi file-storage frontend-ui5; do
    if [ -d "$service_dir" ]; then
        echo "🚀 Starting Docker Compose in $service_dir"
        (cd "$service_dir" && docker-compose up -d --force-recreate)
    else
        echo "❌ Directory $service_dir does not exist"
    fi
done