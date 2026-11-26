<?php
/**
 * venezuela-producto.php
 * -------------------------
 * Página de detalle de un producto, inspirada en la estructura de
 * revechl.cl/producto/... — stats rápidas, pestañas, apps incluidas,
 * "para quién es", specs técnicas, cómo funciona y FAQ. Pedido vía
 * WhatsApp en vez de carrito.
 *
 * USO: venezuela-producto.php?slug=windows-10-pro
 */

require_once __DIR__ . '/venezuela-data.php';

ve_maintenance_lazy();
$slug = (string) ($_GET['slug'] ?? '');
$catalog = ve_catalog();
$product = $catalog[$slug] ?? null;
$rate = get_bcv_rate();
$orderable = $product && $product['pid'] && ve_db_ready() && !$product['soldout'];
$methods = [];
if (ve_db_ready()) {
    if (ve_method_enabled('binance')) $methods[] = 'binance';
    if (ve_method_enabled('pagomovil') && $rate > 0) $methods[] = 'pagomovil';
}
$err = (string) ($_GET['error'] ?? '');
$reviews = $product && $product['pid'] ? ve_own_reviews(40, (int) $product['pid']) : [];
$rStat = $product && $product['pid'] ? (ve_review_stats()[(int) $product['pid']] ?? null) : null;

