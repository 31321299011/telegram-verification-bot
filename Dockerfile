FROM php:8.2-apache

# Install SQLite extension
RUN docker-php-ext-install pdo_sqlite

# Enable mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all files
COPY . /var/www/html/

# Set permissions
RUN chmod -R 755 /var/www/html

# Configure Apache to use bot.php as entry point
RUN echo "RewriteEngine On\nRewriteRule ^$ /bot.php [L]" > /var/www/html/.htaccess

EXPOSE 80
