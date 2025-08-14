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
    
# Install Node.js (LTS) and npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && node -v \
    && npm -v

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy application
COPY . /var/www/html

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Append cron job directly to /etc/crontab
RUN echo "* * * * * root php /var/www/html/artisan schedule:run >> /dev/null 2>&1" >> /etc/crontab

# Create the log file to be able to run tail
RUN touch /var/log/cron.log

# Create storage/logs folder and worker log
RUN mkdir -p /var/www/html/storage/logs \
    && touch /var/www/html/storage/logs/worker.log \
    && touch /var/www/html/storage/logs/laravel.log \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage

# RUN mkdir -p /var/www/html/storage/logs \
#     && touch /var/www/html/storage/logs/worker.log \
#     && chown -R www-data:www-data /var/www/html/storage/logs \
#     && chmod 664 /var/www/html/storage/logs/worker.log

# Copy Supervisor configs
COPY supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY supervisor/queue-worker.conf /etc/supervisor/conf.d/queue-worker.conf

# Copy startup script
COPY start-queue.sh /start-queue.sh
RUN chmod +x /start-queue.sh

EXPOSE 9000

# Run startup script instead of directly starting supervisor
CMD ["/start-queue.sh"]
# CMD ["php-fpm"]
