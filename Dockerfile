# =====================
# 1️⃣ Base image
# =====================
FROM php:8.2-fpm

# =====================
# 2️⃣ System dependencies
# =====================
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

# =====================
# 3️⃣ Set working directory
# =====================
WORKDIR /var/www/html

# =====================
# 4️⃣ Install Composer
# =====================
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

# =====================
# 5️⃣ Copy composer files & install PHP dependencies
# =====================
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# =====================
# 6️⃣ Copy the rest of the application
# =====================
COPY . .

# =====================
# 7️⃣ Set permissions
# =====================
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# =====================
# 8️⃣ Environment variables
# =====================
ENV APP_ENV=production
ENV APP_DEBUG=false
# غيّر APP_URL لرابط التطبيق على Render
ENV APP_URL=https://your-app-name.onrender.com

# =====================
# 9️⃣ Generate app key if missing
# =====================
RUN php artisan key:generate

# =====================
# 🔥 Expose port and start PHP-FPM
# =====================
EXPOSE 10000
CMD ["php-fpm"]
