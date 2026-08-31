<?php
// Libreria condivisa per le pagine di amministrazione (settings/ e orders/).
// Gestisce: sessione, login con API key, token CSRF, intestazione/piè di pagina.
require __DIR__ . '/_lib.php';   // $API_KEY, $DATA_DIR, helper JSON, rotate_old

session_start();

function csrf_token() {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}

function check_csrf() {
  $t = isset($_POST['csrf']) ? $_POST['csrf'] : '';
  if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$t)) {
    http_response_code(400);
    exit('Token CSRF non valido. Ricarica la pagina.');
  }
}

// ── Password amministratore (hash in data/admin.json) ────────────
// L'API key resta solo per le API REST (upload remoto di offline-data.json);
// l'accesso ai pannelli è protetto da questa password.

define('ADMIN_PASS_MIN', 8);

function admin_pass_file() { return data_path('admin.json'); }

// true se una password è già stata impostata (dal primo accesso in poi).
function admin_pass_is_set() {
  $d = read_json(admin_pass_file(), null);
  return is_array($d) && !empty($d['hash']);
}

// Salva l'hash (bcrypt) della password. Ritorna true se la scrittura riesce.
function admin_pass_save($plain) {
  $hash = password_hash($plain, PASSWORD_DEFAULT);
  if (!$hash) return false;
  write_json_atomic(admin_pass_file(), ['hash' => $hash, 'updatedAt' => date('c')]);
  return file_exists(admin_pass_file());
}

function admin_pass_verify($plain) {
  $d = read_json(admin_pass_file(), null);
  if (!is_array($d) || empty($d['hash'])) return false;
  return password_verify((string)$plain, $d['hash']);
}

// Garantisce che l'utente sia autenticato; altrimenti mostra
// l'impostazione password (primo accesso) o il login, ed esce.
function admin_gate($pageTitle) {
  if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
  }

  if (!empty($_SESSION['admin_auth'])) return;   // già autenticato → prosegue

  // ── PRIMO ACCESSO: nessuna password impostata → l'admin ne sceglie una ──
  if (!admin_pass_is_set()) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_pass'])) {
      check_csrf();
      $p1  = (string) $_POST['new_pass'];
      $p2  = isset($_POST['new_pass2']) ? (string) $_POST['new_pass2'] : '';
      $key = isset($_POST['apikey']) ? trim((string) $_POST['apikey']) : '';
      if (mb_strlen($p1) < ADMIN_PASS_MIN) {
        $error = 'La password deve avere almeno ' . ADMIN_PASS_MIN . ' caratteri.';
      } elseif ($p1 !== $p2) {
        $error = 'Le due password non coincidono.';
      } elseif ($key !== '' && strlen($key) < 16) {
        $error = 'La API key deve avere almeno 16 caratteri (oppure lascia il campo vuoto).';
      } elseif (!admin_pass_save($p1)) {
        $error = 'Impossibile salvare la password: la cartella api/data/ non è scrivibile.';
      } else {
        if ($key !== '') apikey_save($key);
        $_SESSION['admin_auth'] = true;
        session_regenerate_id(true);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
      }
    }
    $csrf = csrf_token();
    admin_header($pageTitle, null);
    echo '<div class="card" style="max-width:380px;margin:40px auto">';
    echo '<h2>Primo accesso</h2>';
    echo '<p class="muted">Scegli la password di amministratore (richiesta agli accessi successivi) e, se vuoi, imposta subito la API key per l\'upload remoto del menù.</p>';
    if ($error) echo '<p class="msg err">' . htmlspecialchars($error) . '</p>';
    echo '<form method="post">';
    echo '<input type="hidden" name="csrf" value="' . $csrf . '">';
    echo '<label>Nuova password (min ' . ADMIN_PASS_MIN . ' caratteri)</label>';
    echo '<input type="password" name="new_pass" autocomplete="new-password" autofocus required>';
    echo '<label>Ripeti password</label>';
    echo '<input type="password" name="new_pass2" autocomplete="new-password" required>';
    echo '<label>API key <span class="muted">(opzionale, per l\'upload remoto)</span></label>';
    echo '<div class="row">';
    echo '<input type="text" name="apikey" id="apikey" placeholder="premi «Genera» o incolla una chiave" style="flex:1;min-width:170px;margin:0">';
    echo '<button class="btn alt" type="button" id="genKey">Genera</button>';
    echo '<button class="btn alt" type="button" id="copyKey">Copia</button>';
    echo '</div>';
    echo '<p class="msg ok" id="keyMsg" style="display:none"></p>';
    echo '<button class="btn" type="submit">Imposta ed entra</button>';
    echo '</form></div>';
    echo <<<'JS'
    <script>
    (function () {
      var inp = document.getElementById('apikey');
      var msg = document.getElementById('keyMsg');
      if (!inp) return;
      function show(t){ msg.textContent = t; msg.style.display = 'block'; }
      document.getElementById('genKey').addEventListener('click', function(){
        var b = new Uint8Array(32); crypto.getRandomValues(b);
        inp.value = Array.prototype.map.call(b, function(x){ return ('0'+x.toString(16)).slice(-2); }).join('');
        show('Chiave generata. Copiala: ti servirà nel gestionale.');
      });
      document.getElementById('copyKey').addEventListener('click', function(){
        if(!inp.value){ show('Genera prima una chiave.'); return; }
        inp.select(); inp.setSelectionRange(0, inp.value.length);
        function fb(){ try{ document.execCommand('copy'); show('Chiave copiata.'); }catch(e){ show('Copia manuale con Ctrl+C.'); } }
        if(navigator.clipboard && window.isSecureContext){
          navigator.clipboard.writeText(inp.value).then(function(){ show('Chiave copiata negli appunti.'); }, fb);
        } else { fb(); }
      });
    })();
    </script>
    JS;
    admin_footer();
    exit;
  }

  // ── ACCESSI SUCCESSIVI: richiede la password ──
  $error = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
    check_csrf();
    if (admin_pass_verify($_POST['admin_pass'])) {
      $_SESSION['admin_auth'] = true;
      session_regenerate_id(true);
      header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
      exit;
    }
    $error = 'Password non valida.';
  }
  $csrf = csrf_token();
  admin_header($pageTitle, null);
  echo '<div class="card" style="max-width:360px;margin:40px auto">';
  echo '<h2>Accesso amministratore</h2>';
  if ($error) echo '<p class="msg err">' . htmlspecialchars($error) . '</p>';
  echo '<form method="post">';
  echo '<input type="hidden" name="csrf" value="' . $csrf . '">';
  echo '<label>Password</label>';
  echo '<input type="password" name="admin_pass" autocomplete="current-password" autofocus required>';
  echo '<button class="btn" type="submit">Entra</button>';
  echo '</form></div>';
  admin_footer();
  exit;
}

