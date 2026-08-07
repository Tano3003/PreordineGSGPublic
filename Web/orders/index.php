<?php
// Pagina di amministrazione: visualizzazione e gestione degli ordini ricevuti.
require __DIR__ . '/../api/_admin.php';
admin_gate('Ordini');

$file = data_path('orders.json');
$msg = ''; $msgErr = false;

// ── Azioni POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $action = isset($_POST['action']) ? $_POST['action'] : '';
  $orders = read_json($file, []);

  if ($action === 'delete') {
    $id = isset($_POST['id']) ? $_POST['id'] : '';
    $orders = array_values(array_filter($orders, function ($o) use ($id) {
      return (isset($o['id']) ? $o['id'] : '') !== $id;
    }));
    write_json_atomic($file, $orders);
    $msg = 'Ordine cancellato.';
  } elseif ($action === 'deleteall') {
    $n = count($orders);
    write_json_atomic($file, []);
    $msg = "Cancellati tutti gli ordini ($n).";
  }
}

// ── Lettura + filtri ─────────────────────────────────────────
$orders = read_json($file, []);
$total  = count($orders);

$q    = isset($_GET['q'])    ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort']    : 'recent';

if ($q !== '') {
  $needle = mb_strtolower($q);
  $orders = array_values(array_filter($orders, function ($o) use ($needle) {
    return strpos(mb_strtolower((string)($o['name'] ?? '')), $needle) !== false
        || strpos(mb_strtolower((string)($o['table'] ?? '')), $needle) !== false;
  }));
}

usort($orders, function ($a, $b) use ($sort) {
  switch ($sort) {
    case 'oldest': return ($a['number'] ?? 0) <=> ($b['number'] ?? 0);
    case 'name':   return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    case 'table':  return (intval($a['table'] ?? 0) <=> intval($b['table'] ?? 0)) ?: (($b['number'] ?? 0) <=> ($a['number'] ?? 0));
    default:       return ($b['number'] ?? 0) <=> ($a['number'] ?? 0);  // recent
  }
});

// Ordine selezionato (dettaglio + QR)
$selId = isset($_GET['id']) ? $_GET['id'] : '';
$sel = null;
if ($selId !== '') {
  foreach ($orders as $o) { if (($o['id'] ?? '') === $selId) { $sel = $o; break; } }
}

function fmt_eur($n) { return number_format((float)$n, 2, ',', '.') . ' €'; }
function fmt_dt($iso) { $t = strtotime((string)$iso); return $t ? date('d/m/Y H:i', $t) : '—'; }

$csrf = csrf_token();
admin_header('Ordini', 'orders');
?>

<?php if ($msg): ?>
  <p class="msg <?= $msgErr ? 'err' : 'ok' ?>"><?= htmlspecialchars($msg) ?></p>
<?php endif; ?>

