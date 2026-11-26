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
if ($product && $product['pid']) ve_track('view', (int) $product['pid'], 'producto');
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
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php include __DIR__ . '/venezuela-styles.php'; ?>
<style>
  .breadcrumb { padding: 16px 0 0; font-size: 0.85rem; color: var(--ash); }
  .breadcrumb a { text-decoration: none; color: var(--ash); }
  .breadcrumb a:hover { color: var(--gold); }
  .breadcrumb span.sep { margin: 0 6px; opacity: 0.5; }

  .product-hero { padding: 20px 0 20px; display: grid; grid-template-columns: 0.85fr 1.15fr; gap: 40px; align-items: start; }
  .product-side { position: static; }
  .product-visual {
    background: var(--surface-tint); border: 1px solid var(--line); border-radius: var(--radius-card);
    aspect-ratio: 1/1; display: flex; align-items: center; justify-content: center; overflow: hidden;
  }
  .ibox { margin-top: 14px; background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius-card); padding: 16px; box-shadow: 0 6px 22px rgba(30,40,50,0.06); }
  .ibox-top { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
  .ibox-btn { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 10px; border-radius: 12px; font-size: 0.88rem; font-weight: 700; text-decoration: none; border: 1px solid; font-family: var(--sans); cursor: pointer; }
  .ibox-btn.b1 { color: var(--teal); background: rgba(42,125,116,0.07); border-color: rgba(42,125,116,0.3); }
  .ibox-btn.b2 { color: #1c7a43; background: #eaf7ef; border-color: #b9e3c9; }
  .ibox-guide { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-radius: 10px; background: var(--surface-tint); text-decoration: none; color: var(--text); font-size: 0.88rem; margin-bottom: 14px; }
  .ibox-guide b { font-weight: 700; } .ibox-guide .gs { color: var(--text-dim); } .ibox-guide .gt { flex: 1; } .ibox-guide svg, .ibox-guide .ga { color: var(--teal); }
  .ibox-acc { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: #fff; }
  .ibox-acc details { border-top: 1px solid var(--line); } .ibox-acc details:first-child { border-top: 0; }
  .ibox-acc summary { display: flex; align-items: center; gap: 12px; padding: 13px 14px; cursor: pointer; list-style: none; font-size: 0.9rem; font-weight: 700; }
  .ibox-acc summary::-webkit-details-marker { display: none; }
  .ibox-acc summary .si { color: var(--ash); display: flex; } .ibox-acc summary .st { flex: 1; } .ibox-acc summary .sa { color: var(--ash); transition: transform .15s; }
  .ibox-acc details[open] summary .sa { transform: rotate(90deg); color: var(--teal); }
  .ibox-acc .ab { padding: 0 14px 14px 46px; font-size: 0.86rem; color: var(--text-dim); line-height: 1.6; }
  .ibox-acc .ab p { margin: 0 0 6px; }
  .ibox-trust { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--line); }
  .ibox-trust div { display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center; font-size: 0.72rem; font-weight: 600; color: var(--text-dim); } .ibox-trust svg { color: var(--teal); }
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
  .rich p { margin: 0 0 12px; } .rich ul, .rich ol { margin: 0 0 12px; padding-left: 22px; } .rich li { margin: 0 0 4px; } .rich h3, .rich h4 { font-family: var(--serif); font-weight: 600; margin: 18px 0 8px; } .rich a { color: var(--gold); } .rich blockquote { margin: 0 0 12px; padding: 4px 14px; border-left: 3px solid var(--gold); color: var(--text-dim); }
  .related-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }

  .not-found { padding: 60px 24px; text-align: center; }
  .not-found a { color: var(--gold); }

  @media (max-width: 800px) {
    .product-hero { grid-template-columns: 1fr; }
    .product-side { position: static; }
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
    <div class="product-side">
    <div class="product-visual">
      <?php $imgPath = product_image_path($product); ?>
      <img src="<?= htmlspecialchars($imgPath ?: $brandLogos[$product['brand_logo']]) ?>" alt="<?= htmlspecialchars($product['title']) ?>" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='<?= htmlspecialchars($brandLogos[$product['brand_logo']]) ?>';">
    </div>
    <?php $bx = ve_box_for($product); ?>
      <div class="ibox">
        <?php if ($bx['btn1'] !== '' || $bx['btn2'] !== ''): ?><div class="ibox-top">
          <?php if ($bx['btn1'] !== ''): ?><a class="ibox-btn b1" href="#proceso" data-goto="proceso"><?= ve_box_icon('check', 16) ?><?= h($bx['btn1']) ?></a><?php endif; ?>
          <?php if ($bx['btn2'] !== ''): ?><a class="ibox-btn b2" target="_blank" rel="noopener" href="<?= h(wa_link('Hola, quiero consultar sobre: ' . $product['title'])) ?>"><?= ve_box_icon('chat', 16) ?><?= h($bx['btn2']) ?></a><?php endif; ?>
        </div><?php endif; ?>
        <?php if ($bx['guide']['t'] !== '' && $bx['guide']['u'] !== ''): $gu = $bx['guide']['u']; $gx = preg_match('#^https?://#i', $gu) ? ' target="_blank" rel="noopener"' : ($gu[0] === '#' ? ' data-goto="' . h(ltrim($gu, '#')) . '"' : ''); ?>
          <a class="ibox-guide" href="<?= h($gu) ?>"<?= $gx ?>><?= ve_box_icon('book', 16) ?><span class="gt"><b><?= h($bx['guide']['t']) ?></b><?= $bx['guide']['s'] !== '' ? ' <span class="gs">' . h($bx['guide']['s']) . '</span>' : '' ?></span><span class="ga">→</span></a>
        <?php endif; ?>
        <?php if ($bx['items']): ?><div class="ibox-acc">
          <?php foreach ($bx['items'] as $it): ?>
            <details><summary><span class="si"><?= ve_box_icon($it['i'], 16) ?></span><span class="st"><?= h($it['t']) ?></span><span class="sa">→</span></summary>
              <?php if ($it['b'] !== ''): ?><div class="ab"><?php foreach (preg_split('/\n+/', $it['b']) as $ln): if (trim($ln) !== ''): ?><p><?= h($ln) ?></p><?php endif; endforeach; ?></div><?php endif; ?>
            </details>
          <?php endforeach; ?>
        </div><?php endif; ?>
        <?php if ($bx['trust']): ?><div class="ibox-trust"><?php foreach ($bx['trust'] as $it): ?><div><?= ve_box_icon($it['i'], 18) ?><span><?= h($it['t']) ?></span></div><?php endforeach; ?></div><?php endif; ?>
      </div>
      <script>
        document.querySelectorAll('[data-goto]').forEach(function (a) {
          a.addEventListener('click', function (e) {
            var b = document.querySelector('.info-tab-btn[data-tab="' + a.dataset.goto + '"]'); if (!b) return;
            e.preventDefault(); b.click(); b.scrollIntoView({behavior: 'smooth', block: 'center'});
          });
        });
      </script>
    </div>

    <div class="product-info">
      <div class="eyebrow"><?= htmlspecialchars($product['category']) ?></div>
      <h1><?= htmlspecialchars($product['title']) ?></h1>
      <?php if ($rStat): ?><p class="rating-line">★ <?= h(number_format($rStat['avg'], 1, ',', '')) ?> <span>· <?= (int) $rStat['n'] ?> reseña<?= $rStat['n'] === 1 ? '' : 's' ?> con compra verificada</span></p><?php endif; ?>
      <?php $lead = !empty($product['lead']) ? $product['lead'] : ($product['long_desc'] !== '' ? $product['long_desc'] : ($product['desc'] !== '' ? $product['desc'] : 'Licencia original con entrega digital y soporte.')); ?>
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
      <form class="buy-box" method="post" action="venezuela-carrito.php" id="buy-form">
        <h3>Elige tu licencia</h3>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="slug" value="<?= h($slug) ?>">
        <div class="variant-list" style="margin-bottom:14px">
          <?php $firstOk = true; foreach ($product['variants'] as $v):
            $bsPrice = bs($v['price'], $rate); $off = !$v['avail']; ?>
            <label class="variant-item selectable<?= $off ? ' is-off' : '' ?>">
              <span class="variant-left"><input type="radio" name="variant_id" value="<?= (int) $v['id'] ?>" required data-usd="<?= h($v['price']) ?>" data-bsn="<?= (int) ve_bs_amount($v['price'], $rate) ?>" data-max="<?= $v['stock'] !== null ? (int) min(10, $v['stock']) : 10 ?>" <?= $off ? 'disabled' : ($firstOk ? 'checked' : '') ?>><span class="variant-name"><?= htmlspecialchars($v['label']) ?></span></span>
              <span class="variant-price-text">
                <?php if ($off): ?><span class="variant-price-usd">Agotado</span>
                <?php else: ?><span class="variant-price-usd"><?= usd($v['price']) ?></span><?php if ($bsPrice): ?><span class="variant-price-bs"><?= htmlspecialchars($bsPrice) ?></span><?php endif; ?><?php endif; ?>
              </span>
            </label>
          <?php if (!$off) $firstOk = false; endforeach; ?>
        </div>
        <div class="qty-row"><label for="b-qty">Cantidad</label>
          <select id="b-qty" name="qty"><?php for ($i = 1; $i <= 10; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></div>
        <div class="buy-total"><span>Subtotal</span><span style="text-align:right"><span class="t-usd" id="t-usd"></span><br><span class="t-bs" id="t-bs"></span></span></div>
        <button class="btn-buy" type="submit">🛒 Agregar al carrito</button>
        <p style="font-size:.76rem;color:var(--ash);text-align:center;margin:10px 0 0">Puedes comprar varios productos en un solo pedido y pagar una sola vez. Pagas por Binance o PagoMóvil; si no hay pago, el pedido se cancela solo en <?= (int) ve_pay_hours() ?> horas.</p>
        <p style="text-align:center;margin:10px 0 0"><a class="btn-sec" target="_blank" href="<?= htmlspecialchars(wa_link('Hola, tengo una duda sobre: ' . $product['title'])) ?>">💬 ¿Dudas antes de comprar? Consulta por WhatsApp</a></p>
      </form>
      <script>
        (function () {
          var f = document.getElementById('buy-form'), tu = document.getElementById('t-usd'), tb = document.getElementById('t-bs'), qs = document.getElementById('b-qty');
          function upd() {
            var r = f.querySelector('input[name=variant_id]:checked'); if (!r) { tu.textContent = ''; tb.textContent = ''; return; }
            var max = +r.dataset.max || 10, q = Math.min(+qs.value || 1, max);
            for (var i = 0; i < qs.options.length; i++) qs.options[i].disabled = (i + 1) > max;
            if ((+qs.value || 1) > max) qs.value = max;
            tu.textContent = '$' + ((+r.dataset.usd) * q).toFixed(2) + ' USD';
            tb.textContent = +r.dataset.bsn ? '≈ Bs. ' + ((+r.dataset.bsn) * q).toLocaleString('es-VE') : '';
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
        <span>✉️ Soporte por correo</span>
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
        <?php if (!empty($product['long_html'])): ?><div class="rich"><?= $product['long_html'] ?></div>
        <?php elseif ($product['long_desc'] !== ''): ?><p><?= htmlspecialchars($product['long_desc']) ?></p>
        <?php elseif (trim($product['desc_html'] ?? '') !== ''): ?><?= $product['desc_html'] ?>
        <?php else: ?><p>Licencia original con entrega digital y soporte por correo.</p><?php endif; ?>

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
          <div class="faq-a">Escríbenos a nuestro correo de soporte y te ayudamos a resolverlo — el soporte está incluido en tu compra.</div>
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
