FROM php:8.2-apache

# Install SQLite3 and dependencies
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && apt-get clean

# Enable mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all files
COPY . /var/www/html/

# Set permissions
RUN chmod -R 755 /var/www/html

# Create .htaccess for routing
RUN echo "RewriteEngine On\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule ^ /bot.php [L]" > /var/www/html/.htaccess

EXPOSE 80
