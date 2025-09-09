FROM php:8.4.8-apache

# Install extensions for MySQL
RUN apt-get update && apt-get install -y \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql

# Enable Apache rewrite module (for .htaccess if needed)
RUN a2enmod rewrite

# Copy PHP files from src/ to Apache document root
COPY src/ /var/www/html/

# Set permissions for Apache user
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html

# Configure Apache to prioritize index.php
RUN echo "DirectoryIndex index.php index.html" >> /etc/apache2/apache2.conf

# Allow access to document root
RUN echo "<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>" > /etc/apache2/conf-available/allow-html.conf \
    && a2enconf allow-html

EXPOSE 80