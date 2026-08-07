<?php
// GET → diagnostica: conferma che il backend PHP risponde.
require __DIR__ . '/_lib.php';
send_cors();

$ordersFile = data_path('orders.json');
$menuFile   = data_path('offline-data.json');

json_out([
  'ok'        => true,
  'php'       => PHP_VERSION,
  'now'       => gmdate('c'),
  'writable'  => is_writable(ensure_data_dir()),
  'hasMenu'   => file_exists($menuFile),
  'orders'    => file_exists($ordersFile) ? count(read_json($ordersFile, [])) : 0,
]);
