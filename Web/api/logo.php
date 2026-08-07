<?php
// GET → restituisce il logo caricato dal pannello Impostazioni.
// Il file sta in data/logo.<ext>, che il web server non serve direttamente
// (.htaccess): questo endpoint è l'unico modo per arrivarci.
require __DIR__ . '/_lib.php';
send_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  json_out(['error' => 'Metodo non supportato.'], 405);
}

$file = logo_file();
if ($file === '') {
  json_out(['error' => 'Nessun logo caricato.'], 404);
}

// L'URL porta ?v=<data di modifica>: cambia a ogni logo nuovo, quindi la
// copia in cache si può tenere a lungo senza rischiare di mostrare il vecchio.
header('Content-Type: ' . logo_mime($file));
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=86400');
readfile($file);
