<?php
/**
 * Portada: ordena los productos de la página principal (arrastrando o con flechas) y crea secciones.
 * Una sola pantalla y un solo botón "Guardar". Los productos en "Ocultos" no se muestran en la tienda.
 */
require_once __DIR__ . '/_auth.php';
$pdo = ve_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $secs = ve_sections(false); $secIds = array_map('intval', array_column($secs, 'id'));
    $valid = array_map('intval', $pdo->query('SELECT product_id FROM ve_products')->fetchAll(PDO::FETCH_COLUMN));
    try {
        $pdo->beginTransaction();
        // Borrar una sección: sus productos pasan a la primera de las que quedan
        $del = (int) ($_POST['delete_section'] ?? 0);
        if ($del && in_array($del, $secIds, true) && count($secIds) > 1) {
            $rest = array_values(array_diff($secIds, [$del]));
            $pdo->prepare('UPDATE ve_products SET section_id = :n WHERE section_id = :d')->execute([':n' => $rest[0], ':d' => $del]);
            $pdo->prepare('DELETE FROM ve_sections WHERE id = :d')->execute([':d' => $del]);
            $_POST['s'][$rest[0]] = array_merge((array) ($_POST['s'][$rest[0]] ?? []), (array) ($_POST['s'][$del] ?? []));
            unset($_POST['s'][$del], $_POST['title'][$del], $_POST['note'][$del], $_POST['active'][$del]);
            $_POST['sec_order'] = array_values(array_diff((array) ($_POST['sec_order'] ?? []), [(string) $del]));
            $secIds = $rest;
        }
        // Textos, visibilidad y orden de las secciones
        $up = $pdo->prepare('UPDATE ve_sections SET title = :t, note = :n, active = :a, sort_order = :o WHERE id = :i');
        $order = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['sec_order'] ?? [])), function ($x) use ($secIds) { return in_array($x, $secIds, true); })));
        foreach ($secIds as $sid) if (!in_array($sid, $order, true)) $order[] = $sid;
        foreach ($order as $pos => $sid) {
            $t = trim(mb_substr((string) ($_POST['title'][$sid] ?? ''), 0, 120));
            $up->execute([':t' => $t !== '' ? $t : 'Sección', ':n' => trim(mb_substr((string) ($_POST['note'][$sid] ?? ''), 0, 1000)), ':a' => isset($_POST['active'][$sid]) ? 1 : 0, ':o' => ($pos + 1) * 10, ':i' => $sid]);
        }
        // Sección nueva
        $newT = trim(mb_substr((string) ($_POST['new_title'] ?? ''), 0, 120));
        if ($newT !== '') {
            $pdo->prepare('INSERT INTO ve_sections (title, note, sort_order, active) VALUES (:t,:n,:o,1)')
                ->execute([':t' => $newT, ':n' => trim(mb_substr((string) ($_POST['new_note'] ?? ''), 0, 1000)), ':o' => (count($order) + 1) * 10]);
        }
        // Productos: sección y orden
        $seen = [];
        $setH = $pdo->prepare('UPDATE ve_products SET active = 0 WHERE product_id = :p');
        $setS = $pdo->prepare('UPDATE ve_products SET active = 1, section_id = :s, sort_order = :o WHERE product_id = :p');
        foreach ((array) ($_POST['hidden'] ?? []) as $pid) { $pid = (int) $pid; if (in_array($pid, $valid, true) && !isset($seen[$pid])) { $setH->execute([':p' => $pid]); $seen[$pid] = true; } }
        foreach ((array) ($_POST['s'] ?? []) as $sid => $pids) {
            $sid = (int) $sid; if (!in_array($sid, $secIds, true)) continue;
            foreach (array_values((array) $pids) as $i => $pid) { $pid = (int) $pid; if (in_array($pid, $valid, true) && !isset($seen[$pid])) { $setS->execute([':s' => $sid, ':o' => ($i + 1) * 10, ':p' => $pid]); $seen[$pid] = true; } }
        }
        $pdo->commit();
        flash_set($newT !== '' ? 'Guardado. Se creó la sección «' . $newT . '»: arrastra productos hacia ella.' : 'Guardado. La portada ya se ve con este orden.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[ve] portada: ' . $e->getMessage());
        flash_set('No se pudo guardar. Inténtalo de nuevo.', true);
    }
    header('Location: portada.php'); exit;
}

