FROM node:20-alpine AS node-builder
ARG PROXY
ENV http_proxy=$PROXY \
    HTTP_PROXY=$PROXY \
    https_proxy=$PROXY \
    HTTPS_PROXY=$PROXY

# Configure npm to use proxy if PROXY is set
RUN if [ -n "$PROXY" ]; then \
        npm config set proxy $PROXY && \
        npm config set https-proxy $PROXY; \
    fi && \
    npm config set registry https://registry.npmjs.org/

WORKDIR /app

# Add build essentials
RUN apk add --no-cache python3 make g++

# Copy package files
COPY package.json package-lock.json ./

# Copy all necessary config files
COPY resources/ ./resources/
COPY vite.config.js ./
COPY postcss.config.js ./
COPY tailwind.config.js ./

RUN npm install --prefer-offline --no-audit --no-progress

# Build assets with explicit env
RUN NODE_ENV=production npm run build

# Stage 2: Install PHP dependencies with Composer (PHP 8.3)
FROM php:8.3-cli-alpine AS composer-builder
ARG PROXY
ENV http_proxy=$PROXY \
    HTTP_PROXY=$PROXY \
    https_proxy=$PROXY \
    HTTPS_PROXY=$PROXY

WORKDIR /app

# Install system deps, intl/zip extensions and Composer itself
RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        git \
        curl \
        unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy composer files first
COPY composer.json composer.lock ./

# Copy the rest of the application before installing
COPY . .

# Install dependencies without running scripts
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

# Now run the post-install scripts
RUN composer run-script post-autoload-dump

# ---------------------------------------------------------------------------
# Two runtime targets, because the environments serve PHP differently:
#
#   runtime-fpm    production (applications.nc3.lu) - php-fpm on 9000, behind
#                  the host Apache vhost which proxies via fastcgi and serves
#                  static files from the host public/ directory.
#                  Selected explicitly by docker-compose.prod.yml.
#
#   runtime-apache test / Dokploy - self-contained Apache on port 80, routed by
#                  Traefik over the dokploy network.
#
# runtime-apache is deliberately LAST: `docker build` with no --target builds
# the final stage, so Dokploy continues to build the Apache image unchanged.
# ---------------------------------------------------------------------------

# Stage 3a: Production runtime (php-fpm)
FROM php:8.3-fpm-alpine AS runtime-fpm
ARG PROXY
ENV http_proxy=$PROXY \
    HTTP_PROXY=$PROXY \
    https_proxy=$PROXY \
    HTTPS_PROXY=$PROXY

WORKDIR /var/www/html

RUN apk update && apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    bash \
    icu-dev \
    mysql-client

# Extension set matches runtime-apache so the app behaves identically in both.
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip && \
    docker-php-ext-configure intl && \
    docker-php-ext-install intl

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer-builder /app /var/www/html
COPY --from=node-builder /app/public/build /var/www/html/public/build

RUN mkdir -p /var/www/html/storage/framework/{sessions,views,cache} \
    && mkdir -p /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

VOLUME /var/www/html/storage

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# Stage 3b: Test / Dokploy runtime (self-contained Apache). Must remain last.
FROM php:8.3-apache AS runtime-apache
ARG PROXY
ENV http_proxy=$PROXY \
    HTTP_PROXY=$PROXY \
    https_proxy=$PROXY \
    HTTPS_PROXY=$PROXY \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libicu-dev \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip && \
    docker-php-ext-configure intl && \
    docker-php-ext-install intl

# Enable Apache modules, set document root, and allow .htaccess
RUN a2enmod rewrite && \
    sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf && \
    sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Configure opcache
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Copy application files
COPY --from=composer-builder /app /var/www/html
COPY --from=node-builder /app/public/build /var/www/html/public/build

RUN mkdir -p /var/www/html/storage/framework/{sessions,views,cache} \
    && mkdir -p /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

VOLUME /var/www/html/storage

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]