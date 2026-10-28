#!/usr/bin/env bash
#
# Pubblicazione di Space Riders Service su Plesk.
#
# Lo esegue Plesk dopo ogni pull (Git → Repository Settings → additional
# deployment actions):
#
#     bash deploy/plesk-deploy.sh
#
# Ogni passo è idempotente: rieseguirlo su un'installazione già aggiornata non
# fa danni. Si ferma al primo errore, perché una pubblicazione a metà è peggio
# di una non fatta.
set -euo pipefail

# Niente comandi esterni per trovarsi: le azioni di deploy di Plesk possono
# girare con un PATH minimo, dove nemmeno «dirname» esiste.
case "$0" in
    */*) cd "${0%/*}/.." ;;
    *)   cd .. ;;
esac

# Ogni pubblicazione lascia traccia in storage/logs/pubblicazione.log: le azioni
# di deploy di Plesk mostrano l'output solo sul momento, e senza questo file non
# si capisce se lo script è partito né dove si è fermato.
LOG=storage/logs/pubblicazione.log
if command -v tee >/dev/null 2>&1 && [ -d storage/logs ]; then
    exec > >(tee -a "$LOG") 2>&1
fi
echo "== $(date '+%d/%m/%Y %H:%M:%S' 2>/dev/null || echo '?') · pubblicazione da $(whoami 2>/dev/null || echo '?')"

trova() {
    for candidato in "$@"; do
        [ -x "$candidato" ] && { echo "$candidato"; return 0; }
    done
    return 1
}

PHP="${PHP_BIN:-}"
if [ -z "$PHP" ]; then
    PHP="$(trova /opt/plesk/php/8.3/bin/php /opt/plesk/php/8.4/bin/php /usr/bin/php8.3 /usr/bin/php || true)"
    [ -z "$PHP" ] && PHP="$(command -v php || true)"
fi

if [ -z "$PHP" ] || [ ! -x "$PHP" ]; then
    echo "   php non trovato. Con la shell chroot /opt/plesk non è raggiungibile:" >&2
    echo "   vedi docs/INSTALLAZIONE_PLESK.md §5.4, oppure passa PHP_BIN=/percorso/di/php." >&2
    exit 1
fi

COMPOSER="${COMPOSER_BIN:-}"
[ -z "$COMPOSER" ] && COMPOSER="$(trova /usr/lib/plesk-9.0/composer.phar /usr/local/bin/composer || true)"
[ -z "$COMPOSER" ] && COMPOSER="$(command -v composer || true)"

if [ -z "$COMPOSER" ]; then
    echo "   composer non trovato: passa COMPOSER_BIN=/percorso/di/composer.phar" >&2
    exit 1
fi

echo "   php:      $PHP ($("$PHP" -r 'echo PHP_VERSION;' 2>/dev/null || echo '?'))"
echo "   composer: $COMPOSER"

# nodenv installato ma senza versione scelta: npm parte, poi esbuild invoca
# «node» e lo shim non sa quale usare. Si sceglie la più recente con major pari.
if ! node -v >/dev/null 2>&1 && command -v nodenv >/dev/null 2>&1; then
    disponibili="$(nodenv versions --bare 2>/dev/null || true)"
    scelta="$(printf '%s\n' "$disponibili" | awk -F. '$1 % 2 == 0' | sort -V | tail -n 1)"
    [ -z "$scelta" ] && scelta="$(printf '%s\n' "$disponibili" | sort -V | tail -n 1)"

    if [ -n "$scelta" ]; then
        echo "   node: nessuna versione attiva, uso la $scelta"
        export NODENV_VERSION="$scelta"
    fi
fi

# Plesk tiene più versioni di Node sotto /opt/plesk/node/<versione>/bin:
# va bene la più recente (serve la 20 o superiore).
# Dalle azioni di deploy il PATH è più corto che in SSH: si guarda anche dove
# lo mettono nodenv e nvm nella home dell'utente.
if [ -n "${NPM_BIN:-}" ]; then
    NPM="$NPM_BIN"
else
    NPM="$(command -v npm || true)"
    for candidato in /opt/plesk/node/*/bin/npm "$HOME"/.nodenv/shims/npm "$HOME"/.nvm/versions/node/*/bin/npm /usr/local/bin/npm /usr/bin/npm; do
        [ -z "$NPM" ] && [ -x "$candidato" ] && NPM="$candidato"
    done
    for candidato in /opt/plesk/node/*/bin/npm; do
        [ -x "$candidato" ] && NPM="$candidato"
    done
fi
echo "   npm:      ${NPM:-non trovato}"

echo "→ dipendenze"
case "$COMPOSER" in
    *.phar) "$PHP" "$COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader ;;
    *)      "$COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader ;;
esac

# La configurazione vive sul server e non passa da git: lo script la pretende.
if [ ! -r .env ]; then
    echo "   manca .env — va creato una volta sola sul server (vedi docs/INSTALLAZIONE_PLESK.md §4)" >&2
    exit 1
fi

# Una cache rimasta da un tentativo precedente vincerebbe sul .env appena corretto.
"$PHP" artisan config:clear >/dev/null 2>&1 || true

# Il controllo che conta di più: database del vecchio pannello → prefisso obbligatorio.
ambiente="$("$PHP" deploy/verifica-ambiente.php)"

# Si genera solo se manca: rigenerarla butterebbe fuori tutti a ogni pubblicazione.
if [ "$ambiente" = "senza-chiave" ]; then
    echo "→ chiave applicativa (mancava)"
    "$PHP" artisan key:generate --force --no-interaction
fi

# Tailwind genera le classi leggendo le viste: il tema si ricompila a ogni
# pubblicazione, dal codice appena arrivato.
echo "→ asset del tema"
if [ -x "$NPM" ]; then
    case "$NPM" in
        */*) PATH="${NPM%/*}:$PATH"; export PATH ;;
    esac

    "$NPM" ci --no-audit --no-fund --loglevel=error
    "$NPM" run build
elif [ "${SENZA_ASSET:-}" = "1" ]; then
    echo "   npm non trovato, SENZA_ASSET=1: il tema resta quello di prima" >&2
else
    # Ci si ferma: con il tema vecchio le classi nuove delle viste non esistono,
    # e il pannello si rompe in modi che non sembrano un problema di pubblicazione
    # (il 28/10/2026 il riquadro Rientro di Oggi era bianco su bianco).
    echo "   npm non trovato: il tema non si può ricompilare." >&2
    echo "   Passa NPM_BIN=/percorso/di/npm, oppure SENZA_ASSET=1 se è voluto." >&2
    exit 1
fi

# Solo le tabelle con il prefisso: quelle del vecchio pannello non si toccano.
echo "→ schema"
"$PHP" artisan migrate --force --no-interaction

# Le foto dei pezzi e dei danni stanno in storage/app/public.
echo "→ storage"
[ -L public/storage ] || "$PHP" artisan storage:link

echo "→ cache"
"$PHP" artisan optimize
"$PHP" artisan filament:optimize

echo "✓ pubblicato"
