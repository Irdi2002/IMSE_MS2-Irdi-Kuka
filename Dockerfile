FROM php:8.2-apache

# Enable required PHP extensions and install necessary packages
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    zip \
    unzip \
    git \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install pdo pdo_mysql mysqli

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy the PHP app into the container
COPY src/ /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/

# Update Composer and install dependencies if composer.json exists
WORKDIR /var/www/html/
RUN if [ -f composer.json ]; then composer update; fi

# Install Faker using Composer if composer.json exists
RUN if [ -f composer.json ]; then composer require fakerphp/faker; fi

# Set default work directory
WORKDIR /var/www/html
