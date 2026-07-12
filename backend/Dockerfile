FROM php:8.2-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libwebp-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    nginx \
    supervisor \
    curl \
    git \
    wget \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pgsql gd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/app

COPY . .

# Nginx config
RUN rm -f /etc/nginx/sites-enabled/default
COPY nginx.conf /etc/nginx/sites-enabled/default

# Supervisor config
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Buat direktori yang diperlukan
RUN mkdir -p /opt/app/uploads /opt/app/data && \
    chmod -R 755 /opt/app/uploads /opt/app/data

# PHP-FPM listen on TCP (bukan socket) untuk Nginx
RUN sed -i 's/listen = \/run\/php\/php8.2-fpm.sock/listen = 9000/' /usr/local/etc/php-fpm.d/zz-docker.conf

EXPOSE 80

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
