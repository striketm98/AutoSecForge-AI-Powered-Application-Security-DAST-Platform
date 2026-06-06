FROM php:8.3-apache

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libzip-dev libonig-dev libxml2-dev libicu-dev libpq-dev \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd pdo pdo_mysql mysqli zip mbstring exif pcntl bcmath opcache intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers ssl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy source
COPY . .

# Install PHP deps
RUN if [ -f composer.json ]; then \
        composer install --no-interaction --optimize-autoloader --no-dev; \
    else echo "No composer.json — skipping"; fi

# ASF-004: Lock down uploads directory
RUN mkdir -p uploads/evidence \
    && chown -R www-data:www-data uploads \
    && chmod 750 uploads/evidence

# ASF-006: Install vhost config
COPY apache-vhost.conf /etc/apache2/sites-available/autosecforge.conf
RUN a2dissite 000-default.conf && a2ensite autosecforge.conf

# ASF-005: Harden PHP session settings
RUN echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "session.cookie_secure = 0" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "session.cookie_samesite = Strict" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "session.use_strict_mode = 1" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/security.ini

EXPOSE 80
CMD ["apache2-foreground"]
