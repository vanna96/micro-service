#!/bin/bash

# Ensure that the database URL environment variable is set
if [ -z "$DATABASE_URL" ]; then
  echo "DATABASE_URL environment variable is not set!"
  exit 1
fi

# Run database migrations
echo "Running migrations..."
alembic upgrade head

# Start the FastAPI app
echo "Starting FastAPI..."
exec uvicorn main:app --host 0.0.0.0 --port 8000 --reload
