# ===============================
# Dockerfile for Laravel on Render
# ===============================

# Base image
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev libpng-dev libjpeg-dev libfreetype6-dev \
    curl zip \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy composer.json and composer.lock first to leverage caching
COPY composer.json composer.lock ./

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');" \
    && composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Copy the rest of the Laravel app
COPY . .

# Set permissions for storage and bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port for Render
EXPOSE 10000

# Set environment variables defaults (can override in Render dashboard)
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV APP_KEY=

# Start PHP-FPM (Render reverse proxy will handle public HTTP)
CMD ["php-fpm"]
