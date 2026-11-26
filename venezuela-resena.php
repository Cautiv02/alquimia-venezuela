<?php
/**
 * venezuela-resena.php?o=ID&k=TOKEN — el cliente deja su reseña desde el enlace del correo.
 * Solo quien tiene el enlace de un pedido pagado/entregado puede reseñar (por eso lleva "compra verificada").
 * Una reseña por producto y pedido; queda pendiente hasta que la apruebes en el panel.
 */
require_once __DIR__ . '/venezuela-data.php';
$ready = ve_db_ready();
$id = (int) ($_GET['o'] ?? $_POST['o'] ?? 0);
$key = (string) ($_GET['k'] ?? $_POST['k'] ?? '');
$order = ($ready && $id && hash_equals(ve_token('resena', $id), $key)) ? ve_order_by_id($id) : null;
$can = $order && in_array($order['status'], ['pagado', 'entregado'], true);
// Productos distintos del pedido; se reseña uno por vez (una reseña por producto y pedido)
$prods = [];
if ($can) foreach (ve_order_items($order) as $it) $prods[(int) $it['product_id']] = ve_clean_text($it['product_title']);
$pid = (int) ($_GET['p'] ?? $_POST['p'] ?? 0);
if (!isset($prods[$pid])) $pid = count($prods) === 1 ? (int) array_key_first($prods) : 0;
$reviewed = [];
if ($can) { $q = ve_pdo()->prepare('SELECT product_id FROM ve_reviews WHERE order_id = :o'); $q->execute([':o' => $id]); foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $x) $reviewed[(int) $x] = true; }
$exists = $pid && isset($reviewed[$pid]); $done = false; $err = '';
$pending = array_diff_key($prods, $reviewed);
if ($can && $pid && !$exists && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['website'])) {
    $rating = (int) ($_POST['rating'] ?? 0);
    $author = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($_POST['author'] ?? '')), 0, 60));
    $content = trim(mb_substr((string) ($_POST['content'] ?? ''), 0, 1500));
    if ($rating < 1 || $rating > 5) $err = 'Elige de 1 a 5 estrellas.';
    elseif (mb_strlen($content) < 8) $err = 'Cuéntanos un poco más sobre tu experiencia (mínimo unas palabras).';
    elseif ($author === '') $err = 'Escribe tu nombre (puede ser solo el primero).';
    else {
        try {
            ve_pdo()->prepare('INSERT INTO ve_reviews (order_id, product_id, product_title, author, rating, content, verified, published, created_at) VALUES (:o,:p,:t,:a,:r,:c,1,0,NOW())')
                ->execute([':o' => $id, ':p' => $pid, ':t' => $prods[$pid], ':a' => $author, ':r' => $rating, ':c' => $content]);
            ve_notify('resena', 'Nueva reseña de ' . $author . ' (' . $rating . '★) para ' . $prods[$pid] . ': pendiente de aprobar.', $id, false);
            $done = true; $reviewed[$pid] = true; $pending = array_diff_key($prods, $reviewed);
        } catch (Throwable $e) { $err = 'No pudimos guardar tu reseña. Inténtalo de nuevo.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow">
<title>Tu opinión | Alquimia Digital Venezuela</title>
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php include __DIR__ . '/venezuela-styles.php'; ?>
</head><body>
<?php include __DIR__ . '/venezuela-header.php'; ?>
<div class="page-narrow"><div class="panel">
<?php if (!$order || !$can): ?>
  <h1>Enlace no válido</h1><p class="lead">Este enlace no es válido o tu pedido aún no está pagado. Si crees que es un error, escríbenos por WhatsApp.</p>
<?php elseif (!$pid): /* varios productos: elegir cuál reseñar */ ?>
  <h1>¿Qué tal tu compra?</h1>
  <?php if (!$pending): ?><p class="lead">Ya recibimos tus reseñas de este pedido. ¡Gracias! 💛</p><p><a class="btn-sec" href="./">Ver el catálogo</a></p>
  <?php else: ?><p class="lead">Elige el producto que quieres calificar (pedido <?= h($order['order_number']) ?>):</p>
    <div class="rv-pick"><?php foreach ($prods as $pp => $tt): ?>
      <?php if (isset($reviewed[$pp])): ?><div style="padding:12px 14px;margin-bottom:8px;color:var(--ash)"><?= h($tt) ?> <small>· ya reseñado ✓</small></div>
      <?php else: ?><a href="<?= h('venezuela-resena.php?o=' . $id . '&k=' . urlencode($key) . '&p=' . $pp) ?>"><?= h($tt) ?> <small>· dejar reseña →</small></a><?php endif; ?>
    <?php endforeach; ?></div><?php endif; ?>
<?php elseif ($exists || $done): ?>
  <h1>¡Gracias por tu opinión! 💛</h1><p class="lead"><?= $done ? 'La revisaremos y la publicaremos pronto con la insignia de compra verificada.' : 'Ya recibimos tu reseña de este producto. ¡Gracias!' ?></p>
  <p>
  <?php if ($pending): ?><a class="btn-sec" href="<?= h('venezuela-resena.php?o=' . $id . '&k=' . urlencode($key)) ?>">Reseñar otro producto del pedido</a> <?php endif; ?>
  <a class="btn-sec" href="./">Ver el catálogo</a></p>
<?php else: ?>
  <h1>¿Qué tal tu compra?</h1>
  <p class="lead"><?= h($prods[$pid]) ?> · pedido <?= h($order['order_number']) ?> <?= ve_verified_badge() ?></p>
  <?php if ($err): ?><div class="alert err"><?= h($err) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="o" value="<?= (int) $id ?>"><input type="hidden" name="p" value="<?= (int) $pid ?>"><input type="hidden" name="k" value="<?= h($key) ?>">
    <div class="hp"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
    <div class="field"><label>Tu calificación</label>
      <div class="stars-in"><?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" id="s<?= $i ?>" name="rating" value="<?= $i ?>" <?= (int) ($_POST['rating'] ?? 5) === $i ? 'checked' : '' ?>><label for="s<?= $i ?>" title="<?= $i ?> estrellas">★</label><?php endfor; ?></div></div>
    <div class="field"><label for="author">Tu nombre</label><input id="author" type="text" name="author" maxlength="60" required value="<?= h($_POST['author'] ?? explode(' ', $order['name'])[0]) ?>"><small>Se publica tal como lo escribas.</small></div>
    <div class="field"><label for="content">Tu experiencia</label><textarea id="content" name="content" rows="5" maxlength="1500" required placeholder="¿Cómo fue la compra, la entrega y la activación?"><?= h($_POST['content'] ?? '') ?></textarea></div>
    <button class="btn-buy" type="submit">Enviar mi reseña</button>
  </form>
<?php endif; ?>
</div></div>
<?php include __DIR__ . '/venezuela-footer.php'; ?>
</body></html>
