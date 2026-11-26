<?php
require_once __DIR__ . '/_auth.php';
$pdo = ve_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $a = (string) ($_POST['action'] ?? 'save');
    if ($a === 'refresh_rate') {
        @unlink(BCV_CACHE_FILE); ve_settings_save(['rate_alert' => '']); $i = get_bcv_rate_info();
        flash_set($i['source'] === 'api' ? 'Tasa actualizada desde internet: Bs. ' . number_format($i['rate'], 2, ',', '.') : 'No se pudo leer la tasa de internet; se usa la ' . ($i['source'] === 'manual' ? 'manual' : 'última guardada') . ' (Bs. ' . number_format($i['rate'], 2, ',', '.') . ').', $i['source'] !== 'api');
    } elseif ($a === 'test_mail') {
        $ok = ve_send_mail(preg_split('/[\s,;]+/', VE_NOTIFY_EMAIL)[0] ?: '', 'Prueba de correo — Alquimia Digital Venezuela', ve_mail_html('<p>Si lees esto, el correo de la tienda de Venezuela funciona. ✅</p>'));
        flash_set($ok ? 'Correo de prueba enviado a ' . VE_NOTIFY_EMAIL : 'No se pudo enviar el correo de prueba. Revisa los datos SMTP (host, usuario y clave) en config-ve.php.', !$ok);
    } else {
        $v = [
            'rate_mode' => ($_POST['rate_mode'] ?? '') === 'manual' ? 'manual' : 'auto',
            'rate_manual' => trim(preg_replace('/[^0-9,.]/', '', (string) ($_POST['rate_manual'] ?? ''))),
            'pay_hours' => (string) max(1, min(72, (int) ($_POST['pay_hours'] ?? 4))),
            'support_email' => trim((string) ($_POST['support_email'] ?? '')), 'support_url' => preg_match('#^https?://#i', trim((string) ($_POST['support_url'] ?? ''))) ? trim((string) $_POST['support_url']) : '',
            'clp_per_usd' => trim(preg_replace('/[^0-9,.]/', '', (string) ($_POST['clp_per_usd'] ?? ''))),
            'binance_enabled' => !empty($_POST['binance_enabled']) ? '1' : '0', 'binance_details' => trim((string) ($_POST['binance_details'] ?? '')), 'binance_note' => trim((string) ($_POST['binance_note'] ?? '')),
            'pagomovil_enabled' => !empty($_POST['pagomovil_enabled']) ? '1' : '0', 'pagomovil_details' => trim((string) ($_POST['pagomovil_details'] ?? '')), 'pagomovil_note' => trim((string) ($_POST['pagomovil_note'] ?? '')),
        ];
        if (!empty($_POST['binance_qr_clear'])) $v['binance_qr'] = '';
        if (!empty($_FILES['binance_qr']['tmp_name']) && is_uploaded_file($_FILES['binance_qr']['tmp_name'])) {
            $info = @getimagesize($_FILES['binance_qr']['tmp_name']);
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? '';
            if ($ext && $_FILES['binance_qr']['size'] <= 2 * 1024 * 1024 && move_uploaded_file($_FILES['binance_qr']['tmp_name'], __DIR__ . '/../imagenes/binance-qr.' . $ext)) $v['binance_qr'] = 'imagenes/binance-qr.' . $ext . '?v=' . time();
            else { flash_set('El QR debe ser una imagen JPG, PNG o WEBP de hasta 2 MB.', true); header('Location: ajustes.php'); exit; }
        }
        ve_settings_save($v);
        $w = [];
        if ($v['binance_enabled'] === '1' && $v['binance_details'] === '') $w[] = 'Binance está activado pero sin datos: no se ofrecerá hasta que los escribas.';
        if ($v['pagomovil_enabled'] === '1' && $v['pagomovil_details'] === '') $w[] = 'PagoMóvil está activado pero sin datos: no se ofrecerá hasta que los escribas.';
        if ($v['rate_mode'] === 'manual' && (float) str_replace(',', '.', $v['rate_manual']) <= 0) $w[] = 'Modo manual sin tasa: se usará la automática.';
        flash_set('Ajustes guardados. ' . implode(' ', $w), (bool) $w);
    }
    header('Location: ajustes.php'); exit;
}
$s = ve_settings_all(true); $info = get_bcv_rate_info();
$cron = ve_site_url() . '/ve-cron.php?k=' . ve_cron_key();
admin_header('Ajustes'); flash_show(); ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save">
<div class="box"><h2>Tasa BCV</h2>
  <p>Ahora: <b>Bs. <?= h(number_format($info['rate'], 2, ',', '.')) ?></b> por USD — fuente: <b><?= h(['api' => 'internet (BCV)', 'cache' => 'internet (BCV, ya consultada hoy)', 'ultima' => 'última tasa guardada (internet falló)', 'manual' => 'tasa manual', 'ninguna' => 'sin dato'][$info['source']]) ?></b><?= $info['at'] ? ' · ' . h($info['at']) : '' ?>.</p>
  <?php if ($s['rate_alert'] !== ''): ?><div class="flash err"><?= h($s['rate_alert']) ?></div><?php endif; ?>
  <div class="grid2"><div class="field"><label>Modo</label><select name="rate_mode"><option value="auto" <?= $s['rate_mode'] === 'auto' ? 'selected' : '' ?>>Automático desde internet (con respaldo manual si falla)</option><option value="manual" <?= $s['rate_mode'] === 'manual' ? 'selected' : '' ?>>Siempre manual</option></select></div>
  <div class="field"><label>Tasa manual de respaldo (Bs. por 1 USD)</label><input type="text" name="rate_manual" value="<?= h($s['rate_manual']) ?>" placeholder="ej. 920,50"></div></div>
  <p class="tag">El monto en Bs. de cada pedido queda fijo con la tasa del momento en que el cliente lo hizo. Una tasa que difiere más de 60 % de la anterior se ignora por seguridad.</p>
