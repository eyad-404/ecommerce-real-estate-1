# =========================
# Stage 1: PHP + Composer
# =========================
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy composer files and artisan first
COPY composer.json composer.lock artisan ./

# Install PHP dependencies
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');" \
    && composer install --no-dev --optimize-autoloader

# Copy rest of the application
COPY . .

# Set permissions for storage & bootstrap/cache
RUN mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Generate app key (if not set in .env)
RUN if [ ! -f .env ]; then cp .env.example .env; fi
RUN php artisan key:generate

# Expose port 10000 (Render uses its own proxy)
EXPOSE 10000

# Start PHP-FPM
CMD ["php-fpm"]
