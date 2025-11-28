# استخدم PHP 8.2 CLI
FROM php:8.2-cli

# حدد مجلد العمل
WORKDIR /var/www/html

# انسخ كل ملفات المشروع
COPY . .

# ثبّت الأدوات اللازمة
RUN apt-get update && apt-get install -y \
    libzip-dev unzip \
    && docker-php-ext-install pdo_mysql zip

# ثبّت Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ثبّت Dependencies للـ Laravel
RUN composer install --no-dev --optimize-autoloader

# افتح البورت
EXPOSE 10000

# أمر تشغيل المشروع
CMD ["php", "-S", "0.0.0.0:10000", "-t", "public"]
