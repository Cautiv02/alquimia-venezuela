<?php
require_once __DIR__ . '/_auth.php';
$pdo = ve_pdo();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$o = ve_order_by_id($id);
if (!$o) { http_response_code(404); exit('Pedido no encontrado.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = (string) ($_POST['action'] ?? '');
    $now = "updated_at = NOW()";
    if ($a === 'paid' && in_array($o['status'], ['pendiente', 'anulado'], true)) {
        $pdo->prepare("UPDATE ve_orders SET status = 'pagado', paid_at = NOW(), cancel_reason = NULL, $now WHERE id = :i")->execute([':i' => $id]);
        $o = ve_order_by_id($id);
        flash_set('Pedido marcado como pagado.' . (ve_mail_paid($o) ? ' Se avisó al cliente por correo.' : ' (No se pudo enviar el correo al cliente.)'));
    } elseif ($a === 'deliver' && in_array($o['status'], ['pendiente', 'pagado'], true)) {
        $pdo->prepare("UPDATE ve_orders SET status = 'entregado', delivered_at = NOW(), paid_at = COALESCE(paid_at, NOW()), $now WHERE id = :i")->execute([':i' => $id]);
        $o = ve_order_by_id($id);
        $ok = ve_mail_delivered($o, (string) ($_POST['message'] ?? ''));
        if ($ok) $pdo->prepare('UPDATE ve_orders SET review_requested_at = NOW() WHERE id = :i')->execute([':i' => $id]);
        flash_set('Pedido marcado como entregado.' . ($ok ? ' El correo al cliente incluye el enlace para dejar su reseña verificada.' : ' (No se pudo enviar el correo al cliente.)'));
    } elseif ($a === 'cancel' && $o['status'] === 'pendiente') {
        ve_cancel_order($id, trim((string) ($_POST['reason'] ?? '')) ?: 'cancelado por el administrador');
        flash_set('Pedido cancelado y avisado al cliente.');
    } elseif ($a === 'remind' && $o['status'] === 'pendiente') {
        flash_set(ve_send_reminder($id) ? 'Recordatorio enviado por correo.' : 'No se pudo enviar el correo (revisa el SMTP en Ajustes).', !ve_mail_ready());
    } elseif ($a === 'review' && in_array($o['status'], ['pagado', 'entregado'], true)) {
        flash_set(ve_request_review($id) ? 'Solicitud de reseña enviada.' : 'No se pudo enviar el correo.', false);
    }
    header('Location: pedido.php?id=' . $id); exit;
}

$proofs = $pdo->prepare('SELECT * FROM ve_proofs WHERE order_id = :i ORDER BY id DESC'); $proofs->execute([':i' => $id]); $proofs = $proofs->fetchAll();
$rv = $pdo->prepare('SELECT * FROM ve_reviews WHERE order_id = :i'); $rv->execute([':i' => $id]); $rv = $rv->fetch();
$pdo->prepare('UPDATE ve_notifications SET is_read = 1 WHERE order_id = :i')->execute([':i' => $id]);
$ph = ve_phone_intl($o['phone']);
$st = $o['status'];
admin_header('Pedido ' . $o['order_number']); flash_show(); ?>
<p><a href="index.php" class="muted" style="text-decoration:none">← Pedidos</a></p>
<div class="grid2">
  <div class="box"><h2>Pedido <?= h($o['order_number']) ?> <span class="pill <?= h($st) ?>"><?= h(ucfirst($st)) ?></span></h2>
    <p><b><?= h($o['product_title']) ?></b> — <?= h($o['variant_title']) ?></p>
    <p>Monto: <b><?= h(ve_order_total_text($o)) ?></b><br><span class="tag">Método: <?= h(ve_method_label($o['method'])) ?> · Creado: <?= h(ve_fmt_dt($o['created_at'])) ?><?php if ($st === 'pendiente'): ?> · Vence: <?= h(date('d/m/Y H:i', ve_order_expires_at($o))) ?><?php endif; ?></span></p>
    <?php if ($o['paid_at']): ?><p class="tag">Pagado: <?= h(ve_fmt_dt($o['paid_at'])) ?></p><?php endif; ?>
    <?php if ($o['delivered_at']): ?><p class="tag">Entregado: <?= h(ve_fmt_dt($o['delivered_at'])) ?></p><?php endif; ?>
    <?php if ($o['cancel_reason']): ?><p class="tag">Motivo de cancelación: <?= h($o['cancel_reason']) ?></p><?php endif; ?>
    <?php if ($o['reminder_sent_at']): ?><p class="tag">Recordatorio enviado: <?= h(ve_fmt_dt($o['reminder_sent_at'])) ?></p><?php endif; ?>
    <?php if ($o['review_requested_at']): ?><p class="tag">Reseña solicitada: <?= h(ve_fmt_dt($o['review_requested_at'])) ?></p><?php endif; ?>
    <p class="tag"><a href="<?= h(ve_pay_url($o)) ?>" target="_blank">Página de pago del cliente</a></p></div>
  <div class="box"><h2>Cliente</h2>
    <p><b><?= h($o['name']) ?></b><br><a href="mailto:<?= h($o['email']) ?>"><?= h($o['email']) ?></a><br><?= h($o['phone']) ?>
    <?php if ($ph): ?> · <a target="_blank" href="https://wa.me/<?= h($ph) ?>?text=<?= rawurlencode('Hola ' . $o['name'] . ', te escribo por tu pedido ' . $o['order_number'] . ' (' . $o['product_title'] . ').') ?>">💬 WhatsApp</a><?php endif; ?></p>
    <?php if ($rv): ?><p class="tag">Reseña: <?= str_repeat('★', (int) $rv['rating']) ?> — <?= $rv['published'] ? 'publicada' : 'pendiente' ?> (<a href="resenas.php">ver</a>)</p><?php endif; ?></div>
</div>

<div class="box"><h2>Comprobantes (<?= count($proofs) ?>)</h2>
  <?php if (!$proofs): ?><p class="muted" style="margin:0">El cliente aún no ha subido comprobante.</p><?php endif; ?>
  <?php foreach ($proofs as $p): ?>
    <div style="border-top:1px solid var(--line);padding:12px 0">
      <p class="tag" style="margin:0 0 6px"><?= h(ve_fmt_dt($p['created_at'])) ?> · Referencia: <b><?= h($p['reference'] ?: '—') ?></b> · Pagó: <?= h($p['sender'] ?: '—') ?><?= $p['note'] ? ' · Nota: ' . h($p['note']) : '' ?></p>
      <?php if ($p['file_name']): ?>
        <?php if ($p['file_mime'] === 'pdf'): ?><a class="btn-a ghost" target="_blank" href="comprobante.php?id=<?= (int) $p['id'] ?>">Abrir PDF</a>
        <?php else: ?><a target="_blank" href="comprobante.php?id=<?= (int) $p['id'] ?>"><img class="proof-img" src="comprobante.php?id=<?= (int) $p['id'] ?>" alt="Comprobante"></a><?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="box actions"><h2>Acciones</h2>
  <?php if (in_array($st, ['pendiente', 'anulado'], true)): ?>
    <form method="post" onsubmit="return confirm('¿Confirmas que el pago está verificado?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="paid"><button class="btn-a green">✅ Marcar como pagado</button></form>
  <?php endif; ?>
  <?php if (in_array($st, ['pendiente', 'pagado'], true)): ?>
    <form method="post" style="display:block;margin-top:14px" onsubmit="return confirm('¿Marcar como entregado y avisar al cliente?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="deliver">
      <div class="field"><label>Mensaje para el cliente en el correo de entrega (opcional)</label><textarea name="message" rows="3" placeholder="Ej.: Te envié la clave por WhatsApp. Cualquier duda, escríbeme."></textarea></div>
      <button class="btn-a">📦 Marcar como entregado</button></form>
  <?php endif; ?>
  <?php if ($st === 'pendiente'): ?>
    <div style="margin-top:14px">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="remind"><button class="btn-a ghost">✉️ Enviar recordatorio por correo</button></form>
      <?php if ($u = ve_whatsapp_reminder_url($o)): ?> <a class="btn-a ghost" target="_blank" href="<?= h($u) ?>">💬 Recordatorio por WhatsApp</a><?php endif; ?></div>
    <form method="post" style="display:block;margin-top:14px" onsubmit="return confirm('¿Cancelar este pedido?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="cancel">
      <input type="text" name="reason" placeholder="Motivo (opcional)" style="padding:8px 11px;border:1px solid var(--line);border-radius:8px;width:260px"> <button class="btn-a red">Cancelar pedido</button></form>
  <?php endif; ?>
  <?php if (in_array($st, ['pagado', 'entregado'], true)): ?>
    <form method="post" style="display:block;margin-top:14px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="review"><button class="btn-a ghost">⭐ Pedir reseña (compra verificada)</button></form>
  <?php endif; ?>
</div>
<?php admin_footer();
