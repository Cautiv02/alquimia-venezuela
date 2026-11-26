<?php
/**
 * ve-admin/productos.php — qué productos de la tienda de Chile se venden en Venezuela y a qué precio en USD.
 * Los productos, opciones, fotos y stock son los MISMOS de la tienda de Chile (no se cargan dos veces):
 * aquí solo activas el producto, eliges su dirección (slug) y pones el precio en USD de cada opción.
 */
require_once __DIR__ . '/_auth.php';
$pdo = ve_pdo();

function pv_norm(string $s): string {
    $s = mb_strtolower(ve_clean_text($s));
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
}
function pv_tokens(string $t): array {
    $stop = ['de','para','original','cuenta','suscripcion','oferta','flash','descuento','cyberdays','cyber','blackdays','navidad','exclusiva','barato','en','alquimia','digital','el','la','y','con','sin','reinstalable','licencia','pc','profesional','plus'];
    $t = str_ireplace(['professional', 'profesional'], 'pro', $t);
    $w = array_filter(explode(' ', pv_norm(preg_replace('/\d+\s*%/', ' ', $t))), function ($x) use ($stop) { return $x !== '' && !in_array($x, $stop, true); });
    return array_values(array_unique($w));
}

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $a = (string) ($_POST['action'] ?? '');
    if ($a === 'save') {
        $pid = (int) ($_POST['pid'] ?? 0);
        $p = $pdo->prepare('SELECT id, title FROM products WHERE id = :i'); $p->execute([':i' => $pid]); $p = $p->fetch();
        if (!$p) { flash_set('Producto no encontrado.', true); header('Location: productos.php'); exit; }
        $slug = ve_slugify((string) ($_POST['slug'] ?? '') ?: $p['title']);
        $dup = $pdo->prepare('SELECT product_id FROM ve_products WHERE slug = :s AND product_id <> :p'); $dup->execute([':s' => $slug, ':p' => $pid]);
        if ($dup->fetchColumn()) { flash_set('Esa dirección (slug) ya la usa otro producto. Elige otra.', true); header('Location: productos.php?id=' . $pid); exit; }
        $title = trim(mb_substr((string) ($_POST['title'] ?? ''), 0, 255));
        $pdo->prepare('INSERT INTO ve_products (product_id, slug, title, active, sort_order) VALUES (:p,:s,:t,:a,:o)
            ON DUPLICATE KEY UPDATE slug = VALUES(slug), title = VALUES(title), active = VALUES(active), sort_order = VALUES(sort_order)')
            ->execute([':p' => $pid, ':s' => $slug, ':t' => $title !== '' ? $title : null, ':a' => !empty($_POST['active']) ? 1 : 0, ':o' => (int) ($_POST['sort_order'] ?? 100)]);
        $vs = $pdo->prepare('SELECT id FROM product_variants WHERE product_id = :p'); $vs->execute([':p' => $pid]);
        $up = $pdo->prepare('INSERT INTO ve_prices (variant_id, price_usd, label) VALUES (:v,:p,:l) ON DUPLICATE KEY UPDATE price_usd = VALUES(price_usd), label = VALUES(label)');
        $del = $pdo->prepare('DELETE FROM ve_prices WHERE variant_id = :v');
        foreach ($vs->fetchAll(PDO::FETCH_COLUMN) as $vid) {
            $raw = trim(str_replace(',', '.', (string) ($_POST['usd'][$vid] ?? '')));
            $label = trim(mb_substr((string) ($_POST['label'][$vid] ?? ''), 0, 120));
            if ($raw !== '' && is_numeric($raw) && (float) $raw > 0) $up->execute([':v' => $vid, ':p' => round((float) $raw, 2), ':l' => $label !== '' ? $label : null]);
            else $del->execute([':v' => $vid]);
        }
        flash_set('Guardado. Se ve en la tienda si está activo y tiene al menos una opción con precio en USD.'); header('Location: productos.php?id=' . $pid); exit;
    }
    if ($a === 'import') {
        $n = 0; $usedPid = [];
        foreach ((array) ($_POST['m'] ?? []) as $slug => $pid) {
            $pid = (int) $pid; $static = ve_static()[$slug] ?? null; if (!$pid || !$static || isset($usedPid[$pid])) continue;
            $usedPid[$pid] = true;
            $pdo->prepare('INSERT INTO ve_products (product_id, slug, title, active, sort_order) VALUES (:p,:s,:t,1,:o) ON DUPLICATE KEY UPDATE slug = VALUES(slug), title = VALUES(title), active = 1, sort_order = VALUES(sort_order)')
                ->execute([':p' => $pid, ':s' => $slug, ':t' => $static['title'], ':o' => 10 + $n * 10]);
            $vs = $pdo->prepare('SELECT id, title FROM product_variants WHERE product_id = :p'); $vs->execute([':p' => $pid]); $dbv = $vs->fetchAll();
            foreach ($static['variants'] as $sv) {
                foreach ($dbv as $v) {
                    if (strpos(pv_norm($v['title']), pv_norm($sv['label'])) !== false) {
                        $pdo->prepare('INSERT INTO ve_prices (variant_id, price_usd, label) VALUES (:v,:p,:l) ON DUPLICATE KEY UPDATE price_usd = VALUES(price_usd), label = VALUES(label)')
                            ->execute([':v' => $v['id'], ':p' => $sv['price'], ':l' => $sv['label']]);
                        break;
                    }
                }
            }
            $n++;
        }
        flash_set($n . ' producto(s) importado(s). Revisa cada uno: las opciones que no coincidieron por nombre quedan sin precio y hay que ponerlo a mano.'); header('Location: productos.php'); exit;
    }
    header('Location: productos.php'); exit;
}

