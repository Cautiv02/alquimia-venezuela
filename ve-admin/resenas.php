<?php
require_once __DIR__ . '/_auth.php';
$pdo = ve_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $id = (int) ($_POST['id'] ?? 0); $a = (string) ($_POST['action'] ?? '');
    if ($a === 'publish') $pdo->prepare('UPDATE ve_reviews SET published = 1 WHERE id = :i')->execute([':i' => $id]);
    elseif ($a === 'hide') $pdo->prepare('UPDATE ve_reviews SET published = 0 WHERE id = :i')->execute([':i' => $id]);
    elseif ($a === 'delete') $pdo->prepare('DELETE FROM ve_reviews WHERE id = :i')->execute([':i' => $id]);
    flash_set('Listo.'); header('Location: resenas.php'); exit;
}
$rows = $pdo->query('SELECT r.*, o.order_number FROM ve_reviews r LEFT JOIN ve_orders o ON o.id = r.order_id ORDER BY r.published ASC, r.id DESC LIMIT 200')->fetchAll();
admin_header('Reseñas'); flash_show(); ?>
<div class="box"><h2>Reseñas de clientes (compra verificada)</h2>
<p class="tag" style="margin-top:0">Llegan por el enlace del correo de entrega. Quedan pendientes hasta que las apruebes. Las de Trustoo (tienda de Chile) se muestran aparte, solas.</p>
<table class="t"><tr><th>Cliente</th><th>Producto</th><th>Reseña</th><th>Estado</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr class="<?= $r['published'] ? '' : 'row-hl' ?>">
<td><?= h($r['author']) ?><br><span class="tag"><?= h(ve_fmt_dt($r['created_at'])) ?> · <a href="pedido.php?id=<?= (int) $r['order_id'] ?>"><?= h($r['order_number']) ?></a></span></td>
<td><?= h(ve_clean_text($r['product_title'])) ?></td><td><span style="color:var(--gold)"><?= str_repeat('★', (int) $r['rating']) ?></span><br><?= h($r['content']) ?></td>
<td><span class="pill <?= $r['published'] ? 'entregado' : 'pendiente' ?>"><?= $r['published'] ? 'Publicada' : 'Pendiente' ?></span></td>
<td class="actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="<?= $r['published'] ? 'hide' : 'publish' ?>"><button class="btn-a <?= $r['published'] ? 'ghost' : 'green' ?>"><?= $r['published'] ? 'Ocultar' : 'Publicar' ?></button></form>
<form method="post" onsubmit="return confirm('¿Borrar esta reseña?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn-a red">Borrar</button></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="muted" style="text-align:center;padding:26px">Aún no hay reseñas propias.</td></tr><?php endif; ?></table></div>
<?php admin_footer();
