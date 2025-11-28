# =====================
# Base image
# =====================
FROM php:8.2-fpm

# =====================
# System dependencies + Nginx
# =====================
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    nginx \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# =====================
# Set working directory
# =====================
WORKDIR /var/www/html

# =====================
# Install Composer
# =====================
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

# =====================
# Copy composer files & install dependencies
# =====================
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs --no-scripts

# =====================
# Copy application
# =====================
COPY . .

# =====================
# Run post-install scripts
# =====================
RUN composer run-script post-autoload-dump

# =====================
# Copy or create .env
# =====================
RUN if [ -f .env.example ]; then cp .env.example .env; else \
    echo "APP_NAME=Laravel\nAPP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\nAPP_URL=https://your-app-name.onrender.com\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=laravel\nDB_USERNAME=root\nDB_PASSWORD=" > .env; fi

# =====================
# Generate APP_KEY
# =====================
RUN php artisan key:generate --force

# =====================
# Set permissions
# =====================
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# =====================
# Copy Nginx config
# =====================
COPY nginx.conf /etc/nginx/sites-available/default

# =====================
# Expose HTTP port and start services
# =====================
EXPOSE 8080
CMD service nginx start && php-fpm