// Productos relacionados: otros de la misma categoría, hasta 3
$related = [];
if ($product) {
    foreach ($catalog as $s => $p) {
        if ($s !== $slug && $p['facet_category'] === $product['facet_category']) $related[$s] = $p;
        if (count($related) >= 3) break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $product ? htmlspecialchars($product['title']) . ' | Alquimia Digital Venezuela' : 'Producto no encontrado' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php include __DIR__ . '/venezuela-styles.php'; ?>
<style>
  .breadcrumb { padding: 16px 0 0; font-size: 0.85rem; color: var(--ash); }
  .breadcrumb a { text-decoration: none; color: var(--ash); }
  .breadcrumb a:hover { color: var(--gold); }
  .breadcrumb span.sep { margin: 0 6px; opacity: 0.5; }

  .product-hero { padding: 20px 0 20px; display: grid; grid-template-columns: 0.85fr 1.15fr; gap: 40px; align-items: start; }
  .product-visual {
    background: var(--surface-tint); border: 1px solid var(--line); border-radius: var(--radius-card);
    aspect-ratio: 1/1; display: flex; align-items: center; justify-content: center; overflow: hidden; position: sticky; top: 84px;
  }
  .product-visual img { width: 100%; height: 100%; object-fit: contain; }

  .product-info .eyebrow { font-size: 0.78rem; color: var(--teal); font-weight: 600; text-transform: uppercase; margin-bottom: 8px; }
  .product-info h1 { font-family: var(--serif); font-weight: 600; font-size: clamp(1.5rem, 2.6vw, 2rem); margin: 0 0 10px; }
  .product-info .lead { color: var(--text-dim); margin: 0 0 20px; max-width: 56ch; }

  /* stats rápidas */
  .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 24px; }
  .stat-box { background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 12px 10px; text-align: center; }
  .stat-box .icon { font-size: 1.2rem; }
  .stat-box .value { font-weight: 700; font-size: 0.85rem; margin-top: 4px; }
  .stat-box .label { font-size: 0.68rem; color: var(--ash); text-transform: uppercase; }

  .variant-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
  .variant-item {
    display: flex; align-items: center; justify-content: space-between; gap: 14px;
    background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px;
  }
  .variant-name { font-weight: 600; font-size: 0.95rem; }
  .variant-price-block { display: flex; align-items: center; gap: 16px; }
  .variant-price-text { text-align: right; }
  .variant-price-usd { font-family: var(--serif); font-weight: 600; font-size: 1.1rem; }
  .variant-price-bs { display: block; font-size: 0.72rem; color: var(--ash); }
  .variant-wa-btn {
    background: #25D366; color: #fff; text-decoration: none; padding: 9px 16px; border-radius: 8px;
    font-size: 0.85rem; font-weight: 600; white-space: nowrap;
  }
  .variant-wa-btn:hover { background: #1EBE59; }

  .mini-trust { display: flex; gap: 18px; flex-wrap: wrap; font-size: 0.8rem; color: var(--text-dim); padding: 14px 0; border-top: 1px solid var(--line); margin-bottom: 8px; }
  .mini-trust span { display: flex; align-items: center; gap: 6px; }

  /* pestañas */
  .info-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--line); margin: 28px 0 20px; overflow-x: auto; }
  .info-tab-btn { background: none; border: none; font-family: var(--sans); padding: 10px 4px; margin-right: 24px; font-size: 0.9rem; color: var(--ash); cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap; }
  .info-tab-btn.active { color: var(--text); border-bottom-color: var(--gold); font-weight: 600; }
  .info-panel { display: none; color: var(--text-dim); font-size: 0.92rem; line-height: 1.7; }
  .info-panel.active { display: block; }
  .info-panel ul { padding-left: 20px; margin: 8px 0; }

  /* apps incluidas */
  .apps-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 12px; margin: 10px 0; }
  .app-chip { background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 14px 8px; text-align: center; font-size: 0.8rem; font-weight: 600; }

  /* para quién es */
  .whom-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin: 24px 0; }
  .whom-card { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 16px; }
  .whom-card h4 { font-family: var(--serif); font-weight: 600; font-size: 1rem; margin: 0 0 6px; }
  .whom-card p { font-size: 0.85rem; color: var(--text-dim); margin: 0; }

  /* cómo funciona */
  .steps-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin: 24px 0; }
  .step-box { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 18px; position: relative; }
  .step-num { font-family: var(--serif); font-size: 1.6rem; font-weight: 700; color: var(--gold); }
  .step-box h4 { font-size: 0.92rem; margin: 6px 0 4px; }
  .step-box p { font-size: 0.82rem; color: var(--text-dim); margin: 0; }

  /* FAQ */
  .faq-item { border-bottom: 1px solid var(--line); padding: 14px 0; }
  .faq-q { display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: 600; font-size: 0.92rem; }
  .faq-a { display: none; font-size: 0.88rem; color: var(--text-dim); margin-top: 10px; line-height: 1.6; }
  .faq-item.open .faq-a { display: block; }
  .faq-item.open .faq-toggle { transform: rotate(45deg); }
  .faq-toggle { transition: transform 0.15s ease; font-size: 1.1rem; color: var(--gold); }

  /* relacionados */
  .related-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }

  .not-found { padding: 60px 24px; text-align: center; }
  .not-found a { color: var(--gold); }

  @media (max-width: 800px) {
    .product-hero { grid-template-columns: 1fr; }
    .product-visual { position: static; }
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .whom-grid, .steps-row, .related-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<?php include __DIR__ . '/venezuela-header.php'; ?>

<?php if (!$product): ?>
  <div class="not-found">
    <h1>Producto no encontrado</h1>
    <p><a href="./">Volver al catálogo</a></p>
  </div>
<?php else: ?>

  <div class="wrap breadcrumb">
    <a href="./">Catálogo</a><span class="sep">/</span>
    <a href="./#catalogo"><?= htmlspecialchars($product['category']) ?></a><span class="sep">/</span>
    <span><?= htmlspecialchars($product['title']) ?></span>
  </div>

  <section class="product-hero wrap">
    <div class="product-visual">
      <?php $imgPath = product_image_path($product); ?>
      <img src="<?= htmlspecialchars($imgPath ?: $brandLogos[$product['brand_logo']]) ?>" alt="<?= htmlspecialchars($product['title']) ?>" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='<?= htmlspecialchars($brandLogos[$product['brand_logo']]) ?>';">
    </div>

    <div class="product-info">
      <div class="eyebrow"><?= htmlspecialchars($product['category']) ?></div>
      <h1><?= htmlspecialchars($product['title']) ?></h1>
      <?php if ($rStat): ?><p class="rating-line">★ <?= h(number_format($rStat['avg'], 1, ',', '')) ?> <span>· <?= (int) $rStat['n'] ?> reseña<?= $rStat['n'] === 1 ? '' : 's' ?> con compra verificada</span></p><?php endif; ?>
      <?php $lead = $product['long_desc'] !== '' ? $product['long_desc'] : ($product['desc'] !== '' ? $product['desc'] : 'Licencia original con entrega digital y soporte.'); ?>
      <p class="lead"><?= htmlspecialchars($lead) ?></p>

      <?php if (!empty($product['stats'])): ?>
        <div class="stats-row">
          <?php foreach ($product['stats'] as $st): ?>
            <div class="stat-box">
              <div class="icon"><?= $st[0] ?></div>
              <div class="value"><?= htmlspecialchars($st[2]) ?></div>
              <div class="label"><?= htmlspecialchars($st[1]) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($err !== ''): ?><div class="alert err"><?= h($err) ?></div><?php endif; ?>

      <?php if ($orderable && $methods): ?>
      <form class="buy-box" method="post" action="venezuela-pedido.php" id="buy-form">
        <h3>Haz tu pedido</h3>
        <input type="hidden" name="slug" value="<?= h($slug) ?>">
        <div class="hp"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="variant-list" style="margin-bottom:14px">
          <?php $firstOk = true; foreach ($product['variants'] as $v):
            $bsPrice = bs($v['price'], $rate); $off = !$v['avail']; ?>
            <label class="variant-item selectable<?= $off ? ' is-off' : '' ?>">
              <span class="variant-left"><input type="radio" name="variant_id" value="<?= (int) $v['id'] ?>" required data-usd="<?= h($v['price']) ?>" data-bs="<?= h($bsPrice ?: '') ?>" <?= $off ? 'disabled' : ($firstOk ? 'checked' : '') ?>><span class="variant-name"><?= htmlspecialchars($v['label']) ?></span></span>
              <span class="variant-price-text">
                <?php if ($off): ?><span class="variant-price-usd">Agotado</span>
                <?php else: ?><span class="variant-price-usd"><?= usd($v['price']) ?></span><?php if ($bsPrice): ?><span class="variant-price-bs"><?= htmlspecialchars($bsPrice) ?></span><?php endif; ?><?php endif; ?>
              </span>
            </label>
          <?php if (!$off) $firstOk = false; endforeach; ?>
        </div>
        <div class="field"><label for="b-name">Tu nombre</label><input id="b-name" type="text" name="name" required maxlength="120" autocomplete="name"></div>
        <div class="field"><label for="b-email">Correo electrónico</label><input id="b-email" type="email" name="email" required maxlength="190" autocomplete="email"><small>Ahí te enviamos los datos de pago y tu licencia.</small></div>
        <div class="field"><label for="b-phone">WhatsApp</label><input id="b-phone" type="tel" name="phone" required maxlength="40" autocomplete="tel" placeholder="0412 5859613"><small>Para coordinar la entrega y el soporte.</small></div>
        <div class="pay-methods">
          <?php foreach ($methods as $i => $m): ?>
            <label class="pay-method"><input type="radio" name="method" value="<?= h($m) ?>" <?= $i === 0 ? 'checked' : '' ?>>
              <span><b><?= $m === 'binance' ? 'Binance Pay (USDT)' : 'PagoMóvil (Bs)' ?></b><span><?= $m === 'binance' ? 'Pagas el monto en dólares' : 'A tasa BCV del día' ?></span></span></label>
          <?php endforeach; ?>
        </div>
        <div class="buy-total"><span>Total a pagar</span><span style="text-align:right"><span class="t-usd" id="t-usd"></span><br><span class="t-bs" id="t-bs"></span></span></div>
        <button class="btn-buy" type="submit">Hacer pedido</button>
        <p style="font-size:.76rem;color:var(--ash);text-align:center;margin:10px 0 0">Un producto por pedido. Tienes <?= (int) ve_pay_hours() ?> horas para pagar; si no hay pago ni comprobante, se cancela solo.</p>
        <p style="text-align:center;margin:10px 0 0"><a class="btn-sec" target="_blank" href="<?= htmlspecialchars(wa_link('Hola, tengo una duda sobre: ' . $product['title'])) ?>">💬 ¿Dudas? Escríbenos por WhatsApp</a></p>
      </form>
      <script>
        (function () {
          var f = document.getElementById('buy-form'), tu = document.getElementById('t-usd'), tb = document.getElementById('t-bs');
          function upd() {
            var r = f.querySelector('input[name=variant_id]:checked'); if (!r) { tu.textContent = ''; tb.textContent = ''; return; }
            var m = f.querySelector('input[name=method]:checked');
            tu.textContent = '$' + (+r.dataset.usd).toFixed(2) + ' USD';
            tb.textContent = (m && m.value === 'pagomovil' && r.dataset.bs) ? r.dataset.bs + ' (tasa BCV del momento)' : '';
          }
          f.addEventListener('change', upd); upd();
        })();
      </script>
      <?php else: ?>
      <div class="variant-list">
        <?php foreach ($product['variants'] as $v):
          $bsPrice = bs($v['price'], $rate);
          $waText = "Hola, quiero pedir: {$product['title']} — {$v['label']} ({$v['price']} USD" . ($bsPrice ? " / {$bsPrice}" : "") . ")";
        ?>
          <div class="variant-item">
            <span class="variant-name"><?= htmlspecialchars($v['label']) ?></span>
            <div class="variant-price-block">
              <div class="variant-price-text">
                <span class="variant-price-usd"><?= usd($v['price']) ?></span>
                <?php if ($bsPrice): ?><span class="variant-price-bs"><?= htmlspecialchars($bsPrice) ?></span><?php endif; ?>
              </div>
              <?php if ($product['soldout']): ?><span class="variant-price-bs">Agotado</span>
              <?php else: ?><a class="variant-wa-btn" href="<?= htmlspecialchars(wa_link($waText)) ?>" target="_blank">💬 Pedir</a><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="mini-trust">
        <span>🛡️ Garantía de funcionamiento</span>
        <span>✅ Licencia original</span>
        <span>📱 Soporte por WhatsApp</span>
      </div>

      <?php $dbMode = $product['pid'] && ve_db_ready(); ?>
      <div class="info-tabs">
        <button class="info-tab-btn active" data-tab="desc">Descripción</button>
        <?php if (!empty($product['apps'])): ?><button class="info-tab-btn" data-tab="apps">Apps incluidas</button><?php endif; ?>
        <?php if (!empty($product['requirements'])): ?><button class="info-tab-btn" data-tab="req">Requisitos</button><?php endif; ?>
        <button class="info-tab-btn" data-tab="proceso">Cómo funciona</button>
        <?php if ($dbMode): ?><button class="info-tab-btn" data-tab="resenas">Reseñas<?= $rStat ? ' (' . (int) $rStat['n'] . ')' : '' ?></button><?php endif; ?>
      </div>

      <div class="info-panel active" id="tab-desc">
        <?php if ($product['long_desc'] !== ''): ?><p><?= htmlspecialchars($product['long_desc']) ?></p>
        <?php elseif (trim($product['desc_html'] ?? '') !== ''): ?><?= $product['desc_html'] ?>
        <?php else: ?><p>Licencia original con entrega digital y soporte por WhatsApp.</p><?php endif; ?>

        <div class="whom-grid">
          <div class="whom-card"><h4>Estudiantes</h4><p>Ideal para trabajos, informes y presentaciones a un precio accesible.</p></div>
          <div class="whom-card"><h4>Profesionales</h4><p>Herramientas actualizadas para documentos, análisis y proyectos de trabajo.</p></div>
          <div class="whom-card"><h4>Uso doméstico</h4><p>Perfecto para el hogar, con instalación en varios equipos según la variante.</p></div>
        </div>
      </div>

      <?php if (!empty($product['apps'])): ?>
        <div class="info-panel" id="tab-apps">
          <div class="apps-grid">
            <?php foreach ($product['apps'] as $app): ?><div class="app-chip"><?= htmlspecialchars($app) ?></div><?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($product['requirements'])): ?>
      <div class="info-panel" id="tab-req">
        <ul><?php foreach ($product['requirements'] as $r): ?><li><?= htmlspecialchars($r) ?></li><?php endforeach; ?></ul>
      </div>
      <?php endif; ?>

      <div class="info-panel" id="tab-proceso">
        <div class="steps-row">
          <?php if ($dbMode): ?>
          <div class="step-box"><div class="step-num">1</div><h4>Haz tu pedido</h4><p>Elige la variante, completa tus datos y el medio de pago (Binance o PagoMóvil).</p></div>
          <div class="step-box"><div class="step-num">2</div><h4>Paga y sube tu comprobante</h4><p>Ves los datos y el monto exacto, pagas y subes la captura. Nos llega el aviso al instante.</p></div>
          <div class="step-box"><div class="step-num">3</div><h4>Recibes tu producto</h4><p>Confirmamos tu pago y te enviamos la clave/cuenta con las instrucciones paso a paso.</p></div>
          <?php else: ?>
          <div class="step-box"><div class="step-num">1</div><h4>Escríbenos por WhatsApp</h4><p>Indícanos la variante que quieres y te confirmamos el precio y forma de pago.</p></div>
          <div class="step-box"><div class="step-num">2</div><h4>Coordinamos el pago</h4><p>Te compartimos los datos para el pago y confirmas cuando esté listo.</p></div>
          <div class="step-box"><div class="step-num">3</div><h4>Recibes tu producto</h4><p>Te enviamos la clave/cuenta junto con las instrucciones de instalación paso a paso.</p></div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($dbMode): ?>
      <div class="info-panel" id="tab-resenas">
        <?php if (!$reviews): ?><p>Aún no hay reseñas de este producto. Los clientes que compran reciben un enlace para dejar la suya, con la insignia de compra verificada.</p><?php endif; ?>
        <?php foreach ($reviews as $rv): ?>
          <div class="pr-review">
            <div class="top"><b><?= h($rv['author']) ?></b><span class="stars-s"><?= str_repeat('★', max(1, min(5, $rv['rating']))) ?></span><?= ve_verified_badge() ?><span style="color:var(--ash)"><?= h($rv['date_fmt']) ?></span></div>
            <p><?= h($rv['content']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <h3 style="font-family:var(--serif); font-weight:600; font-size:1.1rem; margin: 28px 0 8px;">Preguntas frecuentes</h3>
      <div class="faq-list">
        <div class="faq-item">
          <div class="faq-q" onclick="this.parentElement.classList.toggle('open')">¿Cómo recibo mi compra? <span class="faq-toggle">+</span></div>
          <div class="faq-a"><?= $dbMode ? 'Cuando confirmamos tu pago te enviamos la clave o cuenta junto con las instrucciones de instalación y activación paso a paso. Puedes ver el estado de tu pedido en la página de pago que te llega por correo.' : 'Una vez confirmado el pago por WhatsApp, te enviamos la clave o cuenta junto con las instrucciones de instalación y activación paso a paso.' ?></div>
        </div>
        <div class="faq-item">
          <div class="faq-q" onclick="this.parentElement.classList.toggle('open')">¿Es una licencia original? <span class="faq-toggle">+</span></div>
          <div class="faq-a">Sí, todas nuestras licencias son originales, con garantía de funcionamiento.</div>
        </div>
        <div class="faq-item">
          <div class="faq-q" onclick="this.parentElement.classList.toggle('open')">¿Qué pasa si tengo un problema después de instalar? <span class="faq-toggle">+</span></div>
          <div class="faq-a">Escríbenos por WhatsApp y te ayudamos a resolverlo — el soporte está incluido en tu compra.</div>
        </div>
        <div class="faq-item">
          <div class="faq-q" onclick="this.parentElement.classList.toggle('open')">¿Puedo pagar en bolívares? <span class="faq-toggle">+</span></div>
          <div class="faq-a"><?= $dbMode ? 'Sí: elige PagoMóvil al hacer tu pedido. El monto en Bs. se calcula con la tasa oficial BCV del momento y queda fijo para tu pedido.' : 'Sí, coordinamos el método de pago directamente por WhatsApp — el precio en Bs. que ves es referencial, según la tasa oficial BCV del día.' ?></div>
        </div>
        <?php if ($dbMode): ?>
        <div class="faq-item">
          <div class="faq-q" onclick="this.parentElement.classList.toggle('open')">¿Qué pasa si no pago a tiempo? <span class="faq-toggle">+</span></div>
          <div class="faq-a">Tienes <?= (int) ve_pay_hours() ?> horas para pagar. Te enviamos un recordatorio y, si no recibimos pago ni comprobante, el pedido se cancela automáticamente. Si ya pagaste, sube tu comprobante y no se cancela.</div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if (!empty($related)): ?>
    <section class="section on-paper wrap" style="margin-top: 40px; border-radius: 16px;">
      <div class="section-head">
        <h2 class="section-title">También te puede interesar</h2>
      </div>
      <div class="related-grid">
        <?php foreach ($related as $rp) ve_render_card($rp, $rate, false); ?>
      </div>
    </section>
  <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/venezuela-footer.php'; ?>

<script>
  document.querySelectorAll('.info-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.info-tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.info-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
  });
</script>

</body>
</html>
