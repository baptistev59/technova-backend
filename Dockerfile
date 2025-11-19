# ---- Etape 1 : Build ----
FROM php:8.2-fpm AS build

# Install system deps
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libicu-dev libonig-dev \
    && docker-php-ext-install intl pdo pdo_pgsql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy project
WORKDIR /var/www/html
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# ---- Etape 2 : Production ----
FROM php:8.2-fpm

# Install system deps & PHP extensions
RUN apt-get update && apt-get install -y \
    nginx libpq-dev libicu-dev \
    && docker-php-ext-install intl pdo pdo_pgsql

# Copy app from builder
WORKDIR /var/www/html
COPY --from=build /var/www/html /var/www/html

# Copy NGINX conf
COPY docker/nginx.conf /etc/nginx/sites-enabled/default

# Expose port Render expects (10000)
EXPOSE 10000

# Start PHP-FPM + Nginx
CMD service nginx start && php-fpm