/* ── Edición de un producto ── */
$id = (int) ($_GET['id'] ?? 0);
if ($id) {
    $p = $pdo->prepare('SELECT * FROM products WHERE id = :i'); $p->execute([':i' => $id]); $p = $p->fetch();
    if (!$p) { flash_set('Producto no encontrado.', true); header('Location: productos.php'); exit; }
    $vp = $pdo->prepare('SELECT * FROM ve_products WHERE product_id = :i'); $vp->execute([':i' => $id]); $vp = $vp->fetch() ?: ['slug' => ve_slugify($p['title']), 'title' => '', 'active' => 1, 'sort_order' => 100, 'new' => true];
    $vs = $pdo->prepare('SELECT pv.id, pv.title, pv.price, pr.price_usd, pr.label FROM product_variants pv LEFT JOIN ve_prices pr ON pr.variant_id = pv.id WHERE pv.product_id = :i ORDER BY pv.price, pv.id');
    $vs->execute([':i' => $id]); $vars = $vs->fetchAll();
    $clp = (float) str_replace(',', '.', ve_setting('clp_per_usd'));
    admin_header('Producto'); flash_show(); ?>
    <p><a href="productos.php" class="muted" style="text-decoration:none">← Productos</a></p>
    <form method="post" class="box"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="pid" value="<?= $id ?>">
      <h2><?= h(ve_clean_text($p['title'])) ?></h2>
      <p class="tag">Producto de la tienda de Chile (nombre, foto, descripción y stock se editan allá).</p>
      <div class="grid2">
        <div class="field"><label>Dirección (slug)</label><input type="text" name="slug" value="<?= h($vp['slug']) ?>"><small>Úsala igual a la de los 10 originales (ej. <code>office-2021-ltsc</code>) para conservar sus textos y requisitos.</small></div>
        <div class="field"><label>Nombre en la tienda de Venezuela (opcional)</label><input type="text" name="title" value="<?= h($vp['title']) ?>" placeholder="<?= h(ve_clean_text($p['title'])) ?>"></div>
        <div class="field"><label>Orden (menor = primero)</label><input type="number" name="sort_order" value="<?= (int) $vp['sort_order'] ?>"></div>
        <div class="field"><label>&nbsp;</label><label><input type="checkbox" name="active" value="1" <?= $vp['active'] ? 'checked' : '' ?>> Mostrar en la tienda de Venezuela</label></div>
      </div>
      <table class="t"><tr><th>Opción (Chile)</th><th>Precio CLP</th><th>Precio USD</th><th>Nombre a mostrar (opcional)</th></tr>
      <?php foreach ($vars as $v): $sug = ($clp > 0 && $v['price'] > 0) ? round($v['price'] / $clp, 2) : null; ?>
        <tr><td><?= h(ve_clean_text($v['title'])) ?></td><td>$<?= h(number_format((float) $v['price'], 0, ',', '.')) ?></td>
        <td><input type="text" name="usd[<?= (int) $v['id'] ?>]" value="<?= $v['price_usd'] !== null ? h(rtrim(rtrim(number_format((float) $v['price_usd'], 2, '.', ''), '0'), '.')) : '' ?>" placeholder="<?= $sug ? 'sug. ' . h($sug) : 'no se vende' ?>" style="width:110px;padding:7px 9px;border:1px solid var(--line);border-radius:8px"></td>
        <td><input type="text" name="label[<?= (int) $v['id'] ?>]" value="<?= h($v['label']) ?>" placeholder="<?= h(ve_clean_text($v['title'])) ?>" style="width:100%;padding:7px 9px;border:1px solid var(--line);border-radius:8px"></td></tr>
      <?php endforeach; ?></table>
      <p class="tag">Deja el precio USD vacío en las opciones que no quieras vender en Venezuela.</p>
      <button class="btn-a">Guardar</button></form>
    <?php admin_footer(); exit;
}

/* ── Listado ── */
$q = trim((string) ($_GET['q'] ?? ''));
$only = !empty($_GET['solo']);
$sql = "SELECT p.id, p.title, p.status, vp.slug, vp.active, vp.title AS vtitle,
          (SELECT COUNT(*) FROM product_variants pv JOIN ve_prices pr ON pr.variant_id = pv.id WHERE pv.product_id = p.id) AS priced,
          (SELECT MIN(pr.price_usd) FROM product_variants pv JOIN ve_prices pr ON pr.variant_id = pv.id WHERE pv.product_id = p.id) AS minusd
        FROM products p LEFT JOIN ve_products vp ON vp.product_id = p.id WHERE p.status = 'activo'";
