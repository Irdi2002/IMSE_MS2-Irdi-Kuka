# Use the official PHP image with Apache
FROM php:8.2-apache

# Enable required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Copy the PHP app into the container
COPY src/ /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/
