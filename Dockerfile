FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    && docker-php-ext-install \
        pdo_mysql \
        zip \
        intl \
        opcache \
        mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer via official script
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy config files
COPY nginx.conf /etc/nginx/sites-available/default
COPY nginx-main.conf /etc/nginx/nginx.conf
COPY entrypoint.sh /entrypoint.sh

# Make entrypoint executable
RUN chmod +x /entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html/var \
    && chmod -R 775 /var/www/html/var

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]