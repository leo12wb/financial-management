FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpq-dev \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    bcmath \
    opcache

# Install Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock symfony.lock ./

# Install PHP dependencies (no dev in prod; --no-scripts avoids running symfony cache:clear before .env exists)
RUN composer install --no-interaction --no-scripts --optimize-autoloader

# Copy application source
COPY . .

# Generate JWT keypair directory
RUN mkdir -p config/jwt

# Warm up prod cache so the first request is fast
RUN APP_ENV=prod php bin/console cache:warmup --no-debug || true

# Set permissions
RUN chown -R www-data:www-data /var/www/html/var \
    && chmod -R 775 /var/www/html/var

EXPOSE 9000

CMD ["php-fpm"]
