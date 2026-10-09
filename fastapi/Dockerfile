FROM python:3.11-slim

# Set working directory to /app (not /app/app)
WORKDIR /app

# Install dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends libgl1 libglib2.0-0t64 libxcb1 \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt

# Copy the FastAPI app into the container (no nested /app folder)
# Only copy contents of app into /app
COPY ./app ./  

# Copy the entrypoint script into the container (place it inside /app)
# COPY entrypoint.sh /entrypoint.sh
# RUN chmod +x /entrypoint.sh

# Expose the port
EXPOSE 8000

# Set the default entrypoint (this will run migrations and start FastAPI)
# ENTRYPOINT ["/entrypoint.sh"]
