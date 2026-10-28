# Space Riders Service — installazione su Plesk

Pubblicazione da git, configurazione sul server, **stesso database del vecchio pannello**.

---

## 1. Che cosa serve sul server

| | |
|---|---|
| PHP | **8.3** (va bene anche 8.4) |
| Estensioni PHP | `bcmath` `ctype` `curl` `dom` `exif` `fileinfo` `gd` `intl` `mbstring` `openssl` `pdo_mysql` `tokenizer` `xml` `zip` |
| Database | quello del vecchio pannello (MariaDB 10.6+ o MySQL 8) |
| Composer | 2.x |
| Node.js | 20 o superiore, per compilare il tema a ogni pubblicazione |
| HTTPS | **obbligatorio**: senza, il telefono non apre la fotocamera per leggere i codici |

Non servono Redis, code né attività pianificate: sessioni e cache stanno nel database, e
l'applicazione non ha lavori in background.

---

## 2. Il database: uno solo, due serie di tabelle

Il nuovo pannello usa **lo stesso database** del vecchio. Il vecchio ha già tabelle chiamate
`users`, `clienti` e `attrezzature`, quindi le tabelle nuove prendono il prefisso **`sr_`**:

```
database del vecchio pannello
├── anagrafica, attrezzature, clienti, sessione_noleggio, users, …   ← il vecchio, non si tocca
└── sr_users, sr_clienti, sr_attrezzature, sr_noleggi, sr_noleggio_righe, sr_segnalazioni, …
```

- Il nuovo pannello **non scrive mai** nelle tabelle del vecchio: le legge soltanto, quando si
  lancia `kaukau:importa-storico` (§7).
- Il vecchio pannello continua a funzionare sulle sue tabelle come prima.
- Il prefisso lo impone lo script di pubblicazione: se `DB_PREFIX` è vuoto e il database è quello
  del vecchio, si ferma prima di toccare lo schema.
- In produzione `migrate:fresh`, `migrate:reset`, `db:wipe` e simili **sono disattivati**: in un
  database condiviso cancellerebbero anche le tabelle del vecchio pannello.

> Provato il 27/10/2026 su una copia del database di oggi: migrazioni e importazione con
> `DB_PREFIX=sr_` danno 3 utenti, 583 clienti, 504 pezzi, 889 noleggi (20 aperti) e 68
> segnalazioni. Le 24 tabelle del vecchio pannello, confrontate con `CHECKSUM TABLE` prima e
> dopo, sono rimaste identiche.

**Prima di tutto, una copia del database.** *Databases → (il database) → Export Dump*. È l'unico
passo che rende reversibile tutto il resto.

---

## 3. Il dominio

Il pannello vive sulla **radice di un dominio o sottodominio**, per esempio
`service.spaceriders.it`. Non in una sottocartella: l'app installabile sul telefono e le
scorciatoie «Consegna» e «Rientro» puntano a `/`.

*Websites & Domains → Add Subdomain*, poi:

- *SSL/TLS Certificates* → **Let's Encrypt**, con il reindirizzamento da http a https;
- *Hosting Settings → Document root*: **`httpdocs/public`** (o `<cartella>/public`). Senza questo
  passo dal web si vedrebbero `.env`, `storage` e `vendor`.

### PHP Settings

| | |
|---|---|
| Versione | 8.3 |
| `memory_limit` | 512M |
| `upload_max_filesize` | **25M** — le foto del telefono pesano 5–8 MB, il valore predefinito è 2M |
| `post_max_size` | **100M** — nel rientro si possono mandare più foto insieme |
| `max_file_uploads` | 20 |

Con valori più bassi le foto si fermano al caricamento con un errore che non dice il perché.
Sono gli stessi di `docker/php.ini`, che si usa in sviluppo.

---

## 4. Il file di ambiente

Vive sul server, **si scrive una volta sola** e nessuna pubblicazione lo tocca. Via SSH, nella
cartella del progetto (dopo la prima pubblicazione, §6):

