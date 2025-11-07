FROM php:8.3-cli AS base

# System packages and PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip curl libpng-dev libonig-dev libxml2-dev \
    libzip-dev libpq-dev libcurl4-openssl-dev libssl-dev \
    zlib1g-dev libicu-dev g++ libevent-dev procps \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring zip exif pcntl bcmath sockets intl

# Swoole is installed from GitHub
RUN curl -L -o swoole.tar.gz https://github.com/swoole/swoole-src/archive/refs/tags/v5.1.0.tar.gz \
    && tar -xf swoole.tar.gz \
    && cd swoole-src-5.1.0 \
    && phpize \
    && ./configure \
    && make -j$(nproc) \
    && make install \
    && docker-php-ext-enable swoole

# Composer installation
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy composer files and artisan file
COPY composer.json composer.lock artisan ./

# Create Laravel's basic directory structure (added storage/app/public for storage:link at runtime)
RUN mkdir -p bootstrap/cache storage/app storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs

# Install Composer dependencies (without post-scripts)
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

# Copy the rest of the project files
COPY . .

# Run Composer post-scripts (skip during build to avoid env-dependent initialization)
RUN composer dump-autoload --optimize --no-scripts

# File permissions
RUN chown -R www-data:www-data /var/www \
 && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

CMD ["sh", "-c", "echo 'APP_KEY:' $APP_KEY && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan storage:link && php artisan octane:start --server=swoole --host=0.0.0.0 --port=9000"]