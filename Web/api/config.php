<?php
// ─────────────────────────────────────────────────────────────
// Configurazione del backend PHP di SAGRA.
// Modifica questi valori dopo aver caricato i file via FTP.
// ─────────────────────────────────────────────────────────────

// Chiave segreta per l'upload REMOTO di offline-data.json via API REST
// (header x-api-key). NON è la password del pannello admin: quella la
// imposti al primo accesso e viene salvata in data/admin.json.
$API_KEY = 'CAMBIA-QUESTA-CHIAVE';

// Origini ammesse per le chiamate dal sito statico (CORS).
//  '*'                       = qualsiasi origine (più semplice)
//  'https://tuosito.it'      = solo il tuo dominio TopHost (più sicuro)
$ALLOWED_ORIGIN = '*';

// Cartella dove vengono salvati i dati (deve essere scrivibile dal web server).
// Di default è la sottocartella data/ accanto a questi file.
$DATA_DIR = __DIR__ . '/data';
