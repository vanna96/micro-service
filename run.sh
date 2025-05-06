#!/bin/bash

set -e  # Exit immediately if a command exits with a non-zero status

# Function to copy a file if it exists
copy_file() {
    local src="$1"
    local dest="$2"
    if [ -f "$src" ]; then
        cp -f "$src" "$dest"
        echo "✅ Copied $(basename "$src") to $dest"
    else
        echo "⚠️  $src not found"
    fi
}

# Copy configuration files
copy_file "config/config.js" "frontend-ui5/webapp/"
copy_file "config/.env" "fastapi/.env"

# Start Docker Compose services
for service_dir in fastapi frontend-ui5; do
    if [ -d "$service_dir" ]; then
        echo "🚀 Starting Docker Compose in $service_dir"
        (cd "$service_dir" && docker-compose up -d --force-recreate)
    else
        echo "❌ Directory $service_dir does not exist"
    fi
done
