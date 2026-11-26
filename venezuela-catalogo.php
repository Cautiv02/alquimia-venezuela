<?php
/**
 * venezuela-catalogo.php
 * -------------------------
 * Portada del catálogo para el mercado venezolano — banner real, franja de
 * marcas, señales de confianza, y grilla de productos que enlazan a su
 * propia página de detalle (venezuela-producto.php).
 */

require_once __DIR__ . '/venezuela-data.php'; // productos + funciones compartidas (bcv, whatsapp, etc.)

ve_maintenance_lazy();          // recordatorios y cancelación automática de pedidos sin pagar (cada 5 min como máximo)
$rate = get_bcv_rate();
$catalog = ve_catalog();
// Datos para el buscador con sugerencias y para los filtros
$suggest = []; $facetOpts = ['brand' => [], 'cat' => [], 'dur' => []];
foreach ($catalog as $slug => $p) {
    $img = product_image_path($p);
    $suggest[] = ['s' => $slug, 't' => $p['title'], 'b' => $p['brand'], 'c' => $p['facet_category'], 'p' => '$' . number_format($p['min_price'], 2) . ' USD',
                  'i' => $img ?: $brandLogos[$p['brand_logo']], 'x' => $p['soldout']];
    $facetOpts['brand'][$p['brand']] = ($facetOpts['brand'][$p['brand']] ?? 0) + 1;
    $facetOpts['cat'][$p['facet_category']] = ($facetOpts['cat'][$p['facet_category']] ?? 0) + 1;
    if ($p['duration'] !== '') $facetOpts['dur'][$p['duration']] = ($facetOpts['dur'][$p['duration']] ?? 0) + 1;
}
foreach ($facetOpts as &$fo) { arsort($fo); } unset($fo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alquimia Digital Venezuela — Software original</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php include __DIR__ . '/venezuela-styles.php'; ?>
<style>
  /* Tipografía de títulos unificada: Source Serif 4 (formal) + Manrope */
  :root {
    --serif: 'Source Serif 4', Georgia, serif;
    --sans: 'IBM Plex Sans', -apple-system, sans-serif;
  }
</style>
</head>
<body>

<?php include __DIR__ . '/venezuela-header.php'; ?>

<section class="ve-banner">
  <div class="ve-banner-inner">
    <img src="imagenes/banner-principal.jpg" alt="Alquimia Digital — Seguro, rápido y garantizado. Soporte técnico especializado, 8 años y más de 12.000 clientes, envíos digitales rápidos, presencia internacional.">

  </div>
</section>

<div class="brands">
  <div class="brands-inner">
    <div class="brands-track">
      <?php foreach (array_merge($brandLogos, $brandLogos) as $key => $b): ?>
        <img src="<?= $b ?>" alt="">
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
// Portada por secciones: cada producto va a su sección; los que no tienen (o cuya sección ya no existe) caen en la primera
$secs = ve_db_ready() ? ve_sections() : [];
if (!$secs) $secs = [['id' => 0, 'title' => 'Ofertas Flash', 'note' => "Paga en dólares (USDT) o en bolívares (PagoMóvil) a la tasa BCV del día.\nLos precios indicados en bolívares están actualizados en tiempo real."]];
$secIds = array_map('intval', array_column($secs, 'id')); $firstSec = $secIds[0];
$bySec = array_fill_keys($secIds, []);
foreach ($catalog as $slug => $p) { $ps = (int) ($p['section'] ?? 0); $sid = in_array($ps, $secIds, true) ? $ps : ($ps ? null : $firstSec); if ($sid !== null) $bySec[$sid][$slug] = $p; }
foreach ($secs as $si => $sec):
  $items = $bySec[(int) $sec['id']];
  if (!$items && $si > 0) continue; ?>
<section class="section" id="<?= $si === 0 ? 'catalogo' : 'seccion-' . (int) $sec['id'] ?>">
  <div class="wrap">
    <div class="section-head">
      <h2 class="section-title"><?= h($sec['title']) ?></h2>
      <?php foreach (preg_split('/\R+/', trim((string) $sec['note'])) as $ln): if (trim($ln) === '') continue; ?><p class="section-note"><?= h($ln) ?></p><?php endforeach; ?>
    </div>
    <?php $showSearch = false; /* poner en true cuando el catálogo crezca: activa buscador y filtros */ ?>
    <?php if ($si === 0 && $showSearch && count($catalog) > 1): ?>
    <div class="ve-tools">
      <div class="ve-search">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" id="ve-q" placeholder="Buscar: Windows, Office, antivirus, AutoCAD…" autocomplete="off" aria-label="Buscar productos">
        <div class="ve-suggest" id="ve-suggest" role="listbox"></div>
      </div>
      <div class="ve-filters">
        <select id="f-brand" aria-label="Marca"><option value="">Marca</option><?php foreach ($facetOpts['brand'] as $k => $n): ?><option value="<?= h($k) ?>"><?= h($k) ?> (<?= (int) $n ?>)</option><?php endforeach; ?></select>
        <select id="f-cat" aria-label="Categoría"><option value="">Categoría</option><?php foreach ($facetOpts['cat'] as $k => $n): ?><option value="<?= h($k) ?>"><?= h($k) ?> (<?= (int) $n ?>)</option><?php endforeach; ?></select>
        <?php if ($facetOpts['dur']): ?><select id="f-dur" aria-label="Duración"><option value="">Duración</option><?php foreach ($facetOpts['dur'] as $k => $n): ?><option value="<?= h($k) ?>"><?= h($k) ?> (<?= (int) $n ?>)</option><?php endforeach; ?></select><?php endif; ?>
        <select id="f-sort" aria-label="Ordenar"><option value="">Ordenar</option><option value="asc">Precio: menor a mayor</option><option value="desc">Precio: mayor a menor</option><option value="name">Nombre A–Z</option></select>
        <button type="button" class="ve-clear" id="ve-clear">Limpiar filtros</button>
      </div>
      <div class="ve-count" id="ve-count"></div>
    </div>
    <?php endif; ?>
    <div class="products"<?= $si === 0 ? ' id="ve-grid"' : '' ?>>
      <?php foreach ($items as $slug => $p) ve_render_card($p, $rate); ?>
      <?php if ($si === 0): ?><p class="ve-empty" id="ve-empty">No encontramos productos con esa búsqueda. Prueba otra palabra o escríbenos por WhatsApp.</p><?php endif; ?>
    </div>
    <?php if (!$catalog && $si === 0): ?><p class="section-note" style="text-align:center">Estamos actualizando el catálogo. Escríbenos por WhatsApp y te atendemos de inmediato.</p><?php endif; ?>
  </div>
</section>
<?php endforeach; ?>

<section class="section on-paper" id="como-comprar">
  <div class="wrap">
    <div class="section-head">
      <h2 class="section-title">¿Cómo obtienes tu licencia?</h2>
      <p class="section-note">Sin complicaciones. Elige, haz tu pedido, paga por Binance o PagoMóvil y recibe tu licencia.</p>
      <p class="section-note">Instrucciones paso a paso con soporte por correo incluido.</p>
    </div>
    <div class="how-grid">
      <div class="how-steps">
        <div class="how-step">
          <div class="how-step-num">1</div>
          <div>
            <h4>Elige tu producto</h4>
            <p>Navega el catálogo, elige la licencia que necesitas y la variante (dispositivos, duración).</p>
          </div>
        </div>
        <div class="how-step">
          <div class="how-step-num">2</div>
          <div>
            <h4>Haz tu pedido y paga</h4>
            <p>Presiona "Hacer pedido", paga en USDT (Binance) o en bolívares (PagoMóvil) y sube tu comprobante. ¿Dudas antes de comprar? Consúltanos por WhatsApp.</p>
          </div>
        </div>
        <div class="how-step">
          <div class="how-step-num">3</div>
          <div>
            <h4>Recibe y activa</h4>
            <p>Te enviamos todo por correo electrónico: enlaces de descarga, guía de instalación y licencia. (Soporte por correo incluido si lo necesitas)</p>
          </div>
        </div>
        <div class="how-step">
          <div class="how-step-num">4</div>
          <div>
            <h4>Disfruta tu producto</h4>
            <p>Relájate y trabaja tranquilo. Déjanos un comentario sobre tu experiencia de compra y obtén un descuento para tu próximo pedido.</p>
          </div>
        </div>
      </div>
      <div class="how-types">
        <div class="how-type-card">
          <h4>Licencia tipo serial alfanumérico</h4>
          <p class="how-type-time">⚡ Entrega inmediata <span>(de 30 a 60 min una vez se confirme el pago)</span></p>
          <ul>
            <li>Clave de activación original</li>
            <li>Enlace de descarga directa</li>
            <li>Guía de instalación incluida</li>
          </ul>
        </div>
        <div class="how-type-card">
          <h4>🛡️ Licencia tipo cuenta de usuario</h4>
          <p class="how-type-time">⚡ Entrega rápida <span>(de 1 a 3 horas una vez se confirme el pago)</span></p>
          <ul>
            <li>Datos de acceso (usuario y clave) o vinculación a tu cuenta</li>
            <li>Acceso completo a funciones originales</li>
            <li>Soporte por correo durante toda la licencia</li>
          </ul>
        </div>
        <p class="how-type-note">Ambos casos dentro del horario laboral.</p>
      </div>
    </div>
  </div>
</section>

<section class="section" id="resenas">
  <div class="wrap">
    <div class="section-head">
      <h2 class="section-title">Reseñas de Clientes</h2>
      <p class="section-note">Lo que dicen quienes ya compraron con nosotros.</p>
    </div>
    <?php
      $trustooReviews = get_trustoo_reviews(60);
      $ownReviews = ve_own_reviews(12);
      $reviewsToShow = !empty($trustooReviews) ? array_merge($ownReviews, $trustooReviews) : ($ownReviews ?: [
          ['author' => 'Cliente verificado', 'rating' => 5, 'content' => 'Compré Office y me llegó la clave a los pocos minutos. Todo funcionó perfecto.', 'product' => 'Office 2021 LTSC PRO', 'date_fmt' => ''],
          ['author' => 'Cliente verificado', 'rating' => 5, 'content' => 'Excelente atención por WhatsApp, resolvieron mis dudas antes de comprar.', 'product' => 'Windows 11 PRO', 'date_fmt' => ''],
          ['author' => 'Cliente verificado', 'rating' => 5, 'content' => 'Ya es la segunda vez que compro, licencias 100% originales.', 'product' => 'Autodesk AutoCAD', 'date_fmt' => ''],
      ]);
    ?>
    <div class="reviews-carousel">
      <button class="reviews-arrow reviews-arrow-prev" onclick="reviewsScroll(-1)" aria-label="Anterior">‹</button>
      <div class="reviews-pager" id="reviews-pager">
        <?php foreach (array_chunk(array_slice($reviewsToShow, 0, 60), 6) as $pageReviews): ?>
          <div class="review-page">
            <?php foreach ($pageReviews as $rev): ?>
              <div class="review-card">
                <div class="review-stars"><?= str_repeat('★', max(1, min(5, $rev['rating']))) ?></div>
                <p class="review-text">"<?= htmlspecialchars($rev['content']) ?>"</p>
                <?php if (!empty($rev['product'])): ?>
                  <p class="review-product">🛒 Compró: <?= htmlspecialchars($rev['product']) ?></p>
                <?php endif; ?>
                <div class="review-footer">
                  <p class="review-author">— <?= htmlspecialchars($rev['author']) ?><?php if (!empty($rev['verified'])): ?> <?= ve_verified_badge() ?><?php endif; ?></p>
                  <?php if (!empty($rev['date_fmt'])): ?>
                    <p class="review-date"><?= htmlspecialchars($rev['date_fmt']) ?></p>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <button class="reviews-arrow reviews-arrow-next" onclick="reviewsScroll(1)" aria-label="Siguiente">›</button>
    </div>
    <?php $totalPages = (int) ceil(min(count($reviewsToShow), 60) / 6); ?>
    <?php if ($totalPages > 1): ?>
      <div class="reviews-dots" id="reviews-dots">
        <?php for ($i = 0; $i < $totalPages; $i++): ?>
          <span class="reviews-dot <?= $i === 0 ? 'active' : '' ?>" onclick="reviewsGoTo(<?= $i ?>)"></span>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
    <script>
      function reviewsScroll(dir) {
        var el = document.getElementById('reviews-pager');
        el.scrollBy({ left: dir * el.clientWidth, behavior: 'smooth' });
      }
      function reviewsGoTo(page) {
        var el = document.getElementById('reviews-pager');
        el.scrollTo({ left: page * el.clientWidth, behavior: 'smooth' });
      }
      (function() {
        var el = document.getElementById('reviews-pager');
        if (!el) return;

        el.addEventListener('scroll', function() {
          var page = Math.round(el.scrollLeft / el.clientWidth);
          document.querySelectorAll('#reviews-dots .reviews-dot').forEach(function(dot, i) {
            dot.classList.toggle('active', i === page);
          });
        });

        // Arrastre con mouse (además del deslizamiento táctil nativo en celular)
        var isDown = false, startX, startScroll, moved = false;
        el.style.cursor = 'grab';

        el.addEventListener('mousedown', function(e) {
          isDown = true;
          moved = false;
          el.style.cursor = 'grabbing';
          el.style.scrollSnapType = 'none'; // se desactiva mientras arrastra, para que siga el mouse libremente
          startX = e.pageX;
          startScroll = el.scrollLeft;
        });
        window.addEventListener('mouseup', function() {
          if (!isDown) return;
          isDown = false;
          el.style.cursor = 'grab';
          el.style.scrollSnapType = 'x mandatory';
          // Al soltar, ajusta a la página más cercana
          var page = Math.round(el.scrollLeft / el.clientWidth);
          el.scrollTo({ left: page * el.clientWidth, behavior: 'smooth' });
        });
        el.addEventListener('mousemove', function(e) {
          if (!isDown) return;
          e.preventDefault();
          moved = true;
          el.scrollLeft = startScroll - (e.pageX - startX);
        });
        // Evita que un arrastre termine disparando un clic accidental en la tarjeta
        el.addEventListener('click', function(e) {
          if (moved) { e.preventDefault(); e.stopPropagation(); }
        }, true);
      })();
    </script>
    <?php if (!empty($trustooReviews) || !empty($ownReviews)): ?>
      <p class="reviews-note">✅ Reseñas reales<?= !empty($trustooReviews) ? ' verificadas por Trustoo' : '' ?><?= !empty($ownReviews) ? ' y de clientes con compra verificada' : '' ?></p>
    <?php else: ?>
      <p class="reviews-note">Próximamente: reseñas reales sincronizadas desde Trustoo.</p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/venezuela-footer.php'; ?>

<script>
(function () {
  var DATA = <?= json_encode($suggest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
  var q = document.getElementById('ve-q'); if (!q) return;
  var box = document.getElementById('ve-suggest'), grid = document.getElementById('ve-grid');
  var cards = Array.prototype.slice.call(grid.querySelectorAll('.product-card'));
  cards.forEach(function (c, i) { c._i = i; });
  var selB = document.getElementById('f-brand'), selC = document.getElementById('f-cat'), selD = document.getElementById('f-dur'), selS = document.getElementById('f-sort');
  var clear = document.getElementById('ve-clear'), count = document.getElementById('ve-count'), empty = document.getElementById('ve-empty');
  function norm(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9 ]+/g, ' ').trim(); }
  function words(s) { return norm(s).split(/\s+/).filter(Boolean); }
  function match(text, ws) { var t = norm(text); return ws.every(function (w) { return t.indexOf(w) > -1; }); }

  function apply() {
    var ws = words(q.value), b = selB.value, c = selC.value, d = selD ? selD.value : '', shown = 0;
    cards.forEach(function (card) {
      var ok = (!ws.length || match(card.dataset.name + ' ' + card.dataset.brand + ' ' + card.dataset.cat, ws)) &&
               (!b || card.dataset.brand === b) && (!c || card.dataset.cat === c) && (!d || card.dataset.dur === d);
      card.style.display = ok ? '' : 'none'; if (ok) shown++;
    });
    var s = selS.value, arr = cards.slice();
    arr.sort(function (x, y) {
      if (s === 'asc') return (+x.dataset.price) - (+y.dataset.price);
      if (s === 'desc') return (+y.dataset.price) - (+x.dataset.price);
      if (s === 'name') return x.dataset.name.localeCompare(y.dataset.name, 'es');
      return x._i - y._i;
    });
    arr.forEach(function (card) { grid.insertBefore(card, empty); });
    var active = ws.length || b || c || d || s;
    clear.style.display = active ? 'inline' : 'none';
    [selB, selC, selD, selS].forEach(function (x) { if (x) x.classList.toggle('on', !!x.value); });
    empty.style.display = shown ? 'none' : 'block';
    count.textContent = active ? (shown + (shown === 1 ? ' producto' : ' productos')) : '';
  }

  var act = -1, list = [];
  function suggest() {
    var ws = words(q.value); act = -1;
    if (!ws.length) { box.classList.remove('open'); box.innerHTML = ''; return; }
    list = DATA.filter(function (d) { return match(d.t + ' ' + d.b + ' ' + d.c, ws); })
      .sort(function (a, b) { var as = norm(a.t).indexOf(ws[0]) === 0 ? 0 : 1, bs = norm(b.t).indexOf(ws[0]) === 0 ? 0 : 1; return as - bs; }).slice(0, 6);
    if (!list.length) { box.classList.remove('open'); box.innerHTML = ''; return; }
    box.innerHTML = list.map(function (d) {
      return '<a role="option" href="venezuela-producto.php?slug=' + encodeURIComponent(d.s) + '"><img src="' + d.i.replace(/"/g, '&quot;') + '" alt=""><span class="sg-t">' +
        d.t.replace(/[&<>"]/g, function (m) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[m]; }) + '</span><span class="sg-p">' + (d.x ? 'Agotado' : 'Desde ' + d.p) + '</span></a>';
    }).join('');
    box.classList.add('open');
  }
  q.addEventListener('input', function () { apply(); suggest(); });
  q.addEventListener('keydown', function (e) {
    var items = box.querySelectorAll('a');
    if (e.key === 'ArrowDown' && items.length) { e.preventDefault(); act = (act + 1) % items.length; }
    else if (e.key === 'ArrowUp' && items.length) { e.preventDefault(); act = (act - 1 + items.length) % items.length; }
    else if (e.key === 'Enter') { if (act > -1 && items[act]) { e.preventDefault(); location.href = items[act].href; } else { box.classList.remove('open'); } return; }
    else if (e.key === 'Escape') { box.classList.remove('open'); return; }
    else return;
    items.forEach(function (a, i) { a.classList.toggle('act', i === act); });
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('.ve-search')) box.classList.remove('open'); });
  [selB, selC, selD, selS].forEach(function (x) { if (x) x.addEventListener('change', apply); });
  clear.addEventListener('click', function () { q.value = ''; selB.value = selC.value = selS.value = ''; if (selD) selD.value = ''; box.classList.remove('open'); apply(); });
  // permite llegar con ?q=texto desde otras páginas
  var m = location.search.match(/[?&]q=([^&]+)/); if (m) { q.value = decodeURIComponent(m[1].replace(/\+/g, ' ')); apply(); }
})();
</script>

</body>
</html>
