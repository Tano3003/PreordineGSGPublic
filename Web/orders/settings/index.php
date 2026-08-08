<?php
// Pagina di amministrazione: configurazione del sito + gestione del menù.
require __DIR__ . '/../api/_admin.php';
admin_gate('Impostazioni');

$settingsFile = data_path('settings.json');
$menuFile     = data_path('offline-data.json');
$msg = ''; $msgErr = false;

// ── Azioni POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $action = isset($_POST['action']) ? $_POST['action'] : '';

  if ($action === 'settings') {
    $title = isset($_POST['title']) ? mb_substr(trim($_POST['title']), 0, 80) : '';
    if ($title === '') $title = 'SAGRA';
    $sub   = isset($_POST['subtitle']) ? mb_substr(trim($_POST['subtitle']), 0, 60) : '';
    // Importo coperto in € (accetta la virgola decimale). 0 = nessun coperto.
    $coperto = isset($_POST['coperto']) ? (float)str_replace(',', '.', trim($_POST['coperto'])) : 0;
    if ($coperto < 0) $coperto = 0;
    // Importo asporto: una tantum per ordine, solo se il cliente sceglie l'asporto.
    $asporto = isset($_POST['importoAsporto']) ? (float)str_replace(',', '.', trim($_POST['importoAsporto'])) : 0;
    if ($asporto < 0) $asporto = 0;
    write_json_atomic($settingsFile, [
      'title' => $title, 'subtitle' => $sub,
      'coperto' => $coperto, 'importoAsporto' => $asporto
    ]);
    $msg = 'Configurazione salvata.';
  }

  elseif ($action === 'logo') {
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
      $msg = 'Nessun file caricato o errore di upload.'; $msgErr = true;
    } elseif ($_FILES['logo']['size'] > logo_max_bytes()) {
      $msg = 'Il logo supera i ' . round(logo_max_bytes() / 1048576) . ' MB.'; $msgErr = true;
    } else {
      // Il formato lo decide getimagesize dal contenuto, non l'estensione del
      // nome: un file qualsiasi rinominato .png non passa di qui.
      $info = @getimagesize($_FILES['logo']['tmp_name']);
      $ext  = $info ? logo_ext_from_type($info[2]) : '';
      if ($ext === '') {
        $msg = 'Formato non riconosciuto: carica un PNG, JPG, WEBP o GIF.'; $msgErr = true;
      } else {
        logo_delete();          // via il precedente, che può avere un'altra estensione
        ensure_data_dir();
        if (!move_uploaded_file($_FILES['logo']['tmp_name'], data_path('logo.' . $ext))) {
          $msg = 'Impossibile salvare il logo: la cartella api/data/ non è scrivibile.'; $msgErr = true;
        } else {
          $msg = 'Logo salvato: ora compare sulla pagina dei preordini.';
        }
      }
    }
  }

  elseif ($action === 'logo_del') {
    $msg = logo_delete() ? 'Logo rimosso.' : 'Nessun logo da rimuovere.';
  }

  elseif ($action === 'upload') {
    if (!isset($_FILES['menu']) || $_FILES['menu']['error'] !== UPLOAD_ERR_OK) {
      $msg = 'Nessun file caricato o errore di upload.'; $msgErr = true;
    } else {
      $raw  = file_get_contents($_FILES['menu']['tmp_name']);
      $data = json_decode($raw, true);
      if (!is_array($data) || !isset($data['categories']) || !is_array($data['categories'])
          || !isset($data['itemsBycat']) || !is_array($data['itemsBycat'])) {
        $msg = 'File non valido: mancano "categories" o "itemsBycat".'; $msgErr = true;
      } else {
        rotate_old($menuFile);
        write_json_atomic($menuFile, $data);
        $tot = 0; foreach ($data['itemsBycat'] as $a) { if (is_array($a)) $tot += count($a); }
        $msg = 'Menù salvato: ' . count($data['categories']) . ' categorie, ' . $tot . ' pietanze. (Il precedente è stato salvato come .old.)';
      }
    }
  }

  elseif ($action === 'clear') {
    $r = rotate_old($menuFile);
    $msg = $r ? 'Menù rimosso (rinominato in offline-data.old.json). Gli ordini sono bloccati finché non ne carichi uno nuovo.'
              : 'Nessun menù da rimuovere.';
  }

  elseif ($action === 'apikey') {
    $key = isset($_POST['apikey']) ? trim($_POST['apikey']) : '';
    if (strlen($key) < 16) {
      $msg = 'La API key deve avere almeno 16 caratteri. Usa «Genera» per crearne una sicura.'; $msgErr = true;
    } elseif (!apikey_save($key)) {
      $msg = 'Impossibile salvare la API key: la cartella api/data/ non è scrivibile.'; $msgErr = true;
    } else {
      $msg = 'API key salvata: l\'upload remoto è ora attivo.';
    }
  }

  elseif ($action === 'password') {
    $cur = isset($_POST['cur_pass'])  ? (string) $_POST['cur_pass']  : '';
    $p1  = isset($_POST['new_pass'])  ? (string) $_POST['new_pass']  : '';
    $p2  = isset($_POST['new_pass2']) ? (string) $_POST['new_pass2'] : '';
    if (!admin_pass_verify($cur)) {
      $msg = 'Password attuale errata.'; $msgErr = true;
    } elseif (mb_strlen($p1) < ADMIN_PASS_MIN) {
      $msg = 'La nuova password deve avere almeno ' . ADMIN_PASS_MIN . ' caratteri.'; $msgErr = true;
    } elseif ($p1 !== $p2) {
      $msg = 'Le due nuove password non coincidono.'; $msgErr = true;
    } elseif (!admin_pass_save($p1)) {
      $msg = 'Impossibile salvare la password: la cartella api/data/ non è scrivibile.'; $msgErr = true;
    } else {
      $msg = 'Password aggiornata.';
    }
  }
}

