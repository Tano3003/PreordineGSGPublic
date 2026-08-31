<?php
// GET  → restituisce le impostazioni del sito (titolo, sottotitolo)  [pubblico]
// POST → salva le impostazioni                                       [protetto]
require __DIR__ . '/_lib.php';
send_cors();

$file   = data_path('settings.json');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  // 'logo' = URL relativa a questa cartella (o '' se non ne è stato caricato
  // uno). Il logo si carica dal pannello, non da qui: è un file, non un campo.
  $logo = logo_url();

  // 404 se non ancora configurato: il sito ripiega sul titolo di config.json.
  // Un logo caricato però va segnalato lo stesso, altrimenti resterebbe
  // invisibile finché qualcuno non salva anche il titolo.
  if (!file_exists($file)) {
    if ($logo === '') json_out(['error' => 'Impostazioni non configurate.'], 404);
    json_out(['logo' => $logo]);
  }
  // NB: non forziamo 'coperto' né 'importoAsporto' se mancano nel file
  // (impostazioni salvate prima di queste funzioni): così non sovrascrivono
  // con 0 i valori di config.json.
  $s = read_json($file, ['title' => 'SAGRA', 'subtitle' => '', 'coperto' => 0, 'importoAsporto' => 0]);
  // Impostazioni salvate prima di questa opzione: nessuna chiave => tastiera
  // numerica, che è il default e il comportamento che il sito aveva prima.
  if (!isset($s['tastieraTavolo'])) $s['tastieraTavolo'] = 'numerica';
  $s['logo'] = $logo;
  json_out($s);
}

if ($method === 'POST') {
  require_key();
  $b = body_json();
  if (!is_array($b)) {
    json_out(['error' => 'Formato non valido.'], 400);
  }
  $s = [
    'title'    => isset($b['title'])    ? mb_substr((string)$b['title'],    0, 80) : 'SAGRA',
    'subtitle' => isset($b['subtitle']) ? mb_substr((string)$b['subtitle'], 0, 60) : '',
    'coperto'  => isset($b['coperto'])  ? max(0, (float)$b['coperto']) : 0,
    // Importo asporto: addebitato una sola volta per ordine, solo col flag asporto.
    'importoAsporto' => isset($b['importoAsporto']) ? max(0, (float)$b['importoAsporto']) : 0,
    // Campo note nel preordine: assente nel payload = comportamento di default (mostrato).
    'mostraNote' => isset($b['mostraNote']) ? (bool)$b['mostraNote'] : true,
    // Campo «Tavolo» nel preordine: assente nel payload = mostrato (default).
    'mostraTavolo' => isset($b['mostraTavolo']) ? (bool)$b['mostraTavolo'] : true,
    // Tastiera del campo «Tavolo» sul telefono: 'alfanumerica' apre quella
    // completa, qualunque altro valore (default) la tastierina dei soli numeri.
    // Il tavolo resta comunque una stringa libera: qui si sceglie la tastiera.
    'tastieraTavolo' => (isset($b['tastieraTavolo']) && $b['tastieraTavolo'] === 'alfanumerica') ? 'alfanumerica' : 'numerica',
  ];
  if (trim($s['title']) === '') $s['title'] = 'SAGRA';
  write_json_atomic($file, $s);
  json_out(array_merge(['ok' => true], $s));
}

json_out(['error' => 'Metodo non supportato.'], 405);
