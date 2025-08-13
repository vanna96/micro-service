#!/bin/bash
set -e

# 1️⃣ Wait for vendor folder
echo "Waiting for composer dependencies..."
while [ ! -d "/var/www/html/vendor" ]; do
  echo "vendor folder not found, waiting 5 seconds..."
  sleep 5
done
echo "vendor folder found."

# 2️⃣ Check if .env exists
if [ ! -f "/var/www/html/.env" ]; then
    echo ".env file not found, exiting."
    exit 1
fi
echo ".env file found."

# 3️⃣ Wait for database connection
echo "Waiting for database connection..."
max_attempts=20
attempt=1
until php -r "new PDO('mysql:host=${DB_HOST};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" >/dev/null 2>&1; do
    if [ $attempt -ge $max_attempts ]; then
        echo "Database connection failed after $attempt attempts, exiting."
        exit 1
    fi
    echo "Database not ready yet, waiting 5 seconds... (attempt $attempt)"
    attempt=$((attempt + 1))
    sleep 5
done

echo "Database connected."

# Start cron in foreground
echo "Starting cron..."
cron -f &

# Start Laravel queue worker in foreground (optional: multiple workers via Supervisor)
echo "Starting Laravel queue worker..."
php /var/www/html/artisan queue:work --sleep=3 --tries=3
