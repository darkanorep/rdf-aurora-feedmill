# Use a suitable PHP-FPM image
FROM php:8.5.1-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libzip-dev \
    default-libmysqlclient-dev \
    libonig-dev \
    autoconf \
    g++ \
    make \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Configure OPcache for better performance
RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.max_accelerated_files=7963'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.file_cache=/tmp'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Configure PHP-FPM for better performance
RUN { \
    echo '[www]'; \
    echo 'user = www-data'; \
    echo 'group = www-data'; \
    echo 'listen = 0.0.0.0:9000'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 50'; \
    echo 'pm.start_servers = 5'; \
    echo 'pm.min_spare_servers = 5'; \
    echo 'pm.max_spare_servers = 35'; \
    echo 'pm.process_idle_timeout = 10s'; \
    echo 'pm.max_requests = 500'; \
    } > /usr/local/etc/php-fpm.d/www.conf

# Install Composer with specific version for better caching
COPY --from=composer:2.5 /usr/bin/composer /usr/bin/composer

# Copy your Laravel application code
COPY . .

# Install Composer dependencies (without dev dependencies for production)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Set appropriate permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Create OPcache directory
RUN mkdir -p /tmp && chown www-data:www-data /tmp

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]