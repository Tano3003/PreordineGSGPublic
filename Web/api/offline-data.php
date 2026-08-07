<?php
// GET  → restituisce il menù salvato (offline-data.json)
// POST → salva il menù (protetto da API key), ruotando il precedente in .old
require __DIR__ . '/_lib.php';
send_cors();

$file   = data_path('offline-data.json');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  if (!file_exists($file)) {
    json_out(['error' => 'Nessun offline-data.json salvato.'], 404);
  }
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  readfile($file);
  exit;
}

if ($method === 'POST') {
  require_key();
  $data = body_json();
  if (!is_array($data)
      || !isset($data['categories']) || !is_array($data['categories'])
      || !isset($data['itemsBycat']) || !is_array($data['itemsBycat'])) {
    json_out(['error' => 'Formato dati non valido.'], 400);
  }
  rotate_old($file);
  write_json_atomic($file, $data);

  $tot = 0;
  foreach ($data['itemsBycat'] as $arr) {
    if (is_array($arr)) $tot += count($arr);
  }
  json_out([
    'ok'         => true,
    'categories' => count($data['categories']),
    'items'      => $tot,
    'syncedAt'   => isset($data['syncedAt']) ? $data['syncedAt'] : null,
  ]);
}

json_out(['error' => 'Metodo non supportato.'], 405);
