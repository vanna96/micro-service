FROM python:3.11-slim

# Set working directory to /app (not /app/app)
WORKDIR /app

# Install dependencies
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