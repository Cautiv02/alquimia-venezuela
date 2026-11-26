<?php
/**
 * venezuela-pago.php?c=VE-XXXX-XXXX — el cliente ve los datos de pago y el monto, sube su comprobante
 * y sigue el estado de su pedido. Al subir el comprobante, llega el aviso al panel (y por correo).
 */
require_once __DIR__ . '/venezuela-data.php';
header('Cache-Control: no-store');

ve_maintenance_lazy();
$ready = ve_db_ready();
$order = $ready ? ve_order_by_code((string) ($_GET['c'] ?? $_POST['c'] ?? '')) : null;
$done = false; $err = '';

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['website']) && $order['status'] === 'pendiente') {
    $pdo = ve_pdo();
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM ve_proofs WHERE order_id = :o'); $cnt->execute([':o' => $order['id']]);
    $ref  = trim(mb_substr((string) ($_POST['reference'] ?? ''), 0, 150));
    $sender = trim(mb_substr((string) ($_POST['sender'] ?? ''), 0, 150));
    $note = trim(mb_substr((string) ($_POST['note'] ?? ''), 0, 1000));
    $fileName = null; $ext = null;
    if ((int) $cnt->fetchColumn() >= 6) {
        $err = 'Ya recibimos varios comprobantes de este pedido. Escríbenos por WhatsApp (ventas y pagos) para revisarlos.';
    } elseif (!empty($_FILES['proof']['tmp_name']) && is_uploaded_file($_FILES['proof']['tmp_name'])) {
        $f = $_FILES['proof'];
        $head = (string) @file_get_contents($f['tmp_name'], false, null, 0, 5);
        $info = @getimagesize($f['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? ($head === '%PDF-' ? 'pdf' : '');
        if (!$ext || $f['size'] > 6 * 1024 * 1024) { $err = 'Adjunta una imagen (JPG, PNG, WEBP) o un PDF de hasta 6 MB.'; }
        else {
            $dir = ve_proofs_dir();
            $fileName = 'c' . (int) $order['id'] . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $fileName)) { $err = 'No pudimos guardar el archivo. Inténtalo de nuevo o envíalo por WhatsApp.'; $fileName = null; }
        }
    } elseif (!empty($_FILES['proof']['error']) && $_FILES['proof']['error'] !== UPLOAD_ERR_NO_FILE) {
        $err = 'No pudimos recibir el archivo (puede ser muy pesado). Máximo 6 MB.';
    }
    if ($err === '' && $fileName === null && $ref === '') $err = 'Adjunta la captura del pago o escribe al menos el número de referencia.';
    if ($err === '') {
        $pdo->prepare('INSERT INTO ve_proofs (order_id, file_name, file_mime, sender, reference, note, created_at) VALUES (:o,:f,:m,:s,:r,:n,NOW())')
            ->execute([':o' => $order['id'], ':f' => $fileName, ':m' => $ext, ':s' => $sender ?: null, ':r' => $ref ?: null, ':n' => $note ?: null]);
        ve_notify('comprobante', 'Comprobante recibido del pedido ' . $order['order_number'] . ' — ' . ve_items_text(ve_order_items($order)) . ' — ' . ve_order_total_text($order) . ' — ' . $order['name'] . ($ref ? ' — ref. ' . $ref : ''), (int) $order['id']);
        $done = true;
    }
}
if ($order) $order = ve_order_by_id((int) $order['id']);
if ($order) ve_track('view', null, 'pago');
$hasProof = $order ? ve_order_has_proof((int) $order['id']) : false;
$rate = $order ? (float) $order['rate'] : 0;
$expires = $order ? ve_order_expires_at($order) : 0;
$qr = trim(ve_setting('binance_qr'));
if ($qr !== '' && !preg_match('#^https?://#', $qr)) $qr = ve_site_url() . '/' . ltrim($qr, '/');
$ph = $order ? ve_phone_intl($order['phone']) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Pago de tu pedido | Alquimia Digital Venezuela</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php include __DIR__ . '/venezuela-styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/venezuela-header.php'; ?>
<div class="page-narrow">
<?php if (!$order): ?>
  <div class="panel"><h1>No encontramos tu pedido</h1><p class="lead">Revisa el enlace que te enviamos por correo o escríbenos por WhatsApp.</p>
    <p><a class="btn-sec" href="./">Ver el catálogo</a></p></div>
