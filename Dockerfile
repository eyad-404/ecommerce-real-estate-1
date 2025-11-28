FROM dunglas/frankenphp:php8.2

WORKDIR /app

# Install system dependencies
RUN apt-get update && \
    apt-get install -y git unzip && \
    docker-php-ext-install pdo_mysql

# Copy project
COPY . /app

# Install Composer dependencies
RUN composer install --optimize-autoloader --no-dev

# Laravel permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && chmod -R a+rw storage bootstrap/cache

# Caddy config
COPY ./Caddyfile /etc/caddy/Caddyfile

EXPOSE 8080
CMD ["frankenphp", "run", "--config=/etc/caddy/Caddyfile"]
