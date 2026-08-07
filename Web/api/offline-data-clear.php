<?php
// POST → ruota offline-data.json in offline-data.old.json (protetto da API key).
require __DIR__ . '/_lib.php';
send_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['error' => 'Metodo non supportato.'], 405);
}
require_key();

$rotated = rotate_old(data_path('offline-data.json')) ? 1 : 0;
json_out(['ok' => true, 'rotated' => $rotated]);