<?php else:
  $st = $order['status']; $isBin = $order['method'] === 'binance'; ?>
  <div class="steps-mini">
    <span class="on">1 · Pedido</span>
    <span class="<?= $st === 'pendiente' && !$hasProof ? 'on' : '' ?>">2 · Pago</span>
    <span class="<?= ($st === 'pendiente' && $hasProof) || $st === 'pagado' ? 'on' : '' ?>">3 · Verificación</span>
    <span class="<?= $st === 'entregado' ? 'on' : '' ?>">4 · Entrega</span>
  </div>
  <div class="panel">
    <?php $items = ve_order_items($order); ?>
    <h1><?= count($items) > 1 ? 'Tu pedido' : h($items[0]['product_title']) ?></h1>
    <p class="lead">Pedido <b><?= h($order['order_number']) ?></b> · <?= h(ve_method_label($order['method'])) ?></p>
    <table class="pay-items">
      <?php foreach ($items as $it): ?>
        <tr><td><?= h($it['product_title']) ?><?php if ($it['variant_title'] !== ''): ?><small><?= h($it['variant_title']) ?><?= (int) $it['qty'] > 1 ? ' · ' . (int) $it['qty'] . ' × $' . h(number_format((float) $it['unit_usd'], 2)) : '' ?></small><?php elseif ((int) $it['qty'] > 1): ?><small><?= (int) $it['qty'] ?> × $<?= h(number_format((float) $it['unit_usd'], 2)) ?></small><?php endif; ?></td>
          <td>$<?= h(number_format((float) $it['unit_usd'] * (int) $it['qty'], 2)) ?></td></tr>
      <?php endforeach; ?>
    </table>

    <?php if ($st === 'entregado'): ?>
      <div class="alert ok"><b>¡Pedido entregado!</b> Revisa tu WhatsApp y tu correo (<?= h($order['email']) ?>): ahí está tu licencia con las instrucciones. Si necesitas soporte para instalarla o activarla, <?= h(ve_support_phrase()) ?>.</div>
      <p><a class="btn-sec" href="<?= h(ve_review_link((int) $order['id'])) ?>">⭐ Dejar mi opinión (compra verificada)</a></p>
    <?php elseif ($st === 'pagado'): ?>
      <div class="alert ok"><b>Pago confirmado ✅.</b> Estamos preparando tu entrega; te la enviamos por WhatsApp y correo en cuanto esté lista.</div>
    <?php elseif ($st === 'anulado'): ?>
      <div class="alert err"><b>Este pedido fue cancelado</b><?= $order['cancel_reason'] ? ' (' . h($order['cancel_reason']) . ')' : '' ?>.</div>
      <p>Si ya pagaste, escríbenos por WhatsApp con tu comprobante y lo reactivamos. Si no, puedes hacer un pedido nuevo.</p>
      <p><a class="btn-sec" href="./">Ver el catálogo</a>
         <a class="btn-sec" target="_blank" href="<?= h(wa_link('Hola, mi pedido ' . $order['order_number'] . ' fue cancelado y quiero revisarlo')) ?>">💬 Escribir por WhatsApp</a></p>
    <?php else: /* pendiente */ ?>
      <?php if ($done): ?><div class="alert ok"><b>¡Comprobante recibido! 🎉</b> Lo revisaremos y te avisamos por correo y WhatsApp en cuanto confirmemos tu pago.</div>
      <?php elseif ($hasProof): ?><div class="alert warn"><b>Ya recibimos tu comprobante.</b> Estamos verificando tu pago; el pedido no se cancelará mientras lo revisamos. Si quieres, puedes enviar otro comprobante abajo.</div><?php endif; ?>

      <div class="pay-amount"><b id="amount"><?= $isBin ? h(number_format((float) $order['price_usd'], 2)) . ' USDT' : 'Bs. ' . h(number_format((float) $order['amount_bs'], 0, ',', '.')) ?></b>
        <span><?= $isBin ? 'Monto exacto a enviar' : h(number_format((float) $order['price_usd'], 2)) . ' USD × Bs. ' . h(number_format($rate, 2, ',', '.')) . '<br>tasa BCV al hacer el pedido' ?></span></div>

      <?php if ($isBin && $qr !== ''): ?><p style="text-align:center"><img src="<?= h($qr) ?>" alt="Código QR de Binance Pay" width="190" height="190" style="margin:0 auto;background:#fff;padding:8px;border-radius:12px;border:1px solid var(--line)"></p><?php endif; ?>
      <div class="pay-data"><?= h(ve_setting($isBin ? 'binance_details' : 'pagomovil_details')) ?></div>
      <p style="font-size:.88rem;color:var(--text-dim)"><?= nl2br(h(ve_setting($isBin ? 'binance_note' : 'pagomovil_note'))) ?></p>
      <p style="font-size:.88rem">Escribe en la nota/concepto tu número de pedido: <b><?= h($isBin ? preg_replace('/\D+/', '', $order['order_number']) : $order['order_number']) ?></b></p>
      <?php if (!$hasProof): ?><div class="alert warn">⏳ Tienes hasta las <b><?= h(date('H:i', $expires)) ?></b> (hora de Venezuela) del <?= h(date('d/m', $expires)) ?> para pagar: <b id="left"></b>. Si no recibimos el pago ni tu comprobante, el pedido se cancela solo.</div><?php endif; ?>
      <?php if ($err): ?><div class="alert err"><?= h($err) ?></div><?php endif; ?>

      <h3 style="font-family:var(--serif);font-weight:600;margin:20px 0 10px">Sube tu comprobante</h3>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="c" value="<?= h($order['code']) ?>">
        <div class="hp"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="field"><label for="proof">Captura o comprobante (imagen o PDF, máx. 6 MB)</label><input id="proof" type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf"></div>
        <div class="field"><label for="reference"><?= $isBin ? 'ID de la transacción (Order ID de Binance)' : 'Número de referencia del PagoMóvil' ?></label><input id="reference" type="text" name="reference" maxlength="150" value="<?= h($_POST['reference'] ?? '') ?>"></div>
        <div class="field"><label for="sender"><?= $isBin ? 'Tu usuario o correo de Binance' : 'Nombre o teléfono de quien pagó' ?> (opcional)</label><input id="sender" type="text" name="sender" maxlength="150" value="<?= h($_POST['sender'] ?? '') ?>"></div>
        <div class="field"><label for="note">Comentario (opcional)</label><textarea id="note" name="note" rows="2" maxlength="1000"><?= h($_POST['note'] ?? '') ?></textarea></div>
        <button class="btn-buy" type="submit">Enviar comprobante</button>
      </form>
      <p style="text-align:center;margin:14px 0 0"><a class="btn-sec" target="_blank" href="<?= h(wa_link('Hola, tengo una consulta sobre el pago de mi pedido ' . $order['order_number'])) ?>">💬 Consultar sobre mi pedido por WhatsApp (ventas y pagos)</a></p>
      <script>
        (function () {
          var end = <?= (int) $expires ?> * 1000 - (<?= time() ?> * 1000 - Date.now()), el = document.getElementById('left');
          if (!el) return;
          function t() { var s = Math.max(0, Math.floor((end - Date.now()) / 1000)), h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60);
            el.textContent = s === 0 ? 'plazo vencido' : 'te quedan ' + h + ' h ' + (m < 10 ? '0' : '') + m + ' min'; }
          t(); setInterval(t, 30000);
        })();
      </script>
    <?php endif; ?>
  </div>
  <p style="text-align:center;font-size:.8rem;color:var(--ash)">Guarda esta página: es tu enlace para ver el estado del pedido. También lo enviamos a <?= h($order['email']) ?>.</p>
<?php endif; ?>
</div>
<?php include __DIR__ . '/venezuela-footer.php'; ?>
</body>
</html>
