FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libzip-dev libonig-dev \
    && docker-php-ext-install bcmath intl mbstring pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
ENV COMPOSER_HOME=/tmp/composer

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
