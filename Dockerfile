FROM php:8.4.8-apache

# Install dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    default-mysql-client \
    && docker-php-ext-install mysqli pdo_mysql zip

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy source code
COPY src/ /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html

# Configure Apache
RUN echo "DirectoryIndex index.php index.html" >> /etc/apache2/apache2.conf
RUN echo "<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    </Directory>" > /etc/apache2/conf-available/allow-html.conf \
    && a2enconf allow-html

EXPOSE 80
