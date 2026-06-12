FROM php:8.3-apache

# Install required PHP extensions and tools
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd curl \
    && a2enmod rewrite headers

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Copy application files
COPY public/ /var/www/html/
COPY src/    /var/www/src/
COPY views/  /var/www/views/
COPY .env    /var/www/html/.env

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage 2>/dev/null || true

# Use the default Apache port 80, but we'll map to 8080 on host
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