// ── Dati correnti ────────────────────────────────────────────
$settings = read_json($settingsFile, ['title' => 'SAGRA', 'subtitle' => '', 'coperto' => 0, 'importoAsporto' => 0]);
$menu     = read_json($menuFile, null);
$menuCats = ($menu && isset($menu['categories'])) ? count($menu['categories']) : 0;
$menuItems = 0;
if ($menu && isset($menu['itemsBycat']) && is_array($menu['itemsBycat'])) {
  foreach ($menu['itemsBycat'] as $a) { if (is_array($a)) $menuItems += count($a); }
}
$menuSync = ($menu && isset($menu['syncedAt'])) ? $menu['syncedAt'] : null;
$apikeySet = apikey_is_set();
$logoUrl   = logo_url();
$csrf = csrf_token();

admin_header('Impostazioni', 'settings');
?>

<?php if ($msg): ?>
  <p class="msg <?= $msgErr ? 'err' : 'ok' ?>"><?= htmlspecialchars($msg) ?></p>
<?php endif; ?>

<div class="card">
  <h2>Configurazione del sito</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="settings">
    <label for="title">Titolo</label>
    <input type="text" id="title" name="title" maxlength="80" value="<?= htmlspecialchars($settings['title']) ?>">
    <label for="subtitle">Sottotitolo (opzionale)</label>
    <input type="text" id="subtitle" name="subtitle" maxlength="60" value="<?= htmlspecialchars($settings['subtitle']) ?>">
    <label for="coperto">Importo coperto (€)</label>
    <input type="number" id="coperto" name="coperto" min="0" step="0.10" value="<?= htmlspecialchars($settings['coperto'] ?? 0) ?>">
    <small class="muted">0 = nessun coperto. Viene moltiplicato per il numero di coperti indicati e aggiunto al totale dell'ordine (non al QR).</small>
    <label for="importoAsporto">Importo asporto (€)</label>
    <input type="number" id="importoAsporto" name="importoAsporto" min="0" step="0.10" value="<?= htmlspecialchars($settings['importoAsporto'] ?? 0) ?>">
    <small class="muted">0 = nessun addebito. Viene aggiunto <strong>una sola volta per ordine</strong>, e solo se il cliente mette il flag «Per asporto» (non al QR). Negli ordini per asporto il coperto non si applica.</small>
    <button class="btn" type="submit">Salva configurazione</button>
  </form>
  <p class="muted" style="margin-top:12px">
    Titolo e sottotitolo vengono mostrati nell'intestazione del sito a tutti i visitatori.
  </p>
</div>

<div class="card">
  <h2>Logo</h2>
  <?php if ($logoUrl): ?>
    <p style="margin:0 0 4px">Logo attuale:</p>
    <p style="margin:0"><img src="../api/<?= htmlspecialchars($logoUrl) ?>" alt="Logo del sito"
         style="max-width:240px;max-height:120px;background:#fff;border:1px solid #e2e8f0;padding:10px;border-radius:10px"></p>
  <?php else: ?>
    <p class="msg" style="background:#f1f5f9;color:#475569;margin-top:0">
      Nessun logo caricato: la pagina dei preordini mostra il solo titolo.
    </p>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="logo">
    <label for="logo">Immagine del logo (PNG, JPG, WEBP o GIF · max <?= round(logo_max_bytes() / 1048576) ?> MB)</label>
    <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" required>
    <small class="muted">
      Viene salvato in <code>api/data/</code> e mostrato in cima alla pagina dei preordini,
      sopra ai dati del cliente, largo quanto il riquadro. Lo sfondo lì è bianco:
      un'immagine orizzontale rende meglio di una quadrata.
    </small>
    <button class="btn" type="submit">Carica logo</button>
  </form>

  <?php if ($logoUrl): ?>
    <form method="post" onsubmit="return confirm('Rimuovere il logo dal sito?')" style="margin-top:8px">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="logo_del">
      <button class="btn danger" type="submit">Rimuovi logo</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Menù (offline-data.json)</h2>
  <?php if ($menuCats): ?>
    <p>Menù attuale: <strong><?= $menuCats ?></strong> categorie, <strong><?= $menuItems ?></strong> pietanze.
      <?php if ($menuSync): ?><span class="muted">· export del <?= htmlspecialchars($menuSync) ?></span><?php endif; ?>
    </p>
  <?php else: ?>
    <p class="msg err">Nessun menù caricato: il sito non può mostrare gli articoli.</p>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="upload">
    <label for="menu">Carica il file <code>offline-data.json</code> esportato dal gestionale</label>
    <input type="file" id="menu" name="menu" accept=".json,application/json" required>
    <button class="btn" type="submit">Carica e salva menù</button>
  </form>

  <form method="post" onsubmit="return confirm('Rimuovere il menù attuale e bloccare gli ordini?')" style="margin-top:8px">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="clear">
    <button class="btn danger" type="submit">Rimuovi menù (blocca ordini)</button>
  </form>
