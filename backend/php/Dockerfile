FROM php:8.0-fpm

# Install dependencies and MySQL client
RUN apt-get update && apt-get install -y \
    procps \
    git \
    unzip \
    curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    default-mysql-client \
    cron \
    supervisor \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy existing application
COPY . /var/www/html

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Append cron job directly to /etc/crontab
RUN echo "* * * * * root php /var/www/html/artisan schedule:run >> /dev/null 2>&1" >> /etc/crontab

# Create the log file to be able to run tail
RUN touch /var/log/cron.log

# Create worker.log file and set permission
RUN mkdir -p /var/www/html/storage/logs \
    && touch /var/www/html/storage/logs/worker.log \
    && chown -R www-data:www-data /var/www/html/storage/logs \
    && chmod 664 /var/www/html/storage/logs/worker.log

# Configure Supervisor
COPY supervisor/queue-worker.conf /etc/supervisor/conf.d/queue-worker.conf

# Copy start.sh
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Use start.sh as the CMD
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]