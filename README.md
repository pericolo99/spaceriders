# Kaukau Service

Il nuovo pannello del noleggio: consegne, rientri, inventario e clienti.
Sostituisce il vecchio pannello Slim 2 in `../pannello`.

Laravel 12 · PHP 8.3 · Filament 5 · MariaDB 11.4. Si usa dal banco, dall'ufficio e dal
telefono, che può installarlo come app (PWA).

## Avvio

```sh
docker compose up -d          # .env, composer, chiave, migrazioni e asset li fa il container
```

Poi si apre http://localhost:9092. Le porte si cambiano da `.env` (`APP_PORT`, `FORWARD_DB_PORT`).

## Dati del vecchio pannello

```sh
docker/importa-storico.sh ../space_riders_service_AAAA-MM-GG_….sql.zip
```

Il dump va nel database `storico`, e `php artisan kaukau:importa-storico` lo copia nelle
tabelle nuove. Si può rilanciare con un dump più recente: non duplica nulla e non tocca i
rientri già registrati nel pannello nuovo. Gli utenti mantengono la loro password.

Che cosa cambia rispetto al vecchio pannello:

- un noleggio è aperto finché ha pezzi fuori. Il vecchio campo `stato` a volte lo dava
  chiuso con dei pezzi ancora fuori: qui contano i pezzi;
- i codici che comparivano nei noleggi ma non più nell'inventario diventano pezzi
  «fuori servizio», per non perdere lo storico;
- i pagamenti non si importano e non compaiono nell'interfaccia.

## Al banco

Consegna, Rientro e Nuovo cliente sono finestre che si aprono da ogni pagina (pulsanti in
alto, riquadri di «Oggi»). Non si chiudono cliccando fuori né con Esc: solo con i loro
pulsanti, e se c'è qualcosa di inserito chiedono conferma prima di uscire.

- **Consegna**: si sceglie (o si crea) il cliente, si leggono i pezzi con la fotocamera
  o con il lettore laser, si conferma.
- **Rientro**: si legge (o si cerca) un pezzo e compare tutto il suo noleggio, con quel
  pezzo già smarcato «A posto». Gli altri si smarcano leggendoli o toccando l'esito; per
  i pezzi con problemi si aggiungono nota e foto. I pezzi senza scelta restano fuori.
- La stessa finestra si apre già sul noleggio dalla lista «Da far rientrare» di Oggi,
  dalla scheda e dall'elenco Noleggi.

Nel campo del codice si può anche scrivere marca, modello, misura o tipologia
(«rossignol 170», «sci head»):
compaiono i suggerimenti con immagine e stato. In consegna i pezzi non disponibili si
vedono ma non si scelgono; in rientro si propongono solo i pezzi fuori, con chi li ha.
La foto di un prodotto si carica dalla scheda del pezzo in Inventario e può valere per
tutti i pezzi dello stesso modello; senza foto si vede l'icona a tratto della tipologia
(`public/img/tipologie/*.svg`).

Gli avvisi hanno sempre un'etichetta («Pezzo già fuori», «Consegna registrata») e il
dettaglio sotto. I messaggi dei moduli sono in italiano (`lang/it`); se ne mancasse uno,
compare l'inglese, mai la chiave grezza.

Un pezzo «da controllare» o «fuori servizio» non si può consegnare finché non lo si
rimette in servizio (Inventario o Segnalazioni).

La fotocamera del telefono funziona solo su `https` (o su `localhost`): in rete locale
serve un certificato, in produzione basta quello del dominio.

## Logo

`public/brand/`: il logo Space Riders Snow Team in nero e in verde petrolio. Le icone
dell'app in `public/icons/` si ricavano dallo stesso file. L'originale è a 275 px: per icone
più nitide sul telefono servirebbe una versione più grande o vettoriale.

## In produzione

Su Plesk, nello stesso database del vecchio pannello con le tabelle prefissate `sr_`:
vedi [docs/INSTALLAZIONE_PLESK.md](docs/INSTALLAZIONE_PLESK.md).

## Test

```sh
docker compose exec app php artisan test
```

## Dove stanno le regole

- `app/Noleggi/Banco.php`: consegna, rientro, segnalazione, rimessa in servizio;
- `app/Noleggi/Oggi.php`: i numeri e le liste della pagina «Oggi»;
- `app/Livewire/*Operazione.php` e `resources/views/components/kk/finestra.blade.php`: le finestre del banco;
- `app/Console/Commands/ImportaStorico.php`: l'importazione dal vecchio pannello.