</div>

<div class="card">
  <h2>Sicurezza (password pannello)</h2>
  <p class="muted">Cambia la password usata per accedere a questo pannello di amministrazione.</p>
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="password">
    <label for="cur_pass">Password attuale</label>
    <input type="password" id="cur_pass" name="cur_pass" autocomplete="current-password" required>
    <label for="new_pass">Nuova password (min <?= ADMIN_PASS_MIN ?> caratteri)</label>
    <input type="password" id="new_pass" name="new_pass" autocomplete="new-password" required>
    <label for="new_pass2">Ripeti nuova password</label>
    <input type="password" id="new_pass2" name="new_pass2" autocomplete="new-password" required>
    <button class="btn" type="submit">Cambia password</button>
  </form>
</div>

<div class="card">
  <h2>API key (upload remoto)</h2>
  <?php if ($apikeySet): ?>
    <p class="msg ok" style="margin-top:0">API key impostata. Puoi sostituirla generandone e salvandone una nuova qui sotto.</p>
  <?php else: ?>
    <p class="msg err" style="margin-top:0">API key non ancora impostata: l'upload remoto di <code>offline-data.json</code> resta disattivato finché non ne inserisci una.</p>
  <?php endif; ?>
  <p class="muted">
    Serve <strong>solo</strong> per l'upload remoto di <code>offline-data.json</code> via le API REST
    (header <code>x-api-key</code>); non c'entra con la password di questo pannello.
    Premi «Genera» per crearne una sicura, «Copia» per tenerla (ti servirà nel gestionale),
    poi «Salva» per attivarla. Viene salvata in <code>api/data/</code>: non serve modificare <code>config.php</code>.
  </p>
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="apikey">
    <label for="apikey"><?= $apikeySet ? 'Nuova API key' : 'API key' ?></label>
    <div class="row">
      <input type="text" id="apikey" name="apikey" placeholder="premi «Genera» o incolla una chiave" style="flex:1;min-width:220px;margin:0" required>
      <button class="btn alt" type="button" id="genKey">Genera</button>
      <button class="btn alt" type="button" id="copyKey">Copia</button>
    </div>
    <p class="msg ok" id="keyMsg" style="display:none"></p>
    <button class="btn" type="submit">Salva API key</button>
  </form>
</div>

<script>
(function () {
  var inp  = document.getElementById('apikey');
  var msg  = document.getElementById('keyMsg');
  function show(t) { msg.textContent = t; msg.style.display = 'block'; }

  document.getElementById('genKey').addEventListener('click', function () {
    var b = new Uint8Array(32);
    crypto.getRandomValues(b);
    inp.value = Array.prototype.map.call(b, function (x) {
      return ('0' + x.toString(16)).slice(-2);
    }).join('');
    show('Chiave generata. Copiala e premi «Salva» per attivarla.');
  });

  document.getElementById('copyKey').addEventListener('click', function () {
    if (!inp.value) { show('Genera prima una chiave.'); return; }
    inp.select();
    inp.setSelectionRange(0, inp.value.length);
    function fallback() {
      try { document.execCommand('copy'); show('Chiave copiata.'); }
      catch (e) { show('Copia manuale: selezione la chiave e premi Ctrl+C.'); }
    }
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(inp.value)
        .then(function () { show('Chiave copiata negli appunti.'); }, fallback);
    } else {
      fallback();
    }
  });
})();
</script>

<div class="card">
  <h3>Note</h3>
  <p class="muted">
    Il sito statico contatta il backend tramite <code>apiBase</code> (default <code>api</code>)
    in <code>config.json</code>; con tutto sullo stesso dominio non serve modificarlo.
    La sincronizzazione diretta dal gestionale non è disponibile qui (è in LAN): esporta il
    file dal gestionale e caricalo sopra.
  </p>
</div>

<?php admin_footer(); ?>