function admin_header($pageTitle, $active = null) {
  echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
  echo '<title>' . htmlspecialchars($pageTitle) . ' — SAGRA Admin</title>';
  echo '<style>' . admin_css() . '</style></head><body><div class="wrap">';
  echo '<header class="topbar"><span class="brand">SAGRA · Admin</span>';
  if ($active !== null) {
    echo '<nav>';
    echo '<a href="../settings/" class="' . ($active === 'settings' ? 'on' : '') . '">Impostazioni</a>';
    echo '<a href="../orders/" class="'   . ($active === 'orders'   ? 'on' : '') . '">Ordini</a>';
    echo '<a href="?logout=1" class="logout">Esci</a>';
    echo '</nav>';
  }
  echo '</header><main>';
}

function admin_footer() {
  echo '</main></div></body></html>';
}

function admin_css() {
  return '
  *{box-sizing:border-box} body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f1f5f9;color:#0f172a}
  .wrap{max-width:880px;margin:0 auto;padding:0 16px 48px}
  .topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 0;flex-wrap:wrap}
  .brand{font-weight:800}
  nav{display:flex;gap:6px} nav a{padding:7px 12px;border-radius:8px;text-decoration:none;color:#334155;font-weight:600;font-size:.9rem}
  nav a.on{background:#0f172a;color:#fff} nav a.logout{color:#b91c1c}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px;margin-bottom:16px}
  h2{margin:0 0 12px;font-size:1.15rem} h3{margin:0 0 8px;font-size:1rem}
  label{display:block;font-size:.8rem;font-weight:700;color:#475569;margin:10px 0 4px}
  input[type=text],input[type=password],input[type=file],select{width:100%;padding:9px 11px;border:1.5px solid #cbd5e1;border-radius:9px;font-size:.95rem;background:#fff}
  input:focus{outline:none;border-color:#0f172a}
  .btn{display:inline-flex;align-items:center;gap:6px;margin-top:12px;padding:9px 16px;border:none;border-radius:9px;background:#0f172a;color:#fff;font-weight:700;font-size:.9rem;cursor:pointer;text-decoration:none}
  .btn.alt{background:#fff;color:#0f172a;border:1.5px solid #cbd5e1}
  .btn.danger{background:#fee2e2;color:#b91c1c;border:1.5px solid #fecaca}
  .msg{padding:9px 12px;border-radius:9px;font-weight:600;font-size:.88rem;margin:10px 0}
  .msg.ok{background:#dcfce7;color:#166534} .msg.err{background:#fee2e2;color:#b91c1c}
  .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
  table{width:100%;border-collapse:collapse;font-size:.9rem} th,td{padding:9px 8px;border-bottom:1px solid #e2e8f0;text-align:left}
  th{font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
  .num{text-align:right;font-variant-numeric:tabular-nums} .muted{color:#64748b;font-size:.82rem}
  /* Le note sotto ai campi vanno a capo: altrimenti il pulsante del form
     finisce in mezzo alla frase, sulla stessa riga. */
  small.muted{display:block;margin-top:6px}
  code{background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:1px 5px;font-size:.85em}
  .qrbox svg{width:220px;height:auto} .toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
  .toolbar input[type=text]{flex:1;min-width:160px;margin:0}
  ';
}
