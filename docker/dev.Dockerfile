# Immagine di sviluppo di Kaukau Service.
# PHP 8.3, la stessa versione che si usa in produzione.
# Serve a eseguire composer, artisan e i test senza installare PHP sulla macchina.
FROM php:8.3-cli-alpine

# sqlite-dev serve a pdo_sqlite, usato dalla suite di test (SQLite in memoria).
# icu-data-full: Alpine porta i dati ICU di sole 4 lingue, e intl formatterebbe
# date e numeri all'inglese anche con APP_LOCALE=it.
# mariadb-client: lo usa docker/importa-storico.sh per caricare il dump del vecchio pannello.
RUN apk add --no-cache \
        git unzip icu-dev icu-data-full libzip-dev oniguruma-dev sqlite-dev \
        libpng-dev libjpeg-turbo-dev freetype-dev mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql pdo_sqlite bcmath intl zip gd exif \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Limiti di caricamento per le foto del telefono (il default di PHP è 2 MB).
COPY php.ini /usr/local/etc/php/conf.d/kaukau.ini

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

# Il container gira con l'uid dell'utente host (vedi docker-compose.yml):
# COMPOSER_HOME sta in /tmp, scrivibile da chiunque.
RUN mkdir -p /tmp/composer && chmod 777 /tmp/composer

# Bootstrap autonomo: .env, vendor, APP_KEY, migrazioni. Idempotente.
COPY dev-entrypoint.sh /usr/local/bin/dev-entrypoint
RUN chmod +x /usr/local/bin/dev-entrypoint

WORKDIR /app

ENTRYPOINT ["dev-entrypoint"]
