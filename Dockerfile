# 1. استخدم صورة PHP مع FPM
FROM php:8.2-fpm

# 2. تثبيت dependencies الأساسية
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    curl \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl gd

# 3. تثبيت Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 4. إنشاء مجلد العمل
WORKDIR /var/www/html

# 5. نسخ ملفات المشروع
COPY . .

# 6. تثبيت الـ PHP dependencies للمشروع
RUN composer install --no-dev --optimize-autoloader

# 7. تعديل صلاحيات المجلدات المهمة
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 8. Cache config & routes & views
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# 9. expose البورت الافتراضي (PHP-FPM يستخدم 9000)
EXPOSE 9000

# 10. تشغيل PHP-FPM
CMD ["php-fpm"]
