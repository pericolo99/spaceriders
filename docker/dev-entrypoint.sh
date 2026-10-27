#!/bin/sh
# Avvio autonomo del container di sviluppo di Kaukau Service.
#
# Lo scopo è che `docker compose up -d` basti: niente comandi manuali di
# bootstrap. Ogni passo è idempotente, quindi il riavvio di un ambiente già
# pronto non rifà nulla e parte in pochi secondi.
set -e

cd /app

log() { echo "[service] $*"; }

# ── 1. .env ─────────────────────────────────────────────────────────────────
if [ ! -f .env ]; then
    log ".env assente: lo creo da .env.example"
    cp .env.example .env
fi

# ── 2. dipendenze PHP ───────────────────────────────────────────────────────
# Si reinstallano solo se il vendor manca o se composer.lock è cambiato dopo
# l'ultima installazione. Il confronto è sull'impronta del lock, non sui
# timestamp: composer non sempre riscrive vendor/autoload.php.
IMPRONTA=vendor/.service-composer-lock

install_vendor() {
    log "composer install"
    composer install --no-interaction --prefer-dist --no-progress
    md5sum composer.lock | cut -d' ' -f1 > "$IMPRONTA"
}

if [ ! -f vendor/autoload.php ]; then
    install_vendor
elif [ ! -f "$IMPRONTA" ] || [ "$(cat "$IMPRONTA")" != "$(md5sum composer.lock | cut -d' ' -f1)" ]; then
    log "composer.lock cambiato dall'ultima installazione"
    install_vendor
fi

# ── 3. chiave applicativa ───────────────────────────────────────────────────
if ! grep -qE '^APP_KEY=.+' .env; then
    log "APP_KEY vuota: la genero"
    php artisan key:generate --force --no-interaction
fi

# ── 4. attesa del database ──────────────────────────────────────────────────
# La condition healthy del compose copre il caso normale; il retry serve ai
# riavvii in cui MariaDB è già su ma sta ancora ricostruendo InnoDB.
attempts=0
until php artisan db:monitor >/dev/null 2>&1; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge 30 ]; then
        log "database irraggiungibile dopo 60s: proseguo, la migrate dirà perché"
        break
    fi
    [ "$attempts" = 1 ] && log "attendo il database…"
    sleep 2
done

# ── 5. schema e link allo storage ───────────────────────────────────────────
log "migrate"
php artisan migrate --force --no-interaction

[ -L public/storage ] || php artisan storage:link >/dev/null 2>&1 || true

# In sviluppo la configurazione non si mette in cache: le cache residue di una
# sessione precedente nasconderebbero le modifiche a .env e config/.
php artisan config:clear >/dev/null 2>&1 || true

log "pronto → ${*}"
exec "$@"
