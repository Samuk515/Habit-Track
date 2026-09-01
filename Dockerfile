FROM php:8.2-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy Apache configuration
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Copy project files
COPY . /var/www/html/

RUN composer install --no-dev --optimize-autoloader --working-dir=/var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
