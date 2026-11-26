<?php
/** Campos del editor de la caja informativa (se usa en «Caja informativa» y en la edición de cada producto). */
function caja_fields(array $b, string $n = 'box', string $uid = 'cj'): void {
    $icons = ve_box_icons(); $opt = function (string $sel) use ($icons): string { $o = ''; foreach ($icons as $k => $l) $o .= '<option value="' . h($k) . '"' . ($k === $sel ? ' selected' : '') . '>' . h($l) . '</option>'; return $o; };
?>
<div class="cj" id="<?= h($uid) ?>">
  <div class="cj-g">
    <div class="field"><label>Botón de la izquierda</label><input type="text" name="<?= $n ?>[btn1]" maxlength="40" value="<?= h($b['btn1']) ?>"><small>Lleva a la pestaña «Cómo funciona». Vacío = no se muestra.</small></div>
    <div class="field"><label>Botón de WhatsApp</label><input type="text" name="<?= $n ?>[btn2]" maxlength="40" value="<?= h($b['btn2']) ?>"><small>Abre WhatsApp con el nombre del producto. Vacío = no se muestra.</small></div>
  </div>
  <div class="field"><label>Fila destacada (guía)</label>
    <div class="cj-g3"><input type="text" name="<?= $n ?>[g_t]" maxlength="80" value="<?= h($b['guide']['t']) ?>" placeholder="Título (ej. Guía de uso paso a paso)"><input type="text" name="<?= $n ?>[g_s]" maxlength="100" value="<?= h($b['guide']['s']) ?>" placeholder="Texto gris (ej. cómo se instala y activa)"><input type="text" name="<?= $n ?>[g_u]" maxlength="500" value="<?= h($b['guide']['u']) ?>" placeholder="Enlace: https://… o #proceso"></div>
    <small>Si dejas el título o el enlace vacío, la fila no se muestra. <code>#proceso</code> lleva a la pestaña «Cómo funciona».</small></div>
  <div class="field"><label>Secciones desplegables</label><div class="cj-items"></div><button type="button" class="btn-a ghost cj-add">+ Agregar sección</button></div>
  <div class="field"><label>Franja de confianza (hasta 4)</label><div class="cj-trust"></div></div>
  <script>
  (function () {
    var root = document.getElementById(<?= json_encode($uid) ?>), N = <?= json_encode($n) ?>, OPT = <?= json_encode(array_map(function ($k) use ($icons) { return [$k, $icons[$k]]; }, array_keys($icons)), JSON_UNESCAPED_UNICODE) ?>,
        ITEMS = <?= json_encode($b['items'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>, TRUST = <?= json_encode($b['trust'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    function sel(name, cur) { var s = document.createElement('select'); s.name = name; OPT.forEach(function (o) { var e = document.createElement('option'); e.value = o[0]; e.textContent = o[1]; if (o[0] === cur) e.selected = true; s.appendChild(e); }); return s; }
    function inp(name, val, ph, max) { var i = document.createElement('input'); i.type = 'text'; i.name = name; i.value = val || ''; i.placeholder = ph; i.maxLength = max; return i; }
    function item(d) {
      d = d || {i: 'info', t: '', b: ''}; var r = document.createElement('div'); r.className = 'cj-row';
      var top = document.createElement('div'); top.className = 'cj-top'; top.appendChild(sel(N + '[it_i][]', d.i)); top.appendChild(inp(N + '[it_t][]', d.t, 'Título de la sección', 80));
      var xb = document.createElement('div'); xb.className = 'cj-xb'; [['▲', -1], ['▼', 1], ['✕', 0]].forEach(function (b) { var bt = document.createElement('button'); bt.type = 'button'; bt.textContent = b[0]; bt.dataset.d = b[1]; xb.appendChild(bt); }); top.appendChild(xb);
      var ta = document.createElement('textarea'); ta.name = N + '[it_b][]'; ta.rows = 3; ta.maxLength = 1200; ta.placeholder = 'Texto que se muestra al desplegar (una línea por párrafo)'; ta.value = d.b || '';
      r.appendChild(top); r.appendChild(ta); root.querySelector('.cj-items').appendChild(r);
    }
    ITEMS.forEach(item);
    var tr = root.querySelector('.cj-trust'); for (var k = 0; k < 4; k++) { var d = TRUST[k] || {i: 'check', t: ''}, w = document.createElement('div'); w.className = 'cj-t'; w.appendChild(sel(N + '[tr_i][]', d.i)); w.appendChild(inp(N + '[tr_t][]', d.t, 'Texto corto', 30)); tr.appendChild(w); }
    root.querySelector('.cj-add').addEventListener('click', function () { item(); });
    root.querySelector('.cj-items').addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return; var r = b.closest('.cj-row'), d = +b.dataset.d, c = r.parentNode;
      if (d === 0) r.remove(); else if (d < 0 && r.previousElementSibling) c.insertBefore(r, r.previousElementSibling); else if (d > 0 && r.nextElementSibling) c.insertBefore(r.nextElementSibling, r);
    });
  })();
  </script>
</div>
<style>
  .cj input[type=text], .cj select, .cj textarea { padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; font-family: var(--sans); font-size: .88rem; background: #fff; box-sizing: border-box; }
  .cj-g { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; } .cj-g3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; } .cj-g3 input, .cj-g input { width: 100%; }
  .cj-row { border: 1px solid var(--line); border-radius: 10px; padding: 10px; margin-bottom: 8px; background: var(--surface-tint); }
  .cj-top { display: grid; grid-template-columns: 150px 1fr auto; gap: 8px; margin-bottom: 8px; align-items: center; } .cj-row textarea { width: 100%; }
  .cj-xb { display: flex; gap: 4px; } .cj-xb button { border: 1px solid var(--line); background: #fff; border-radius: 6px; width: 28px; height: 32px; cursor: pointer; font-size: .75rem; }
  .cj-trust { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; } .cj-t { display: grid; grid-template-columns: 130px 1fr; gap: 6px; }
  @media (max-width: 700px) { .cj-g, .cj-g3, .cj-trust { grid-template-columns: 1fr; } .cj-top { grid-template-columns: 1fr auto; } .cj-top select { grid-column: 1 / -1; } }
</style>
<?php }
