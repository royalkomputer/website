FROM php:8.2-cli

RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/render/project/src

COPY . .

EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:$PORT"]
