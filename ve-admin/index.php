<?php
require_once __DIR__ . '/_auth.php';
$pdo = ve_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'read_all') { $pdo->exec('UPDATE ve_notifications SET is_read = 1 WHERE is_read = 0'); }
    header('Location: index.php' . (!empty($_POST['back']) ? '?' . $_POST['back'] : '')); exit;
}
$f = (string) ($_GET['f'] ?? 'revisar');
$q = trim((string) ($_GET['q'] ?? ''));
$filters = ['revisar' => 'Por revisar (con comprobante)', 'pendiente' => 'Pendientes de pago', 'pagado' => 'Pagados · por entregar', 'entregado' => 'Entregados', 'anulado' => 'Cancelados', 'todos' => 'Todos'];
if (!isset($filters[$f])) $f = 'revisar';
$where = ['1=1']; $args = [];
if ($f === 'revisar') $where[] = "o.status = 'pendiente' AND EXISTS (SELECT 1 FROM ve_proofs p WHERE p.order_id = o.id)";
elseif ($f === 'pendiente') $where[] = "o.status = 'pendiente' AND NOT EXISTS (SELECT 1 FROM ve_proofs p WHERE p.order_id = o.id)";
elseif ($f !== 'todos') { $where[] = 'o.status = :s'; $args[':s'] = $f; }
if ($q !== '') { $where[] = '(o.order_number LIKE :q OR o.email LIKE :q OR o.name LIKE :q OR o.phone LIKE :q OR o.code LIKE :q OR o.product_title LIKE :q)'; $args[':q'] = '%' . $q . '%'; }
$st = $pdo->prepare("SELECT o.*, (SELECT COUNT(*) FROM ve_proofs p WHERE p.order_id = o.id) AS proofs FROM ve_orders o WHERE " . implode(' AND ', $where) . " ORDER BY o.id DESC LIMIT 200");
$st->execute($args); $orders = $st->fetchAll();
$counts = [];
foreach ($pdo->query("SELECT status, COUNT(*) n FROM ve_orders GROUP BY status")->fetchAll() as $r) $counts[$r['status']] = (int) $r['n'];
$nRev = (int) $pdo->query("SELECT COUNT(*) FROM ve_orders o WHERE o.status = 'pendiente' AND EXISTS (SELECT 1 FROM ve_proofs p WHERE p.order_id = o.id)")->fetchColumn();
$notes = $pdo->query('SELECT * FROM ve_notifications ORDER BY id DESC LIMIT 12')->fetchAll();
$unread = (int) $pdo->query('SELECT COUNT(*) FROM ve_notifications WHERE is_read = 0')->fetchColumn();
$rateInfo = get_bcv_rate_info();
admin_header('Pedidos'); flash_show(); ?>
<?php if (ve_setting('rate_alert') !== ''): ?><div class="flash err">⚠️ <?= h(ve_setting('rate_alert')) ?> <a href="ajustes.php">Ir a Ajustes</a></div><?php endif; ?>
<?php if ($rateInfo['rate'] <= 0): ?><div class="flash err">⚠️ No hay tasa BCV disponible: los precios en Bs. y PagoMóvil no funcionan. <a href="ajustes.php">Pon una tasa manual</a>.</div><?php endif; ?>
<?php if (!ve_mail_ready()): ?><div class="flash err">⚠️ El correo no está configurado: los clientes y tú no reciben avisos por email (los avisos del panel sí funcionan).</div><?php endif; ?>
<div class="grid2">
  <div class="box"><h2>Resumen</h2>
    <p style="margin:0"><b><?= $nRev ?></b> por revisar · <b><?= (int) ($counts['pagado'] ?? 0) ?></b> por entregar · <b><?= (int) ($counts['pendiente'] ?? 0) ?></b> pendientes · <b><?= (int) ($counts['entregado'] ?? 0) ?></b> entregados</p>
    <p class="tag" style="margin:8px 0 0">Tasa BCV: <b>Bs. <?= h(number_format($rateInfo['rate'], 2, ',', '.')) ?></b> (<?= h(['api' => 'internet', 'cache' => 'internet, de hoy', 'ultima' => 'última guardada', 'manual' => 'manual', 'ninguna' => 'sin dato'][$rateInfo['source']]) ?>)</p></div>
  <div class="box"><h2>Avisos <?= $unread ? '<span class="badge">' . $unread . ' nuevos</span>' : '' ?></h2>
    <?php if (!$notes): ?><p class="muted" style="margin:0">Sin avisos todavía.</p><?php endif; ?>
    <?php foreach ($notes as $n): ?><p style="margin:0 0 6px;font-size:.85rem;<?= $n['is_read'] ? 'color:var(--ash)' : 'font-weight:600' ?>"><span class="tag"><?= h(ve_fmt_dt($n['created_at'])) ?></span> <?php if ($n['order_id']): ?><a href="pedido.php?id=<?= (int) $n['order_id'] ?>" style="color:inherit"><?= h($n['message']) ?></a><?php else: ?><?= h($n['message']) ?><?php endif; ?></p><?php endforeach; ?>
    <?php if ($unread): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn-a ghost" style="margin-top:6px">Marcar todo como leído</button></form><?php endif; ?></div>
</div>
<div class="toolbar">
  <?php foreach ($filters as $k => $lab): ?><a class="f <?= $k === $f ? 'on' : '' ?>" href="?f=<?= h($k) ?>"><?= h($lab) ?><?= $k === 'revisar' && $nRev ? ' (' . $nRev . ')' : '' ?></a><?php endforeach; ?>
  <form method="get" style="margin-left:auto"><input type="hidden" name="f" value="todos"><input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar pedido, correo, nombre…" style="padding:7px 12px;border:1px solid var(--line);border-radius:20px"></form>
</div>
<table class="t"><tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th>Producto</th><th>Monto</th><th>Estado</th><th></th></tr>
<?php foreach ($orders as $o): $rev = $o['status'] === 'pendiente' && $o['proofs'] > 0; ?>
<tr class="<?= $rev ? 'row-hl' : '' ?>"><td><b><?= h($o['order_number']) ?></b></td><td><?= h(ve_fmt_dt($o['created_at'])) ?></td>
<td><?= h($o['name']) ?><br><span class="tag"><?= h($o['email']) ?></span></td>
<td><?= h($o['product_title']) ?><br><span class="tag"><?= h($o['variant_title']) ?></span></td>
<td><?= h(ve_order_total_text($o)) ?><br><span class="tag"><?= h(ve_method_label($o['method'])) ?></span></td>
<td><span class="pill <?= $rev ? 'revisar' : h($o['status']) ?>"><?= $rev ? 'Comprobante enviado' : h(ucfirst($o['status'])) ?></span><?= $o['proofs'] > 0 ? '<br><span class="tag">' . (int) $o['proofs'] . ' comprobante(s)</span>' : '' ?></td>
<td><a class="btn-a<?= $rev ? '' : ' ghost' ?>" href="pedido.php?id=<?= (int) $o['id'] ?>">Abrir</a></td></tr>
<?php endforeach; if (!$orders): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:26px">No hay pedidos en esta vista.</td></tr><?php endif; ?>
</table>
<?php admin_footer();