$secs = ve_sections(false); $secIds = array_map('intval', array_column($secs, 'id')); $first = $secIds[0] ?? 0;
$rows = $pdo->query('SELECT vp.product_id, vp.slug, vp.section_id, vp.active, vp.title AS vtitle, p.title AS ptitle FROM ve_products vp JOIN products p ON p.id = vp.product_id ORDER BY vp.sort_order, p.title')->fetchAll();
$bySec = array_fill_keys($secIds, []); $hidden = [];
foreach ($rows as $r) {
    $r['name'] = ve_clean_text($r['vtitle'] !== null && $r['vtitle'] !== '' ? $r['vtitle'] : $r['ptitle']);
    if (!(int) $r['active']) { $hidden[] = $r; continue; }
    $sid = in_array((int) $r['section_id'], $secIds, true) ? (int) $r['section_id'] : $first;
    $bySec[$sid][] = $r;
}
function portada_row(array $r, string $field): void { ?>
  <li class="prow" draggable="true" data-pid="<?= (int) $r['product_id'] ?>"><input type="hidden" name="<?= h($field) ?>" value="<?= (int) $r['product_id'] ?>">
    <span class="grip" title="Arrastra para mover">⠿</span><span class="pname"><?= h($r['name']) ?><small><?= h($r['slug']) ?></small></span>
    <span class="pbtn"><button type="button" class="mv" data-d="-1" title="Subir">▲</button><button type="button" class="mv" data-d="1" title="Bajar">▼</button>
    <select class="moveto" aria-label="Mover a"></select></span></li>
<?php }
admin_header('Portada'); flash_show(); ?>
<style>
  .sec { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 18px; }
  .sec.off { opacity: .65; }
  .sec-head { display: grid; grid-template-columns: 1fr auto; gap: 10px 14px; align-items: start; margin-bottom: 12px; }
  .sec-head input[type=text], .sec-head textarea { width: 100%; padding: 8px 11px; border: 1px solid var(--line); border-radius: 8px; font-family: var(--sans); font-size: .9rem; background: #fff; }
  .sec-head input.st { font-family: var(--serif); font-weight: 600; font-size: 1.05rem; }
  .sec-tools { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
  .sec-tools label { font-size: .82rem; white-space: nowrap; }
  .plist { list-style: none; margin: 0; padding: 6px; min-height: 52px; border: 2px dashed var(--line); border-radius: 10px; background: var(--surface-tint); }
  .plist.over { border-color: var(--gold); background: #fdf8ea; }
  .plist:empty::before { content: 'Arrastra productos aquí'; display: block; padding: 14px; color: var(--ash); font-size: .85rem; text-align: center; }
  .prow { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 8px 10px; margin: 6px 0; cursor: grab; }
  .prow.drag { opacity: .4; }
  .grip { color: var(--ash); font-size: 1.1rem; user-select: none; }
  .pname { flex: 1; font-size: .9rem; font-weight: 600; } .pname small { display: block; color: var(--ash); font-weight: 400; font-size: .74rem; }
  .pbtn { display: flex; gap: 4px; align-items: center; }
  .pbtn button { border: 1px solid var(--line); background: #fff; border-radius: 6px; width: 28px; height: 28px; cursor: pointer; font-size: .7rem; }
  .pbtn select { padding: 5px 6px; border: 1px solid var(--line); border-radius: 6px; font-size: .78rem; max-width: 150px; background: #fff; }
  .savebar { position: sticky; bottom: 0; background: var(--bg); padding: 12px 0; border-top: 1px solid var(--line); display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
</style>
<p class="muted" style="margin:0 0 16px">Arrastra los productos para ordenarlos o pasarlos a otra sección (en el celular usa las flechas y el selector «Mover a»). Las secciones también se pueden subir y bajar. Al terminar pulsa <b>Guardar</b>.
  Los productos nuevos se agregan desde <a href="productos.php">Productos</a>.</p>
<form method="post" id="pf"><?= csrf_field() ?>
<div id="secs">
<?php foreach ($secs as $s): $sid = (int) $s['id']; ?>
  <div class="sec<?= $s['active'] ? '' : ' off' ?>" data-sid="<?= $sid ?>"><input type="hidden" name="sec_order[]" value="<?= $sid ?>">
    <div class="sec-head">
      <input class="st" type="text" name="title[<?= $sid ?>]" value="<?= h($s['title']) ?>" maxlength="120" aria-label="Título de la sección">
      <div class="sec-tools"><button type="button" class="btn-a ghost secmv" data-d="-1" title="Subir sección">▲</button><button type="button" class="btn-a ghost secmv" data-d="1" title="Bajar sección">▼</button>
        <label><input type="checkbox" name="active[<?= $sid ?>]" value="1" <?= $s['active'] ? 'checked' : '' ?>> Mostrar</label>
        <?php if (count($secs) > 1): ?><button type="submit" name="delete_section" value="<?= $sid ?>" class="btn-a red" onclick="return confirm('¿Eliminar esta sección? Sus productos pasan a la primera sección.')">Eliminar</button><?php endif; ?></div>
      <textarea name="note[<?= $sid ?>]" rows="2" maxlength="1000" placeholder="Texto bajo el título (opcional, una línea por párrafo)" style="grid-column:1 / -1"><?= h($s['note']) ?></textarea>
    </div>
    <ul class="plist" data-name="s[<?= $sid ?>][]"><?php foreach ($bySec[$sid] as $r) portada_row($r, 's[' . $sid . '][]'); ?></ul>
  </div>
<?php endforeach; ?>
</div>
<div class="sec" style="background:var(--surface-tint)"><b>Productos ocultos</b> <span class="tag">(no se muestran en la portada; siguen existiendo)</span>
  <ul class="plist" data-name="hidden[]" id="hidden" style="margin-top:10px"><?php foreach ($hidden as $r) portada_row($r, 'hidden[]'); ?></ul></div>
<div class="box"><h2>Nueva sección</h2>
  <div class="grid2"><div class="field"><label>Título</label><input type="text" name="new_title" maxlength="120" placeholder="Ej.: Antivirus, Packs, Novedades…"></div>
  <div class="field"><label>Texto bajo el título (opcional)</label><input type="text" name="new_note" maxlength="1000"></div></div>
  <p class="tag" style="margin:0">Se crea al pulsar Guardar, abajo del todo; después arrastras productos hacia ella.</p></div>
<div class="savebar"><button class="btn-a" type="submit">Guardar</button><a class="btn-a ghost" href="../" target="_blank">Ver la tienda</a></div>
</form>
<script>
(function () {
  var form = document.getElementById('pf'), drag = null;
  function lists() { return Array.prototype.slice.call(document.querySelectorAll('.plist')); }
  function secTitle(ul) { var s = ul.closest('.sec'); var t = s.querySelector('input.st'); return t ? (t.value || 'Sección') : 'Ocultos'; }
  function refresh() {
    lists().forEach(function (ul) {
      Array.prototype.forEach.call(ul.children, function (li) {
        li.querySelector('input[type=hidden]').name = ul.dataset.name;
        var sel = li.querySelector('.moveto'), cur = lists().indexOf(ul);
        sel.innerHTML = '<option value="">Mover a…</option>';
        lists().forEach(function (o, i) { if (i !== cur) { var op = document.createElement('option'); op.value = i; op.textContent = o.id === 'hidden' ? 'Ocultos' : secTitle(o); sel.appendChild(op); } });
      });
    });
  }
  form.addEventListener('dragstart', function (e) { var li = e.target.closest && e.target.closest('.prow'); if (!li) return; drag = li; li.classList.add('drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', li.dataset.pid); } catch (x) {} });
  form.addEventListener('dragend', function () { if (drag) drag.classList.remove('drag'); lists().forEach(function (u) { u.classList.remove('over'); }); drag = null; refresh(); });
  form.addEventListener('dragover', function (e) {
    var ul = e.target.closest && e.target.closest('.plist'); if (!ul || !drag) return; e.preventDefault(); ul.classList.add('over');
    var after = null, y = e.clientY;
    Array.prototype.forEach.call(ul.children, function (c) { if (c === drag) return; var b = c.getBoundingClientRect(); if (y > b.top + b.height / 2) after = c; });
    if (after) after.after(drag); else ul.insertBefore(drag, ul.firstChild);
  });
  form.addEventListener('dragleave', function (e) { var ul = e.target.closest && e.target.closest('.plist'); if (ul && !ul.contains(e.relatedTarget)) ul.classList.remove('over'); });
  form.addEventListener('drop', function (e) { e.preventDefault(); });
  form.addEventListener('click', function (e) {
    var b = e.target.closest('.mv'); if (b) { var li = b.closest('.prow'), d = +b.dataset.d; if (d < 0 && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling); if (d > 0 && li.nextElementSibling) li.parentNode.insertBefore(li.nextElementSibling, li); return; }
    var sb = e.target.closest('.secmv'); if (sb) { var s = sb.closest('.sec'), dd = +sb.dataset.d; if (dd < 0 && s.previousElementSibling) s.parentNode.insertBefore(s, s.previousElementSibling); if (dd > 0 && s.nextElementSibling) s.parentNode.insertBefore(s.nextElementSibling, s); refresh(); }
  });
  form.addEventListener('change', function (e) {
    if (e.target.classList.contains('moveto') && e.target.value !== '') { var li = e.target.closest('.prow'); lists()[+e.target.value].appendChild(li); refresh(); }
  });
  form.addEventListener('input', function (e) { if (e.target.classList.contains('st')) refresh(); });
  refresh();
})();
</script>
<?php admin_footer();