```bash
cp .env.example .env
chmod 600 .env
```

`.env.example` è il file dello sviluppo in Docker: va corretto. I valori che contano:

```dotenv
APP_NAME="Space Riders Service"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://service.spaceriders.it
APP_KEY=                       # la genera lo script alla prima pubblicazione

APP_LOCALE=it
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mariadb          # mysql se il server ha MySQL
DB_HOST=127.0.0.1              # non «mariadb»: quello è il nome del servizio Docker
DB_PORT=3306
DB_DATABASE=«database del vecchio pannello»
DB_USERNAME=«utente del database»
DB_PASSWORD=«password del database»
DB_PREFIX=sr_                  # obbligatorio: vedi §2

# Il vecchio pannello è nello stesso database: l'importazione lo legge da qui.
STORICO_DB_DATABASE=«database del vecchio pannello»

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

LOG_LEVEL=warning
```

Le credenziali del database sono quelle del vecchio pannello (nel suo `app/config/database.php`):
*Databases* in Plesk le mostra, e lì si può anche cambiare la password, ricordandosi però di
aggiornarla nel vecchio pannello.

> **`APP_KEY` non si cambia più** dopo la prima volta: cifra le sessioni, e cambiarla butta fuori
> tutti.

Dopo ogni modifica al `.env`:

```bash
php artisan config:cache
```

### «Ho cambiato il `.env` ma legge sempre i valori vecchi»

È la configurazione in cache: `bootstrap/cache/config.php`, scritta da un `config:cache` o da un
`optimize` lanciato prima (anche da Plesk o a mano). Finché c'è, Laravel **non legge il `.env`**.
Sintomo tipico: dopo aver messo `DB_PREFIX=sr_` e `APP_DEBUG=false`, la pagina d'errore continua
a comparire e a cercare la tabella `sessions` senza prefisso.

```bash
php artisan config:clear       # oppure: rm bootstrap/cache/config.php
php artisan config:show database.connections.mariadb.prefix   # deve dire sr_
```

Se dice ancora il valore vecchio, il `.env` modificato non è quello del progetto: deve stare
accanto ad `artisan`, nella cartella di destinazione del Git di Plesk (non in `public/`).

---

## 5. Git e script di pubblicazione

*Websites & Domains → (il sottodominio) → Git → Add Repository*:

- **Remote Git hosting**: `git@github.com:pericolo99/spaceriders.git`. Plesk mostra una chiave
  pubblica: va aggiunta su GitHub in *Settings → Deploy keys* del repository (solo lettura).
- Branch: **`main`**. Cartella di destinazione: **`httpdocs`**.
- *Repository Settings → Enable additional deployment actions*:

  ```bash
  bash deploy/plesk-deploy.sh
  ```

Lo script ([`deploy/plesk-deploy.sh`](../deploy/plesk-deploy.sh)) fa, in ordine: dipendenze PHP
senza i pacchetti di sviluppo, controllo del `.env` (prefisso compreso), chiave applicativa se
manca, compilazione del tema, migrazioni (solo tabelle `sr_`), collegamento delle foto
(`storage:link`), cache. Si ferma al primo errore.

### Se il deploy dice «command not found» o «php non trovato»

L'abbonamento ha la shell **chroot**: i comandi lanciati da Plesk non vedono `/opt/plesk`, dove
stanno PHP e Node. Si risolve in *Subscriptions → (dominio) → Web Hosting Access → Access to the
server over SSH*: da **`/bin/bash (chrooted)`** a **`/bin/bash`**.

Se PHP, Composer o npm stanno in percorsi insoliti, glieli si indica:

```bash
PHP_BIN=/opt/plesk/php/8.3/bin/php \
COMPOSER_BIN=/usr/lib/plesk-9.0/composer.phar \
NPM_BIN=/opt/plesk/node/22/bin/npm \
bash deploy/plesk-deploy.sh
```

