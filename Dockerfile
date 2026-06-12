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
    openssl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd curl \
    && rm -rf /var/lib/apt/lists/*

# Enable required Apache modules (ssl + rewrite + headers)
RUN a2enmod rewrite headers ssl

# Copy application files
COPY public/ /var/www/html/
COPY src/    /var/www/src/
COPY views/  /var/www/views/
COPY .env    /var/www/html/.env

# ── Domain-locked HTTPS vhost ─────────────────────────────────────
# Self-signed cert for autosecforge.com (replace with a real cert in prod).
RUN mkdir -p /etc/ssl/asf \
    && openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
        -keyout /etc/ssl/asf/autosecforge.key \
        -out    /etc/ssl/asf/autosecforge.crt \
        -subj   "/C=US/O=AutoSecForge/CN=autosecforge.com" \
        -addext "subjectAltName=DNS:autosecforge.com"
COPY apache-vhost.conf /etc/apache2/sites-available/autosecforge.conf
RUN a2dissite 000-default default-ssl 2>/dev/null; \
    a2ensite autosecforge

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage 2>/dev/null || true

# HTTP (redirect) + HTTPS
EXPOSE 80 443

CMD ["apache2-foreground"]
