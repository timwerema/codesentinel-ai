# CodeSentinel AI: one image for the web server and the queue worker.
# The scanners are installed here so the worker can run them; nothing is fetched at scan time.
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# Ziggy's route helper is imported from vendor during the build.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends php-cli php-xml php-mbstring php-curl unzip git ca-certificates \
    && composer install --no-dev --no-interaction --prefer-dist --no-scripts \
    && npm run build

FROM golang:bookworm AS gitleaks
RUN GOBIN=/out go install github.com/zricethezav/gitleaks/v8@v8.30.1

FROM php:8.3-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git python3 python3-pip util-linux \
    && docker-php-ext-install pdo_pgsql pcntl zip \
    && pecl install redis && docker-php-ext-enable redis \
    && pip3 install --no-cache-dir --break-system-packages semgrep==1.186.0 \
    && rm -rf /var/lib/apt/lists/*
COPY --from=gitleaks /out/gitleaks /usr/local/bin/gitleaks
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# The app runs as an unprivileged user. Scanners therefore already run without privileges.
RUN useradd --create-home --uid 10001 app
WORKDIR /var/www/app
COPY --chown=app:app . .
COPY --chown=app:app --from=assets /app/public/build public/build
USER app
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
