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
    libcurl4-openssl-dev \
    openssl \
    ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd curl zip \
    && rm -rf /var/lib/apt/lists/*

# wkhtmltopdf for real PDF report export. Debian 13 (trixie) dropped the apt
# package, so install the upstream patched-Qt static build — it renders headless
# (no xvfb needed). Best-effort: if the download or its deps fail, the build
# still succeeds and report.php falls back to a browser print-to-PDF view.
RUN set -eu; \
    arch="$(dpkg --print-architecture)"; \
    url="https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_${arch}.deb"; \
    apt-get update; \
    if curl -fsSL -o /tmp/wkhtmltox.deb "$url"; then \
        apt-get install -y --no-install-recommends /tmp/wkhtmltox.deb \
            || echo "WARN: wkhtmltopdf dependencies unmet — PDF will use the print fallback"; \
    else \
        echo "WARN: wkhtmltopdf download failed — PDF will use the print fallback"; \
    fi; \
    rm -f /tmp/wkhtmltox.deb; \
    rm -rf /var/lib/apt/lists/*

# Enable required Apache modules (ssl + rewrite + headers)
RUN a2enmod rewrite headers ssl

# Allow large mobile-app uploads (APK/IPA) for the MobSF scanner page.
RUN { \
      echo 'upload_max_filesize=300M'; \
      echo 'post_max_size=300M'; \
      echo 'max_execution_time=600'; \
      echo 'max_input_time=600'; \
      echo 'memory_limit=512M'; \
    } > /usr/local/etc/php/conf.d/asf-uploads.ini

# Copy application files.
# Note: .env is NOT baked into the image — it's git-ignored (may not exist in
# the build context) and secrets don't belong in image layers. At runtime the
# compose volume mount of ./public provides /var/www/html/.env (public/.env).
COPY public/ /var/www/html/
COPY src/    /var/www/src/
COPY views/  /var/www/views/

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
