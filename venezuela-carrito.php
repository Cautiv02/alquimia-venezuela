<?php
/**
 * venezuela-carrito.php — carrito de compras. Varios productos (hasta 10 unidades de cada uno) en un solo pedido y un solo pago.
 * El carrito guarda solo la opción y la cantidad (cookie); precios y stock se leen SIEMPRE de la base.
 * Acciones (POST): add (desde la ficha del producto), update / remove / clear. El pedido se crea en venezuela-pedido.php.
 */
require_once __DIR__ . '/venezuela-data.php';
header('Cache-Control: no-store');
ve_maintenance_lazy();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string) ($_POST['action'] ?? '');
    $cart = ve_cart_raw();
    if ($a === 'add') {
        $slug = (string) ($_POST['slug'] ?? ''); $vid = (int) ($_POST['variant_id'] ?? 0); $qty = max(1, min(10, (int) ($_POST['qty'] ?? 1)));
        $p = ve_catalog()[$slug] ?? null; $v = null;
        if ($p && $p['pid'] && !$p['soldout']) foreach ($p['variants'] as $x) if ((int) $x['id'] === $vid) { $v = $x; break; }
        $back = 'venezuela-producto.php?slug=' . urlencode($slug) . '&error=';
        if (!$v) { header('Location: ' . $back . urlencode('Elige una opción del producto.') . '#buy-form'); exit; }
        if (!$v['avail']) { header('Location: ' . $back . urlencode('Esa opción está agotada.') . '#buy-form'); exit; }
        $max = $v['stock'] !== null ? min(10, (int) $v['stock']) : 10;
        $cart[$vid] = min($max, ($cart[$vid] ?? 0) + $qty);
        ve_cart_save($cart);
        header('Location: venezuela-carrito.php?agregado=1'); exit;
    }
    if ($a === 'update') {
        if (!empty($_POST['remove'])) unset($cart[(int) $_POST['remove']]);
        foreach ((array) ($_POST['qty'] ?? []) as $vid => $q) {
            $vid = (int) $vid; if (!isset($cart[$vid])) continue;
            $q = (int) $q; if ($q < 1) unset($cart[$vid]); else $cart[$vid] = min(10, $q);
        }
        ve_cart_save($cart);
    } elseif ($a === 'clear') { ve_cart_save([]); }
    header('Location: venezuela-carrito.php'); exit;
}