</div>
<div class="box"><h2>Medios de pago</h2>
  <div class="grid2"><div>
    <label style="font-weight:600"><input type="checkbox" name="binance_enabled" value="1" <?= $s['binance_enabled'] === '1' ? 'checked' : '' ?>> Binance Pay (USDT)</label>
    <div class="field"><label>Datos que verá el cliente (ID de Binance Pay, usuario…)</label><textarea name="binance_details" rows="4"><?= h($s['binance_details']) ?></textarea></div>
    <div class="field"><label>Instrucciones</label><textarea name="binance_note" rows="3"><?= h($s['binance_note']) ?></textarea></div>
    <div class="field"><label>Código QR de Binance Pay (imagen)</label><input type="file" name="binance_qr" accept="image/*">
      <?php if (trim($s['binance_qr']) !== ''): ?><small>QR actual cargado. <label><input type="checkbox" name="binance_qr_clear" value="1"> Quitarlo</label></small><?php endif; ?></div>
  </div><div>
    <label style="font-weight:600"><input type="checkbox" name="pagomovil_enabled" value="1" <?= $s['pagomovil_enabled'] === '1' ? 'checked' : '' ?>> PagoMóvil (Bs)</label>
    <div class="field"><label>Datos que verá el cliente (banco, teléfono, cédula/RIF, titular)</label><textarea name="pagomovil_details" rows="4" placeholder="Banco: …&#10;Teléfono: …&#10;Cédula/RIF: …"><?= h($s['pagomovil_details']) ?></textarea></div>
    <div class="field"><label>Instrucciones</label><textarea name="pagomovil_note" rows="3"><?= h($s['pagomovil_note']) ?></textarea></div>
  </div></div>
</div>
<div class="box"><h2>Soporte técnico</h2>
  <div class="grid2"><div class="field"><label>Correo de soporte</label><input type="text" name="support_email" value="<?= h($s['support_email']) ?>" placeholder="soporte@tudominio.com"><small>Aparece en el pie de la tienda, en los correos y en las respuestas de ayuda. El soporte técnico no se atiende por WhatsApp.</small></div>
  <div class="field"><label>Plataforma de tickets (opcional, cuando exista)</label><input type="text" name="support_url" value="<?= h($s['support_url']) ?>" placeholder="https://soporte.tudominio.com"><small>Si lo llenas, los textos dirán «abre un ticket en…» y el pie enlazará ahí en lugar del correo.</small></div></div>
</div>
<div class="box"><h2>Plazo de pago y precios</h2>
  <div class="grid2"><div class="field"><label>Horas para pagar antes de la cancelación automática</label><input type="number" name="pay_hours" min="1" max="72" value="<?= h($s['pay_hours']) ?>"><small>El recordatorio automático sale a la mitad del plazo. No se cancelan pedidos con comprobante enviado.</small></div>
  <div class="field"><label>CLP por 1 USD (opcional)</label><input type="text" name="clp_per_usd" value="<?= h($s['clp_per_usd']) ?>" placeholder="ej. 950"><small>Solo sirve para sugerirte un precio en USD a partir del precio en CLP de la tienda de Chile al editar productos.</small></div></div>
</div>
<button class="btn-a">Guardar ajustes</button></form>
<div class="grid2" style="margin-top:22px">
<div class="box"><h2>Tasa y correo</h2>
  <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="refresh_rate"><button class="btn-a ghost">🔄 Actualizar tasa ahora</button></form>
  <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="test_mail"><button class="btn-a ghost">✉️ Enviar correo de prueba</button></form>
  <p class="tag">Correo: <?= ve_mail_ready() ? 'configurado (' . h(VE_SMTP_HOST) . ')' : '<b>sin configurar</b>' ?> · Avisos a: <?= h(VE_NOTIFY_EMAIL ?: '—') ?></p></div>
<div class="box"><h2>Tarea programada (opcional)</h2>
  <p class="tag" style="margin-top:0">Sin tarea programada, los recordatorios y cancelaciones se procesan cuando alguien visita la tienda o abres el panel. Para que sean puntuales, en cPanel → Tareas Cron (cada 10 min) usa:</p>
  <input type="text" readonly value="curl -s '<?= h($cron) ?>' >/dev/null" onclick="this.select()" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:8px;font-size:.78rem"></div>
</div>
<?php admin_footer();
