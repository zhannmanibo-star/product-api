FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Allow Apache/PHP to read Render secret files in /etc/secrets
RUN usermod -a -G 1000 www-data

WORKDIR /var/www/html

COPY . .

# LavaLust writable cache/runtime directory
RUN mkdir -p /var/www/html/runtime/cache \
    && chown -R www-data:www-data /var/www/html/runtime \
    && chmod -R 775 /var/www/html/runtime

# Apache document root -> LavaLust public directory
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Enable .htaccess
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf

# Render port
RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:10000>/' \
    /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

CMD ["apache2-foreground"]