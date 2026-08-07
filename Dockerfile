FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy Apache configuration
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Copy project files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80