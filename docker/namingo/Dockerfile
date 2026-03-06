FROM php:8.3-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl wget supervisor cron gnupg \
    libicu-dev libgmp-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libxml2-dev libc-client-dev libkrb5-dev libonig-dev libcurl4-openssl-dev \
    libssl-dev openssl mariadb-client procps uuid-dev \
    debian-keyring debian-archive-keyring apt-transport-https \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mysqli intl gmp soap imap gd zip \
        opcache bcmath sockets pcntl \
    && pecl install --configureoptions 'enable-openssl="yes" enable-sockets="yes" enable-mysqlnd="yes" enable-http2="no" enable-swoole-curl="no" enable-swoole-pgsql="no"' swoole \
    && pecl install redis uuid \
    && docker-php-ext-enable swoole redis uuid

# Install Caddy
RUN curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg \
    && curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list \
    && apt-get update && apt-get install -y caddy \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Clone Namingo registry
RUN git clone --depth 1 https://github.com/getnamingo/registry.git /opt/registry

# Install Composer dependencies for each component
WORKDIR /opt/registry
RUN cd epp && composer install --no-dev --no-interaction --optimize-autoloader 2>/dev/null || true \
    && cd ../whois/port43 && composer install --no-dev --no-interaction --optimize-autoloader 2>/dev/null || true \
    && cd ../../rdap && composer install --no-dev --no-interaction --optimize-autoloader 2>/dev/null || true \
    && cd ../das && composer install --no-dev --no-interaction --optimize-autoloader 2>/dev/null || true \
    && cd ../automation && composer install --no-dev --no-interaction --optimize-autoloader 2>/dev/null || true \
    && cd ../cp && composer install --no-dev --no-interaction --optimize-autoloader 2>/dev/null || true

# Create necessary directories
RUN mkdir -p /var/log/namingo /var/run /opt/registry/epp/certs \
    && chown -R www-data:www-data /opt/registry/cp

# Copy configuration files
COPY config/epp-config.php /opt/registry/epp/config.php
COPY config/whois-config.php /opt/registry/whois/port43/config.php
COPY config/rdap-config.php /opt/registry/rdap/config.php
COPY config/das-config.php /opt/registry/das/config.php
COPY config/automation-config.php /opt/registry/automation/config.php
COPY config/cp-env /opt/registry/cp/.env
COPY config/whois-web-config.php /opt/registry/whois/web/config.php
COPY config/init-data.sql /opt/init-data.sql
COPY config/Caddyfile /etc/caddy/Caddyfile

# Copy entrypoint and supervisor config
COPY entrypoint.sh /entrypoint.sh
COPY generate-certs.sh /generate-certs.sh
COPY supervisord.conf /etc/supervisor/conf.d/namingo.conf
RUN chmod +x /entrypoint.sh /generate-certs.sh

EXPOSE 700 43 7500 1043 80

ENTRYPOINT ["/entrypoint.sh"]
