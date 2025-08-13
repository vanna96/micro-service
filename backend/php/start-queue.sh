#!/bin/bash
set -e

echo "📦 Checking Laravel project readiness..."

# Wait for vendor folder
while [ ! -d "/var/www/html/vendor" ]; do
    echo "⏳ Waiting for vendor/ folder..."
    sleep 3
done

# Wait for .env file
while [ ! -f "/var/www/html/.env" ]; do
    echo "⏳ Waiting for .env file..."
    sleep 3
done

# Extract DB connection info from .env
DB_HOST=$(grep DB_HOST /var/www/html/.env | cut -d '=' -f2)
DB_PORT=$(grep DB_PORT /var/www/html/.env | cut -d '=' -f2)
DB_DATABASE=$(grep DB_DATABASE /var/www/html/.env | cut -d '=' -f2)
DB_USERNAME=$(grep DB_USERNAME /var/www/html/.env | cut -d '=' -f2)
DB_PASSWORD=$(grep DB_PASSWORD /var/www/html/.env | cut -d '=' -f2)

# Wait for DB to be ready
echo "⏳ Waiting for database connection to $DB_HOST:$DB_PORT..."
until php -r "try { new PDO('mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_DATABASE', '$DB_USERNAME', '$DB_PASSWORD'); echo '✅ Database is ready'; exit(0); } catch (Exception \$e) { exit(1); }"; do
    sleep 3
done

echo "🚀 Starting Supervisor..."
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
