#!/bin/bash

set -e  # Exit immediately if a command exits with a non-zero status

# Configuration variables directly in the script
BAKEND_PORT=8080
PHPMYADMIN_PORT=9092
BACKEND_URL="http://localhost:$BAKEND_PORT"

MYSQL_ROOT_PASSWORD=admin
MYSQL_DATABASE=fastapi
MYSQL_USER=admin
MYSQL_PASSWORD=123456
DB_HOST=mariadb
DATABASE_URL="mysql+mysqlconnector://$MYSQL_USER:$MYSQL_PASSWORD@$DB_HOST:3306/$MYSQL_DATABASE"

FRONTEND_PORT=9090
FRONTEND_URL="https://localhost:$FRONTEND_PORT"

# Function to copy a file if it exists (we won't need this for .env anymore)
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

# Generate the .env file for frontend-ui5
FRONTEND_ENV_FILE="frontend-ui5/.env"
mkdir -p "$(dirname "$FRONTEND_ENV_FILE")"

cat <<EOF > "$FRONTEND_ENV_FILE"
FRONTEND_PORT=$FRONTEND_PORT
EOF

echo "✅ Generated .env file for frontend-ui5 at $FRONTEND_ENV_FILE"

# Generate the .env file for FastAPI
FASTAPI_ENV_FILE="fastapi/.env"
mkdir -p "$(dirname "$FASTAPI_ENV_FILE")"

cat <<EOF > "$FASTAPI_ENV_FILE"
BAKEND_PORT=$BAKEND_PORT
PHPMYADMIN_PORT=$PHPMYADMIN_PORT
MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD
MYSQL_DATABASE=$MYSQL_DATABASE
MYSQL_USER=$MYSQL_USER
MYSQL_PASSWORD=$MYSQL_PASSWORD
DB_HOST=$DB_HOST
DATABASE_URL=$DATABASE_URL
EOF

echo "✅ Generated .env file for FastAPI at $FASTAPI_ENV_FILE"

# Write config.js with actual values for frontend-ui5
CONFIG_JS="frontend-ui5/webapp/config.js"
mkdir -p "$(dirname "$CONFIG_JS")"

cat <<EOF > "$CONFIG_JS"
window.BASE_URL = "$FRONTEND_URL";
window.BACKEND_URL = "$BACKEND_URL";
EOF

echo "✅ Generated $CONFIG_JS with evaluated URLs"

# Start Docker Compose for each service
for service_dir in fastapi frontend-ui5; do
    if [ -d "$service_dir" ]; then
        echo "🚀 Starting Docker Compose in $service_dir"
        (cd "$service_dir" && docker-compose up -d --force-recreate)
    else
        echo "❌ Directory $service_dir does not exist"
    fi
done