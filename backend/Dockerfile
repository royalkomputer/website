FROM php:8.2-cli

RUN apt-get update && apt-get install -y libpq-dev git \
    && docker-php-ext-install pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/render/project/src

COPY . .

# Backup ke git: butuh GIT_TOKEN + GIT_REPO_URL di env Render
# Setup: buat Personal Access Token di GitHub → Settings → Developer settings → Personal access tokens → Fine-grained tokens
#        repo scope: Contents (write) + Metadata (read)
#        Lalu set environment variable di Render:
#        - GIT_TOKEN: token GitHub
#        - GIT_REPO_URL: https://github.com/username/royalkomputer.git
#        - GIT_BRANCH: main (opsional, default: main)

EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:$PORT"]
