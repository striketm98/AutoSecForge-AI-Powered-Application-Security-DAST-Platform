# ============================================================
# AutoSecForge – Dockerfile  (SECURITY-HARDENED)
#
# SCA-003 FIX: Pinned from php:8.3-apache → php:8.3.31-apache
# ASF-004 FIX: public/uploads/client-logos/ directory removed;
#              uploads are served from /var/www/storage (outside
#              webroot) via a proxy controller.
# ============================================================

FROM php:8.3.31-apache

# Install PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libwebp-dev \
        libxml2-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql gd curl xml \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers

# Copy application source
WORKDIR /var/www/html
COPY public/ ./
COPY src/    ../src/
COPY templates/ ../templates/

# ASF-004 FIX: Do NOT create a public/uploads directory inside the
# webroot.  Logos are stored in /var/www/storage which is mapped via
# the Docker volume and NOT accessible by Apache directly.
RUN mkdir -p /var/www/storage/client-logos \
    && chown -R www-data:www-data /var/www/storage \
    && chmod 750 /var/www/storage

# Apache virtual-host configuration
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# PHP production settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "session.cookie_secure = 1"  >> "$PHP_INI_DIR/php.ini" \
    && echo "session.cookie_httponly = 1" >> "$PHP_INI_DIR/php.ini" \
    && echo "session.cookie_samesite = Strict" >> "$PHP_INI_DIR/php.ini" \
    && echo "display_errors = Off"        >> "$PHP_INI_DIR/php.ini" \
    && echo "log_errors = On"             >> "$PHP_INI_DIR/php.ini" \
    && echo "expose_php = Off"            >> "$PHP_INI_DIR/php.ini"

EXPOSE 80
