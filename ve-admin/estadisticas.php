<?php
/**
 * Estadísticas de la tienda de Venezuela: visitas, embudo (visita → producto → carrito → pedido → pago), ventas, orígenes y productos.
 * Las visitas se cuentan de forma anónima (sin IP ni datos personales).
 */
require_once __DIR__ . '/_auth.php';
$pdo = ve_pdo();
ve_stats_ensure_table($pdo);

$ranges = ['hoy' => ['Hoy', 0], 'ayer' => ['Ayer', 1], '7' => ['7 días', 6], '30' => ['30 días', 29]];
$r = (string) ($_GET['r'] ?? '7'); if (!isset($ranges[$r])) $r = '7';
$startDay = date('Y-m-d', strtotime('-' . $ranges[$r][1] . ' days'));
$from = $startDay . ' 00:00:00';
$to = $r === 'ayer' ? date('Y-m-d') . ' 00:00:00' : date('Y-m-d H:i:s', strtotime('+1 day'));
$args = [':f' => $from, ':t' => $to];

function st_one(PDO $pdo, string $sql, array $a) { $s = $pdo->prepare($sql); $s->execute($a); return $s->fetchColumn(); }
function st_rows(PDO $pdo, string $sql, array $a) { $s = $pdo->prepare($sql); $s->execute($a); return $s->fetchAll(); }
function st_pct($a, $b) { return $b > 0 ? round($a * 100 / $b, 1) . '%' : '—'; }
function st_usd($n) { return '$' . number_format((float) $n, 2) . ' USD'; }
$W = 'created_at >= :f AND created_at < :t';
$We = 'e.created_at >= :f AND e.created_at < :t';
$Wo = 'o.created_at >= :f AND o.created_at < :t';
$PAID = "o.status IN ('pagado','entregado')";

$visitors  = (int) st_one($pdo, "SELECT COUNT(DISTINCT vid) FROM ve_events WHERE ev='view' AND $W", $args);
$views     = (int) st_one($pdo, "SELECT COUNT(*) FROM ve_events WHERE ev='view' AND $W", $args);
$vProduct  = (int) st_one($pdo, "SELECT COUNT(DISTINCT vid) FROM ve_events WHERE ev='view' AND page='producto' AND $W", $args);
$vCart     = (int) st_one($pdo, "SELECT COUNT(DISTINCT vid) FROM ve_events WHERE ev='cart_add' AND $W", $args);
$ordersAll = (int) st_one($pdo, "SELECT COUNT(*) FROM ve_orders o WHERE $Wo AND o.status <> 'anulado'", $args);
$paid = st_rows($pdo, "SELECT COUNT(*) n, COALESCE(SUM(o.price_usd),0) usd, COALESCE(SUM(CASE WHEN o.method='pagomovil' THEN o.amount_bs ELSE 0 END),0) bs FROM ve_orders o WHERE $PAID AND $Wo", $args)[0];
$byMethod = st_rows($pdo, "SELECT o.method, COUNT(*) n, COALESCE(SUM(o.price_usd),0) usd FROM ve_orders o WHERE $PAID AND $Wo GROUP BY o.method", $args);
$ticket = $paid['n'] > 0 ? $paid['usd'] / $paid['n'] : 0;

