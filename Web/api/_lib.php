<?php
// Funzioni condivise dagli endpoint. Incluso da ogni file *.php.
require __DIR__ . '/config.php';

// Header CORS + gestione preflight OPTIONS.
function send_cors() {
  global $ALLOWED_ORIGIN;
  header('Access-Control-Allow-Origin: ' . $ALLOWED_ORIGIN);
  header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, x-api-key');
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
  }
}

// Risponde JSON e termina.
function json_out($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// File dove viene salvata la API key impostata dal pannello. Ha la
// precedenza su $API_KEY di config.php ed è protetto dal .htaccess di data/.
function apikey_file() { return data_path('apikey.json'); }

// API key effettiva: quella salvata dal pannello se presente, altrimenti
// quella in config.php (se diversa dal segnaposto). '' = non impostata.
function apikey_effective() {
  global $API_KEY;
  $d = read_json(apikey_file(), null);
  if (is_array($d) && !empty($d['key'])) return (string) $d['key'];
  if ($API_KEY !== '' && $API_KEY !== 'CAMBIA-QUESTA-CHIAVE') return (string) $API_KEY;
  return '';
}

// true se una API key valida è configurata (da pannello o da config.php).
function apikey_is_set() { return apikey_effective() !== ''; }

// Salva la API key impostata dal pannello. Ritorna true se la scrittura riesce.
function apikey_save($plain) {
  write_json_atomic(apikey_file(), ['key' => (string) $plain, 'updatedAt' => date('c')]);
  return file_exists(apikey_file());
}

// Verifica la API key (header x-api-key); altrimenti risponde e termina.
function require_key() {
  $key = apikey_effective();
  if ($key === '') {
    json_out(['error' => 'API_KEY non configurata nel server.'], 500);
  }
  $hdr = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
  if (!is_string($hdr) || !hash_equals($key, $hdr)) {
    json_out(['error' => 'API key non valida.'], 401);
  }
}

// Corpo della richiesta come array associativo (o null se non valido).
function body_json() {
  $raw = file_get_contents('php://input');
  $d = json_decode($raw, true);
  return is_array($d) ? $d : null;
}

function ensure_data_dir() {
  global $DATA_DIR;
  if (!is_dir($DATA_DIR)) @mkdir($DATA_DIR, 0775, true);
  return $DATA_DIR;
}

function data_path($name) {
  return ensure_data_dir() . '/' . $name;
}

function read_json($path, $default) {
  if (!file_exists($path)) return $default;
  $d = json_decode(file_get_contents($path), true);
  return $d === null ? $default : $d;
}

// Scrittura atomica: scrive in un file temporaneo e poi rinomina.
function write_json_atomic($path, $data) {
  ensure_data_dir();
  $tmp = $path . '.tmp';
  file_put_contents(
    $tmp,
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    LOCK_EX
  );
  rename($tmp, $path);
}

// ── Logo del sito (data/logo.<ext>) ──────────────────────────
//
// Sta in data/ come tutto il resto, quindi non è raggiungibile dal web
// (il .htaccess di quella cartella nega l'accesso diretto): lo serve logo.php.
//
// Solo formati raster: un SVG è un documento con dentro dello script, e
// servito dal nostro stesso dominio diventerebbe un modo per farlo girare.

function logo_exts() { return array('png', 'jpg', 'jpeg', 'webp', 'gif'); }

function logo_max_bytes() { return 2 * 1024 * 1024; }   // 2 MB

// Percorso del logo attuale, '' se non ne è stato caricato nessuno.
function logo_file() {
  foreach (logo_exts() as $e) {
    $p = data_path('logo.' . $e);
    if (file_exists($p)) return $p;
  }
  return '';
}

function logo_mime($path) {
  $map = array(
    'png'  => 'image/png',  'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp', 'gif' => 'image/gif',
  );
  $e = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  return isset($map[$e]) ? $map[$e] : 'application/octet-stream';
}

// Estensione da usare in base al formato riconosciuto da getimagesize().
// '' se il file non è un'immagine di un formato ammesso.
function logo_ext_from_type($type) {
  if ($type === IMAGETYPE_PNG)  return 'png';
  if ($type === IMAGETYPE_JPEG) return 'jpg';
  if ($type === IMAGETYPE_GIF)  return 'gif';
  if (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP) return 'webp';
  return '';
}

// URL del logo relativa alla cartella api/, con cache-buster sulla data di
// modifica; '' se non c'è logo. È questa che finisce nelle impostazioni.
function logo_url() {
  $f = logo_file();
  return $f ? 'logo.php?v=' . filemtime($f) : '';
}

// Cancella il logo qualunque estensione abbia. true se c'era qualcosa.
function logo_delete() {
  $trovato = false;
  foreach (logo_exts() as $e) {
    $p = data_path('logo.' . $e);
    if (file_exists($p)) { @unlink($p); $trovato = true; }
  }
  return $trovato;
}

// Rinomina  <nome>.json → <nome>.old.json  (cancella un .old preesistente).
// No-op se il file non esiste. Ritorna true se ha ruotato qualcosa.
function rotate_old($path) {
  if (!file_exists($path)) return false;
  $old = preg_replace('/\.json$/', '.old.json', $path);
  if (file_exists($old)) @unlink($old);
  rename($path, $old);
  return true;
}
