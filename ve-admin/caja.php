<?php
/** Caja informativa bajo la foto del producto: contenido general para todos los productos. */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_caja.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'reset') { ve_settings_save(['info_box' => '']); flash_set('Se restauró el texto de ejemplo.'); }
    else { ve_settings_save(['info_box' => json_encode(ve_box_from_post((array) ($_POST['box'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]); flash_set('Guardado. La caja ya se ve así en todos los productos que no tengan una propia.'); }
    header('Location: caja.php'); exit;
}
$g = json_decode(ve_setting('info_box'), true);
$box = is_array($g) ? ve_box_clean($g) : ve_box_default();
admin_header('Caja informativa'); flash_show(); ?>
<p class="muted" style="margin:0 0 16px">Es la caja que aparece <b>bajo la foto</b> de cada producto: dos botones, una fila de guía, secciones que se despliegan y una franja de confianza. Lo que escribas aquí vale para <b>todos los productos</b>; si un producto necesita algo distinto, se personaliza en su propia ficha (Productos).
  <?= $g ? '' : 'Ahora mismo se muestra un texto de <b>ejemplo</b>: edítalo con tu información real (sobre todo la garantía).' ?></p>
<form method="post" class="box"><?= csrf_field() ?><input type="hidden" name="action" value="save">
  <?php caja_fields($box, 'box', 'cj-main'); ?>
  <p><button class="btn-a" type="submit">Guardar</button> <button class="btn-a ghost" type="submit" name="action" value="reset" onclick="return confirm('¿Volver al texto de ejemplo?')">Restaurar ejemplo</button></p>
</form>
<?php admin_footer();