$perDay = st_rows($pdo, "SELECT DATE(created_at) d, COUNT(DISTINCT vid) v, COUNT(*) pv FROM ve_events WHERE ev='view' AND $W GROUP BY DATE(created_at) ORDER BY d", $args);
$byDay = []; foreach ($perDay as $x) $byDay[$x['d']] = $x;
$maxV = 1; foreach ($perDay as $x) $maxV = max($maxV, (int) $x['v']);
$sources = st_rows($pdo, "SELECT source, COUNT(DISTINCT vid) v FROM ve_events WHERE ev='view' AND source <> 'interno' AND $W GROUP BY source ORDER BY v DESC LIMIT 8", $args);
$devices = st_rows($pdo, "SELECT device, COUNT(DISTINCT vid) v FROM ve_events WHERE ev='view' AND $W GROUP BY device ORDER BY v DESC", $args);
$topProducts = st_rows($pdo, "SELECT e.product_id, COALESCE(NULLIF(vp.title,''), p.title) title, COUNT(*) vistas, COUNT(DISTINCT e.vid) personas
    FROM ve_events e LEFT JOIN products p ON p.id = e.product_id LEFT JOIN ve_products vp ON vp.product_id = e.product_id
    WHERE e.ev='view' AND e.page='producto' AND e.product_id IS NOT NULL AND $We GROUP BY e.product_id, title ORDER BY vistas DESC LIMIT 10", $args);
$topCart = st_rows($pdo, "SELECT e.product_id, COALESCE(NULLIF(vp.title,''), p.title) title, COUNT(*) n
    FROM ve_events e LEFT JOIN products p ON p.id = e.product_id LEFT JOIN ve_products vp ON vp.product_id = e.product_id
    WHERE e.ev='cart_add' AND e.product_id IS NOT NULL AND $We GROUP BY e.product_id, title ORDER BY n DESC LIMIT 5", $args);
// Lo más vendido (pedidos pagados del período; los pedidos anteriores al carrito se cuentan como 1 unidad)
$topSold = st_rows($pdo, "SELECT t, SUM(q) q, SUM(u) u FROM (
        SELECT i.product_title t, i.qty q, i.unit_usd * i.qty u FROM ve_order_items i JOIN ve_orders o ON o.id = i.order_id WHERE $PAID AND $Wo
        UNION ALL
        SELECT o.product_title, 1, o.price_usd FROM ve_orders o WHERE $PAID AND $Wo AND NOT EXISTS (SELECT 1 FROM ve_order_items i2 WHERE i2.order_id = o.id)
    ) x GROUP BY t ORDER BY q DESC, u DESC LIMIT 10", $args);
$abandoned = st_rows($pdo, "SELECT o.id, o.order_number, o.email, o.price_usd, o.created_at, o.method, o.status,
        (SELECT COUNT(*) FROM ve_proofs p WHERE p.order_id = o.id) proofs
    FROM ve_orders o WHERE o.status IN ('pendiente','anulado') AND $Wo ORDER BY o.id DESC LIMIT 12", $args);
function st_clean($t) { return trim(preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]/u', '', (string) $t)); }

admin_header('Estadísticas');
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
  <h2 style="font-family:var(--serif);margin:0;">Estadísticas de la tienda</h2>
  <div><?php foreach ($ranges as $k => $v): ?><a class="btn-a <?= $r === (string) $k ? '' : 'ghost' ?>" style="margin-left:6px;" href="?r=<?= h($k) ?>"><?= h($v[0]) ?></a><?php endforeach; ?></div>
</div>
<style>.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px}.kpi{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:16px}.kpi b{display:block;font-size:1.6rem;font-weight:600}.kpi span{font-size:.78rem;color:var(--ash)}.bar{height:10px;background:var(--gold);border-radius:5px}.two{display:grid;grid-template-columns:1fr 1fr;gap:20px}@media(max-width:800px){.two{grid-template-columns:1fr}}</style>

<div class="kpis">
  <div class="kpi"><b><?= $visitors ?></b><span>personas distintas en la tienda</span></div>
  <div class="kpi"><b><?= $views ?></b><span>páginas vistas</span></div>
  <div class="kpi"><b><?= (int) $paid['n'] ?></b><span>pedidos pagados</span></div>
  <div class="kpi"><b><?= h(st_usd($paid['usd'])) ?></b><span>ventas pagadas<?= (float) $paid['bs'] > 0 ? ' · incl. Bs. ' . h(number_format((float) $paid['bs'], 0, ',', '.')) . ' por PagoMóvil' : '' ?></span></div>
  <div class="kpi"><b><?= h(st_usd($ticket)) ?></b><span>compra promedio</span></div>
  <div class="kpi"><b><?= st_pct((int) $paid['n'], $visitors) ?></b><span>visitas que terminaron comprando</span></div>
</div>

<div class="box">
  <strong>Del interés al pago</strong>
  <table class="t" style="margin-top:12px;">
    <tr><th>Etapa</th><th>Personas / pedidos</th><th>Del paso anterior</th></tr>
    <tr><td>Entraron a la tienda</td><td><?= $visitors ?></td><td>—</td></tr>
    <tr><td>Vieron un producto</td><td><?= $vProduct ?></td><td><?= st_pct($vProduct, $visitors) ?></td></tr>
    <tr><td>Agregaron al carrito</td><td><?= $vCart ?></td><td><?= st_pct($vCart, $vProduct) ?></td></tr>
    <tr><td>Hicieron el pedido (sin contar cancelados)</td><td><?= $ordersAll ?></td><td>—</td></tr>
    <tr><td>Pagaron</td><td><?= (int) $paid['n'] ?></td><td><?= st_pct((int) $paid['n'], $ordersAll) ?></td></tr>
  </table>
  <p class="tag" style="margin:10px 0 0;">Los pedidos incluyen también tus pruebas. Un pedido puede tener varios productos y cuenta una sola vez.</p>
</div>

<div class="box">
  <strong>Visitas por día</strong>
  <table class="t" style="margin-top:12px;">
    <tr><th style="width:110px;">Día</th><th>Personas</th><th style="width:90px;">Páginas</th></tr>
    <?php for ($d = strtotime($startDay); $d <= strtotime($r === 'ayer' ? $startDay : date('Y-m-d')); $d += 86400): $k = date('Y-m-d', $d); $x = $byDay[$k] ?? ['v' => 0, 'pv' => 0]; ?>
      <tr><td><?= h(date('d/m', $d)) ?></td><td><div style="display:flex;align-items:center;gap:8px;"><div class="bar" style="width:<?= (int) round(($x['v'] / $maxV) * 80) ?>%;min-width:2px;"></div><?= (int) $x['v'] ?></div></td><td><?= (int) $x['pv'] ?></td></tr>
    <?php endfor; ?>
  </table>
</div>

<div class="two">
  <div class="box"><strong>De dónde llegan</strong>
    <table class="t" style="margin-top:12px;"><?php foreach ($sources as $x): ?><tr><td><?= h($x['source']) ?></td><td><?= (int) $x['v'] ?></td></tr><?php endforeach; if (!$sources): ?><tr><td class="tag">Aún sin datos.</td></tr><?php endif; ?></table></div>
  <div class="box"><strong>Dispositivo</strong>
    <table class="t" style="margin-top:12px;"><?php foreach ($devices as $x): ?><tr><td><?= h($x['device']) ?></td><td><?= (int) $x['v'] ?></td></tr><?php endforeach; if (!$devices): ?><tr><td class="tag">Aún sin datos.</td></tr><?php endif; ?></table></div>
</div>

<div class="two">
  <div class="box"><strong>Productos más vistos</strong>
    <table class="t" style="margin-top:12px;"><tr><th>Producto</th><th>Vistas</th><th>Personas</th></tr>
    <?php foreach ($topProducts as $x): ?><tr><td><a href="productos.php?id=<?= (int) $x['product_id'] ?>"><?= h(mb_substr(st_clean($x['title']), 0, 60)) ?></a></td><td><?= (int) $x['vistas'] ?></td><td><?= (int) $x['personas'] ?></td></tr><?php endforeach; if (!$topProducts): ?><tr><td colspan="3" class="tag">Aún sin datos.</td></tr><?php endif; ?></table></div>
  <div class="box"><strong>Más agregados al carrito</strong>
    <table class="t" style="margin-top:12px;"><?php foreach ($topCart as $x): ?><tr><td><?= h(mb_substr(st_clean($x['title']), 0, 60)) ?></td><td><?= (int) $x['n'] ?></td></tr><?php endforeach; if (!$topCart): ?><tr><td class="tag">Aún sin datos.</td></tr><?php endif; ?></table></div>
</div>

<div class="two">
  <div class="box"><strong>Lo más vendido</strong>
    <table class="t" style="margin-top:12px;"><tr><th>Producto</th><th>Licencias</th><th>Ventas</th></tr>
    <?php foreach ($topSold as $x): ?><tr><td><?= h(mb_substr(st_clean($x['t']), 0, 60)) ?></td><td><?= (int) $x['q'] ?></td><td><?= h(st_usd($x['u'])) ?></td></tr><?php endforeach; if (!$topSold): ?><tr><td colspan="3" class="tag">Aún sin ventas en este período.</td></tr><?php endif; ?></table></div>
  <div class="box"><strong>Ventas por medio de pago</strong>
    <table class="t" style="margin-top:12px;"><tr><th>Medio</th><th>Pedidos</th><th>Ventas</th></tr>
    <?php foreach ($byMethod as $x): ?><tr><td><?= h(ve_method_label($x['method'])) ?></td><td><?= (int) $x['n'] ?></td><td><?= h(st_usd($x['usd'])) ?></td></tr><?php endforeach; if (!$byMethod): ?><tr><td colspan="3" class="tag">Aún sin ventas en este período.</td></tr><?php endif; ?></table></div>
</div>

<div class="box">
  <strong>Llegaron al pago y no pagaron</strong>
  <p class="tag" style="margin:6px 0 10px;">Pedidos de este período que siguen pendientes o se cancelaron por falta de pago.</p>
  <table class="t"><tr><th>Pedido</th><th>Correo</th><th>Total</th><th>Medio</th><th>Estado</th><th>Fecha</th></tr>
  <?php foreach ($abandoned as $o): ?><tr>
    <td><a href="pedido.php?id=<?= (int) $o['id'] ?>"><?= h($o['order_number']) ?></a></td><td><?= h($o['email']) ?></td><td><?= h(st_usd($o['price_usd'])) ?></td>
    <td><?= h(ve_method_label($o['method'])) ?></td><td><?= $o['status'] === 'anulado' ? 'Cancelado' : ((int) $o['proofs'] > 0 ? 'Con comprobante' : 'Pendiente') ?></td><td class="tag"><?= h(date('d/m H:i', strtotime($o['created_at']))) ?></td></tr>
  <?php endforeach; if (!$abandoned): ?><tr><td colspan="6" class="tag">Ninguno en este período.</td></tr><?php endif; ?></table>
</div>
<p class="tag">Las visitas se cuentan de forma anónima (sin guardar IP ni datos personales), no cuentan tus visitas desde el panel ni los robots de buscadores, y se miden desde que se instaló esta función.</p>
<?php admin_footer();
