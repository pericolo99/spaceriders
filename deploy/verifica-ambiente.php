<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/*
 * Controllo della configurazione prima di toccare lo schema.
 *
 * Lo chiama deploy/plesk-deploy.sh. Legge la configurazione come la legge
 * Laravel (virgolette, commenti, variabili del server) invece di cercare righe
 * nel .env.
 *
 * Esce con 1 e dice quale chiave guardare. Con 0 stampa soltanto «chiave» o
 * «senza-chiave», che lo script usa per decidere se generare APP_KEY.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$errori = [];

$connessione = config('database.default');
$db = config("database.connections.{$connessione}");

// «mariadb» è il nome del servizio nel docker-compose di sviluppo: arriva fin
// qui quando si copia .env.example senza correggerlo.
$host = (string) ($db['host'] ?? '');
if ($host !== '' && ! str_starts_with($host, '/') && filter_var($host, FILTER_VALIDATE_IP) === false && gethostbyname($host) === $host) {
    $errori[] = "DB_HOST={$host}: il nome non si risolve su questo server (qui di solito 127.0.0.1 o localhost).";
}

// Il punto delicato: le tabelle nuove stanno nel database del vecchio pannello,
// che ha già users, clienti e attrezzature. Senza prefisso la prima migrazione
// si fermerebbe su «users already exists» — o peggio, un domani, scriverebbe
// dentro le tabelle del vecchio.
$storico = config('database.connections.storico');
$stessoDatabase = ($storico['database'] ?? null) === ($db['database'] ?? null)
    && ($storico['host'] ?? null) === ($db['host'] ?? null);

if ($stessoDatabase && ($db['prefix'] ?? '') === '') {
    $errori[] = 'DB_PREFIX è vuoto, ma il database è quello del vecchio pannello: serve DB_PREFIX=sr_.';
}

if ($errori === [] && $stessoDatabase) {
    try {
        // Tutte le tabelle del database, prefisso compreso: se qualcosa col
        // prefisso scelto c'è già e non è nostro, meglio saperlo ora.
        $vecchie = collect(DB::getSchemaBuilder()->getTableListing(schemaQualified: false))
            ->intersect(['anagrafica', 'sessione_noleggio']);

        if ($vecchie->isEmpty()) {
            $errori[] = "STORICO_DB_DATABASE={$storico['database']}: non ci sono le tabelle del vecchio pannello (anagrafica, sessione_noleggio).";
        }
    } catch (Throwable $e) {
        $errori[] = 'Il database non risponde: '.$e->getMessage();
    }
}

if ($errori !== []) {
    fwrite(STDERR, "   configurazione non adatta a questo server:\n");
    foreach ($errori as $errore) {
        fwrite(STDERR, "   - {$errore}\n");
    }
    fwrite(STDERR, "   (vedi docs/INSTALLAZIONE_PLESK.md §4)\n");
    exit(1);
}

echo config('app.key') ? 'chiave' : 'senza-chiave';