<?php if ($sel): ?>
  <div class="card">
    <div class="row" style="justify-content:space-between">
      <h2 style="margin:0">Ordine #<?= htmlspecialchars($sel['number'] ?? '?') ?></h2>
      <a class="btn alt" href="?<?= http_build_query(['q' => $q, 'sort' => $sort]) ?>">← Torna all'elenco</a>
    </div>
    <p class="muted">
      <?php if (!empty($sel['name'])): ?><strong><?= htmlspecialchars($sel['name']) ?></strong> · <?php endif; ?>
      <?php if (!empty($sel['table'])): ?>Tavolo <?= htmlspecialchars($sel['table']) ?> · <?php endif; ?>
      <?php if (!empty($sel['covers'])): ?><?= htmlspecialchars($sel['covers']) ?> coperti · <?php endif; ?>
      <?php if (!empty($sel['perAsporto'])): ?><strong>PER ASPORTO</strong> · <?php endif; ?>
      <?= fmt_dt($sel['createdAt'] ?? '') ?>
    </p>
    <?php if (!empty($sel['note'])): ?>
      <p class="muted">Note: <strong><?= htmlspecialchars($sel['note']) ?></strong></p>
    <?php endif; ?>

    <div class="row" style="align-items:flex-start;gap:24px">
      <div class="qrbox" id="qrbox"><p class="muted">Generazione QR…</p></div>
      <div style="flex:1;min-width:220px">
        <table>
          <tbody>
          <?php foreach (($sel['items'] ?? []) as $it): ?>
            <tr>
              <td><?= htmlspecialchars($it['descrizione'] ?? '') ?></td>
              <td class="num">× <?= htmlspecialchars($it['qta'] ?? 0) ?></td>
              <td class="num"><?= fmt_eur(($it['prezzo'] ?? 0) * ($it['qta'] ?? 0)) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php /* Per asporto non si addebita il coperto. */
                $cop_u = empty($sel['perAsporto']) ? (float)($sel['coperto'] ?? 0) : 0;
                $cop_n = (int)($sel['covers'] ?? 0); if ($cop_u > 0 && $cop_n > 0): ?>
            <tr><td>Coperti</td><td class="num">× <?= $cop_n ?></td><td class="num"><?= fmt_eur($cop_u * $cop_n) ?></td></tr>
          <?php endif; ?>
          <?php /* Asporto: una sola volta per ordine, solo se il flag c'è. */
                $asp = !empty($sel['perAsporto']) ? (float)($sel['importoAsporto'] ?? 0) : 0;
                if ($asp > 0): ?>
            <tr><td>Asporto</td><td class="num">× 1</td><td class="num"><?= fmt_eur($asp) ?></td></tr>
          <?php endif; ?>
          <tr><th>Totale</th><th class="num"><?= htmlspecialchars($sel['totalQty'] ?? 0) ?></th><th class="num"><?= fmt_eur($sel['total'] ?? 0) ?></th></tr>
          </tbody>
        </table>
        <form method="post" onsubmit="return confirm('Cancellare questo ordine?')">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= htmlspecialchars($sel['id']) ?>">
          <button class="btn danger" type="submit">Cancella ordine</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <form method="get" class="toolbar">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cerca per nome o tavolo…">
    <select name="sort" onchange="this.form.submit()">
      <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Più recenti</option>
      <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Meno recenti</option>
      <option value="name"   <?= $sort === 'name'   ? 'selected' : '' ?>>Nome (A–Z)</option>
      <option value="table"  <?= $sort === 'table'  ? 'selected' : '' ?>>Tavolo</option>
    </select>
    <button class="btn" type="submit">Cerca</button>
  </form>

  <p class="muted"><?= count($orders) ?> ordini<?= $q !== '' ? ' (su ' . $total . ')' : '' ?></p>

  <?php if (!$orders): ?>
    <p class="muted"><?= $total ? 'Nessun ordine corrisponde alla ricerca.' : 'Nessun ordine ricevuto.' ?></p>
  <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Nome</th><th>Tavolo</th><th>Quando</th><th class="num">Totale</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><strong><?= htmlspecialchars($o['number'] ?? '?') ?></strong></td>
          <td><?= htmlspecialchars($o['name'] ?? '—') ?></td>
          <td><?= htmlspecialchars($o['table'] ?? '—') ?></td>
          <td class="muted"><?= fmt_dt($o['createdAt'] ?? '') ?></td>
          <td class="num"><?= fmt_eur($o['total'] ?? 0) ?></td>
          <td class="num"><a href="?<?= http_build_query(['q' => $q, 'sort' => $sort, 'id' => $o['id'] ?? '']) ?>">Dettaglio / QR</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post" onsubmit="return confirm('Cancellare TUTTI gli ordini? Operazione irreversibile.')" style="margin-top:12px">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="deleteall">
      <button class="btn danger" type="submit">Cancella tutti gli ordini</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($sel): ?>
<script src="../assets/qrcode.js"></script>
<script>
  (function () {
    var payload = <?= json_encode($sel['qr'] ?? '') ?>;
    var box = document.getElementById('qrbox');
    if (!payload || typeof SagraQR === 'undefined') {
      box.innerHTML = '<p class="muted">QR non disponibile per questo ordine.</p>';
      return;
    }
    SagraQR.toString(payload, { type: 'svg', margin: 2, errorCorrectionLevel: 'L',
                                color: { dark: '#0f172a', light: '#ffffff' } })
      .then(function (svg) { box.innerHTML = svg; })
      .catch(function ()    { box.innerHTML = '<p class="muted">Errore nella generazione del QR.</p>'; });
  })();
</script>
<?php endif; ?>

<?php admin_footer(); ?>
