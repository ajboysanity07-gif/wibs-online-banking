# syntax=docker/dockerfile:1

FROM php:8.3-cli-bookworm AS vendor
WORKDIR /app

RUN apt-get update && apt-get install -y \
    git unzip zip curl gnupg2 ca-certificates apt-transport-https \
    libzip-dev libicu-dev libpng-dev libonig-dev libxml2-dev \
    libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
 && mkdir -p /etc/apt/keyrings \
 && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
 && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
 && apt-get update && apt-get install -y nodejs \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install bcmath gd intl mbstring zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader

COPY package*.json ./
RUN npm install

COPY . .
RUN composer dump-autoload --optimize --no-dev
RUN npm run build

FROM php:8.3-apache-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    ACCEPT_EULA=Y \
    PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    ca-certificates curl gnupg2 apt-transport-https \
    git unzip zip \
    libzip-dev libicu-dev libpng-dev libonig-dev libxml2-dev \
    libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
    unixodbc-dev chromium \
 && mkdir -p /usr/share/keyrings /etc/apt/keyrings \
 && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
 && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/microsoft-prod.list \
 && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
 && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
 && apt-get update \
 && ACCEPT_EULA=Y apt-get install -y msodbcsql18 nodejs \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install bcmath gd intl mbstring pdo zip \
 && pecl install sqlsrv pdo_sqlsrv \
 && docker-php-ext-enable sqlsrv pdo_sqlsrv \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

COPY --from=vendor /app /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint \
 && mkdir -p storage bootstrap/cache \
 && chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]