$args = [];
if ($q !== '') { $sql .= ' AND p.title LIKE :q'; $args[':q'] = '%' . $q . '%'; }
if ($only) $sql .= ' AND vp.product_id IS NOT NULL';
$sql .= ' ORDER BY (vp.product_id IS NULL), vp.sort_order, p.title LIMIT 300';
$st = $pdo->prepare($sql); $st->execute($args); $rows = $st->fetchAll();
$nVe = (int) $pdo->query('SELECT COUNT(*) FROM ve_products')->fetchColumn();

// Sugerencias para importar los 10 productos originales
$suggest = [];
if (isset($_GET['importar'])) {
    $all = $pdo->query("SELECT id, title FROM products WHERE status = 'activo'")->fetchAll();
    foreach (ve_static() as $slug => $s) {
        $a = pv_tokens($s['title']); $best = null; $bs = 0;
        foreach ($all as $c) {
            $b = pv_tokens($c['title']); if (!$a || !$b) continue;
            $inter = count(array_intersect($a, $b)); $uni = count(array_unique(array_merge($a, $b)));
            $score = $uni ? $inter / $uni : 0;
            if ($score > $bs || ($score === $bs && $best && mb_strlen($c['title']) < mb_strlen($best['title']))) { $bs = $score; $best = $c; }
        }
        $suggest[$slug] = ['s' => $s, 'p' => $best, 'score' => $bs];
    }
    $cnt = []; foreach ($suggest as $r) if ($r['p']) $cnt[$r['p']['id']] = ($cnt[$r['p']['id']] ?? 0) + 1;
    foreach ($suggest as $k => $r) $suggest[$k]['dup'] = $r['p'] && $cnt[$r['p']['id']] > 1;
}
admin_header('Productos'); flash_show(); ?>
<?php if (isset($_GET['importar'])): ?>
<form method="post" class="box"><?= csrf_field() ?><input type="hidden" name="action" value="import">
  <h2>Importar los 10 productos originales</h2>
  <p class="tag">Busqué en la tienda de Chile el producto más parecido a cada uno. Desmarca los que no coincidan (los editas a mano después). Se cargan sus precios en USD actuales en las opciones cuyo nombre coincida (p. ej. "1 PC", "3 PC").</p>
  <table class="t"><tr><th></th><th>Producto actual de Venezuela</th><th>Producto encontrado en Chile</th><th>Parecido</th></tr>
  <?php foreach ($suggest as $slug => $r): ?><tr><td><input type="checkbox" name="m[<?= h($slug) ?>]" value="<?= (int) ($r['p']['id'] ?? 0) ?>" <?= $r['p'] && $r['score'] >= 0.7 && empty($r['dup']) ? 'checked' : '' ?> <?= $r['p'] ? '' : 'disabled' ?>></td>
    <td><?= h($r['s']['title']) ?></td><td><?= $r['p'] ? h(ve_clean_text($r['p']['title'])) : '—' ?></td><td><?= (int) round($r['score'] * 100) ?>%<?= !empty($r['dup']) ? ' <b style="color:#b45309">¡repetido!</b>' : '' ?></td></tr><?php endforeach; ?></table>
  <p><button class="btn-a">Importar seleccionados</button> <a class="btn-a ghost" href="productos.php">Cancelar</a></p></form>
<?php endif; ?>
<div class="toolbar"><form method="get" style="display:flex;gap:8px;flex:1"><input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar producto de la tienda de Chile…" style="flex:1;padding:8px 14px;border:1px solid var(--line);border-radius:20px">
  <label style="align-self:center;font-size:.85rem"><input type="checkbox" name="solo" value="1" <?= $only ? 'checked' : '' ?> onchange="this.form.submit()"> Solo los de Venezuela</label></form>
  <?php if (!$nVe): ?><a class="btn-a" href="?importar=1">Importar los 10 productos actuales</a><?php else: ?><a class="btn-a ghost" href="?importar=1">Importar los 10 originales</a><?php endif; ?></div>
<table class="t"><tr><th>Producto (Chile)</th><th>En Venezuela</th><th>Desde</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= h(ve_clean_text($r['title'])) ?></td>
<td><?php if ($r['slug'] === null): ?><span class="muted">—</span><?php else: ?><span class="pill <?= $r['active'] && $r['priced'] ? 'entregado' : 'pendiente' ?>"><?= !$r['priced'] ? 'Sin precios' : ($r['active'] ? 'Visible' : 'Oculto') ?></span> <span class="tag"><?= h($r['slug']) ?></span><?php endif; ?></td>
<td><?= $r['minusd'] !== null ? '$' . h(number_format((float) $r['minusd'], 2)) : '—' ?></td>
<td><a class="btn-a ghost" href="?id=<?= (int) $r['id'] ?>"><?= $r['slug'] === null ? 'Agregar' : 'Editar' ?></a></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="4" class="muted" style="text-align:center;padding:26px">Sin resultados.</td></tr><?php endif; ?></table>
<?php admin_footer();