---

## 6. Prima installazione

1. **Copia del database** (§2), se non è già fatta.
2. *Git → Pull*. La prima pubblicazione **si ferma** dicendo che manca il `.env`: è normale.
3. Via SSH, nella cartella del progetto, crea il `.env` (§4).
4. *Git → Deploy* (o rilancia `bash deploy/plesk-deploy.sh`). Questa volta arriva a
   `✓ pubblicato`.
5. Importa i dati del vecchio pannello (§7).
6. Verifica (§9).

---

## 7. Importare i dati del vecchio pannello

```bash
php artisan kaukau:importa-storico
```

Copia nelle tabelle `sr_` utenti, clienti, attrezzature, noleggi e segnalazioni. Le **password
restano quelle** del vecchio pannello: ognuno entra con la propria email (in minuscolo) e la
password di sempre. I pagamenti non si importano.

Si può rilanciare: aggiunge clienti, pezzi e noleggi nuovi e aggiorna quelli che conosce. **Non
riporta però i rientri** registrati nel vecchio pannello su noleggi già importati: le righe di un
noleggio si scrivono solo la prima volta, così un rientro fatto nel pannello nuovo non viene mai
sovrascritto.

### Il passaggio dal vecchio al nuovo

Per questo il passaggio va fatto **in un momento solo**, a banco chiuso (la sera o prima
dell'apertura):

1. si smette di usare il vecchio pannello;
2. si lancia `php artisan kaukau:importa-storico`;
3. da quel momento consegne e rientri si registrano **solo nel nuovo**.

Il vecchio pannello può restare raggiungibile per consultare lo storico (pagamenti compresi), ma
non va più usato per registrare: quello che vi si scrive dopo il passaggio il nuovo non lo vede.

Prove prima del passaggio: tutto ciò che si registra nel nuovo pannello è un dato vero, nelle
stesse tabelle che si useranno dopo. Per provare senza sporcare, meglio farlo su una copia del
database (un secondo database in Plesk con il dump importato, e un `.env` che punta lì).

---

## 8. Aggiornamenti successivi

```bash
git push                    # poi «Pull» in Plesk, o il webhook di GitHub se configurato
```

Plesk scarica il codice e lancia lo script: dipendenze, tema, migrazioni, cache. Il `.env` e le
foto caricate (`storage/app/public`) restano dove sono.

---

## 9. Verifica finale

| | |
|---|---|
| `https://service.spaceriders.it` mostra il login con il logo | ✔ |
| `https://service.spaceriders.it/.env` risponde **404** | ✔ radice su `public/` |
| `https://service.spaceriders.it/up` risponde 200 | ✔ l'applicazione è viva |
| si entra con email e password del vecchio pannello | ✔ importazione riuscita |
| «Oggi» mostra i noleggi da far rientrare | ✔ |
| dal telefono, «Consegna» → icona della fotocamera apre la fotocamera | ✔ https a posto |
| una foto da telefono si carica nel rientro | ✔ limiti PHP a posto (§3) |
| `php artisan about` dice `Environment: production`, `Debug Mode: OFF` | ✔ |
| il vecchio pannello si apre ancora | ✔ le sue tabelle non sono state toccate |

---

## 10. Che cosa non fare

**Non lasciare `DB_PREFIX` vuoto.** Lo script si ferma, ma un comando lanciato a mano no.

**Non lanciare `migrate:fresh`, `db:wipe`, `migrate:reset`** né cercare di aggirarne il blocco:
in un database condiviso svuotano anche il vecchio pannello.

**Non mettere il `.env` nel repository**, nemmeno per un momento: le credenziali restano nella
storia di git.

**Non lasciare `APP_DEBUG=true`**: una pagina d'errore mostrerebbe query e configurazione.

**Non registrare noleggi in entrambi i pannelli** dopo il passaggio (§7).
