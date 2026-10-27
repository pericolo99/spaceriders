#!/bin/sh
# Carica il dump del vecchio pannello nel database `storico` e lo copia
# nelle tabelle nuove.
#
#   docker/importa-storico.sh ../space_riders_service_2026-10-27_07-33-07.sql.zip
#
# Si può rilanciare: il database `storico` viene svuotato e ricaricato, e
# l'importazione aggiorna i record già copiati invece di duplicarli.
set -e

DUMP="$1"
if [ -z "$DUMP" ] || [ ! -f "$DUMP" ]; then
    echo "uso: $0 <dump.sql | dump.sql.zip>" >&2
    exit 1
fi

cd "$(dirname "$0")/.."

ROOT_PW=$(grep -E '^DB_ROOT_PASSWORD=' .env 2>/dev/null | cut -d= -f2-)
ROOT_PW=${ROOT_PW:-root}

echo "[storico] svuoto e ricreo il database storico"
docker compose exec -T mariadb mariadb -uroot -p"$ROOT_PW" -e \
    "DROP DATABASE IF EXISTS storico; CREATE DATABASE storico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON storico.* TO 'kaukau'@'%';"

echo "[storico] carico $DUMP"
case "$DUMP" in
    *.zip) unzip -p "$DUMP" '*.sql' ;;
    *) cat "$DUMP" ;;
esac | docker compose exec -T mariadb mariadb -uroot -p"$ROOT_PW" storico

echo "[storico] copio i dati nelle tabelle nuove"
docker compose exec -T app php artisan kaukau:importa-storico
