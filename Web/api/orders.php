<?php
// POST            → registra un ordine (PUBBLICO: lo inviano i clienti)
// GET             → elenco ordini, più recenti per primi (protetto da API key)
// DELETE ?id=XXX  → cancella un ordine            (protetto da API key)
// DELETE          → cancella tutti gli ordini      (protetto da API key)
require __DIR__ . '/_lib.php';
send_cors();

$file   = data_path('orders.json');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
  $o = body_json();
  if (!is_array($o) || !isset($o['items']) || !is_array($o['items'])) {
    json_out(['error' => 'Formato ordine non valido.'], 400);
  }
  $orders = read_json($file, []);
  $number = 0;
  foreach ($orders as $x) {
    if (isset($x['number']) && $x['number'] > $number) $number = $x['number'];
  }
  $number++;
  $order = [
    'id'        => bin2hex(random_bytes(8)),
    'number'    => $number,
    'createdAt' => gmdate('c'),
    'name'      => isset($o['name'])     ? mb_substr((string)$o['name'],   0, 100) : '',
    'table'     => isset($o['table'])    ? mb_substr((string)$o['table'],  0, 30)  : '',
    'covers'    => isset($o['covers'])   ? mb_substr((string)$o['covers'], 0, 10)  : '',
    'coperto'   => isset($o['coperto'])  ? (float)$o['coperto']  : 0,
    // Importo asporto: già una tantum, il front-end lo manda a 0 se non è per asporto.
    'importoAsporto' => isset($o['importoAsporto']) ? (float)$o['importoAsporto'] : 0,
    'note'      => isset($o['note'])     ? mb_substr((string)$o['note'], 0, 100)   : '',
    'perAsporto'=> isset($o['perAsporto']) ? (bool)$o['perAsporto'] : false,
    'total'     => isset($o['total'])    ? (float)$o['total']    : 0,
    'totalQty'  => isset($o['totalQty']) ? (int)$o['totalQty']   : 0,
    'items'     => $o['items'],
    'qr'        => isset($o['qr']) ? (string)$o['qr'] : '',
  ];
  $orders[] = $order;
  write_json_atomic($file, $orders);
  json_out(['ok' => true, 'id' => $order['id'], 'number' => $number]);
}

if ($method === 'GET') {
  require_key();
  $orders = read_json($file, []);
  usort($orders, function ($a, $b) {
    $na = isset($a['number']) ? $a['number'] : 0;
    $nb = isset($b['number']) ? $b['number'] : 0;
    return $nb - $na;
  });
  json_out($orders);
}

if ($method === 'DELETE') {
  require_key();
  $orders = read_json($file, []);
  $id = isset($_GET['id']) ? $_GET['id'] : '';

  if ($id === '') {                       // nessun id → cancella tutti
    $count = count($orders);
    write_json_atomic($file, []);
    json_out(['ok' => true, 'deleted' => $count]);
  }

  $found = false;
  $out   = [];
  foreach ($orders as $o) {
    if (isset($o['id']) && $o['id'] === $id) { $found = true; continue; }
    $out[] = $o;
  }
  if (!$found) json_out(['error' => 'Ordine non trovato.'], 404);
  write_json_atomic($file, $out);
  json_out(['ok' => true]);
}

json_out(['error' => 'Metodo non supportato.'], 405);