$ready = ve_db_ready();
$rate = get_bcv_rate();
$c = $ready ? ve_cart_lines() : ['lines' => [], 'total' => 0.0];
$lines = $c['lines']; $total = $c['total'];
$methods = [];
if ($ready) { if (ve_method_enabled('binance')) $methods[] = 'binance'; if (ve_method_enabled('pagomovil') && $rate > 0) $methods[] = 'pagomovil'; }
// Si cambió el stock o el precio y alguna línea se ajustó, se guarda el carrito ya depurado
if ($ready && count($lines) !== count(ve_cart_raw())) ve_cart_save(array_column($lines, 'qty', 'vid'));
$err = (string) ($_GET['error'] ?? '');
$bsTotal = bs($total, $rate);
$nUnits = array_sum(array_column($lines, 'qty'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Mi carrito | Alquimia Digital Venezuela</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php include __DIR__ . '/venezuela-styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/venezuela-header.php'; ?>
<div class="wrap cart-page">
  <h1 class="cart-title">Mi carrito</h1>
<?php if (!$lines): ?>
  <div class="panel" style="max-width:640px">
    <p class="lead" style="margin:0 0 14px"><?= $ready ? 'Tu carrito está vacío. Elige un producto del catálogo y agrégalo aquí.' : 'La tienda está en mantenimiento. Escríbenos por WhatsApp y te atendemos.' ?></p>
    <a class="btn-sec" href="./#catalogo">Ver el catálogo</a>
  </div>
<?php else: ?>
  <?php if (isset($_GET['agregado'])): ?><div class="alert ok">✅ Producto agregado al carrito.</div><?php endif; ?>
  <?php if ($err !== ''): ?><div class="alert err"><?= h($err) ?></div><?php endif; ?>
  <div class="cart-grid">
    <form method="post" class="panel cart-lines" action="venezuela-carrito.php">
      <input type="hidden" name="action" value="update">
      <?php foreach ($lines as $l): ?>
        <div class="cart-line">
          <a href="venezuela-producto.php?slug=<?= urlencode($l['slug']) ?>" class="cart-thumb"><img src="<?= h($l['image'] ?: $brandLogos[$l['brand_logo']]) ?>" alt="" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='<?= h($brandLogos[$l['brand_logo']]) ?>';"></a>
          <div class="cart-info">
            <a class="cart-name" href="venezuela-producto.php?slug=<?= urlencode($l['slug']) ?>"><?= h($l['title']) ?></a>
            <span class="cart-opt"><?= h($l['label']) ?> · <?= h('$' . number_format($l['unit'], 2)) ?> c/u</span>
            <div class="cart-qty">
              <label class="sr" for="q<?= (int) $l['vid'] ?>">Cantidad</label>
              <select id="q<?= (int) $l['vid'] ?>" name="qty[<?= (int) $l['vid'] ?>]" onchange="this.form.submit()">
                <?php for ($i = 1; $i <= $l['max']; $i++): ?><option value="<?= $i ?>" <?= $i === $l['qty'] ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?>
              </select>
              <button type="submit" name="remove" value="<?= (int) $l['vid'] ?>" class="cart-del">Quitar</button>
            </div>
          </div>
          <div class="cart-sub"><b>$<?= h(number_format($l['sub'], 2)) ?></b><?php if ($bs = bs($l['sub'], $rate)): ?><span><?= h($bs) ?></span><?php endif; ?></div>
        </div>
      <?php endforeach; ?>
      <noscript><button class="btn-sec" type="submit">Actualizar cantidades</button></noscript>
      <p style="margin:14px 0 0;display:flex;gap:14px;flex-wrap:wrap;align-items:center"><a class="btn-sec" href="./#catalogo">← Seguir comprando</a>
        <button type="submit" form="clear-form" class="cart-del">Vaciar carrito</button></p>
    </form>
    <form id="clear-form" method="post" action="venezuela-carrito.php" onsubmit="return confirm('¿Vaciar el carrito?')"><input type="hidden" name="action" value="clear"></form>

    <?php if ($methods): ?>
    <form method="post" action="venezuela-pedido.php" class="panel buy-box cart-checkout" id="checkout-form">
      <h3>Tus datos y el pago</h3>
      <div class="hp"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <div class="field"><label for="b-name">Tu nombre</label><input id="b-name" type="text" name="name" required maxlength="120" autocomplete="name"></div>
      <div class="field"><label for="b-email">Correo electrónico</label><input id="b-email" type="email" name="email" required maxlength="190" autocomplete="email"><small>Ahí te enviamos los datos de pago y tus licencias.</small></div>
      <div class="field"><label for="b-phone">WhatsApp</label><input id="b-phone" type="tel" name="phone" required maxlength="40" autocomplete="tel" placeholder="0412 5859613"><small>Para coordinar la entrega y el soporte.</small></div>
      <div class="pay-methods">
        <?php foreach ($methods as $i => $m): ?>
          <label class="pay-method"><input type="radio" name="method" value="<?= h($m) ?>" <?= $i === 0 ? 'checked' : '' ?>>
            <span><b><?= $m === 'binance' ? 'Binance Pay (USDT)' : 'PagoMóvil (Bs)' ?></b><span><?= $m === 'binance' ? 'Pagas el monto en dólares' : 'A tasa BCV del día' ?></span></span></label>
        <?php endforeach; ?>
      </div>
      <div class="buy-total"><span>Total (<?= (int) $nUnits ?> <?= $nUnits === 1 ? 'licencia' : 'licencias' ?>)</span>
        <span style="text-align:right"><span class="t-usd">$<?= h(number_format($total, 2)) ?> USD</span><?php if ($bsTotal): ?><br><span class="t-bs" id="t-bs"><?= h($bsTotal) ?> (tasa BCV del momento)</span><?php endif; ?></span></div>
      <button class="btn-buy" type="submit">Hacer pedido</button>
      <p style="font-size:.76rem;color:var(--ash);text-align:center;margin:10px 0 0">Un solo pago por todo el carrito. Tienes <?= (int) ve_pay_hours() ?> horas para pagar; si no hay pago ni comprobante, se cancela solo.</p>
    </form>
    <?php else: ?>
    <div class="panel cart-checkout"><h3 style="font-family:var(--serif);margin:0 0 8px">Pago</h3>
      <p class="lead" style="margin:0 0 12px">Por ahora los pagos en línea no están disponibles. Escríbenos por WhatsApp y te atendemos de inmediato.</p>
      <a class="btn-sec" target="_blank" href="<?= h(wa_link('Hola, quiero comprar: ' . implode('; ', array_map(function ($l) { return $l['title'] . ' (' . $l['label'] . ') x' . $l['qty']; }, $lines)))) ?>">💬 Pedir por WhatsApp</a></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>
<?php include __DIR__ . '/venezuela-footer.php'; ?>
</body>
</html>
