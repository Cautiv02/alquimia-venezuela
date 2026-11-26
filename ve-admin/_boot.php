<?php
/** ve-admin/_boot.php — sesión, CSRF, avisos y cabecera/pie del panel (lo usan login, instalador y el resto). */
require_once __DIR__ . '/../venezuela-data.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']), 'path' => '/']);
    session_name('ve_admin');
    session_start();
}
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Sesión expirada. Vuelve atrás, recarga la página e inténtalo de nuevo.'); }
}
function flash_set(string $msg, bool $err = false): void { $_SESSION['flash'] = [$msg, $err]; }
function flash_show(): void {
    if (!empty($_SESSION['flash'])) { [$m, $e] = $_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="flash' . ($e ? ' err' : '') . '">' . h($m) . '</div>'; }
}
function ve_fmt_dt(?string $d): string { return $d ? date('d/m/Y H:i', strtotime($d)) : '—'; }

function admin_header(string $title): void {
    $att = 0; try { $att = (int) ve_pdo()->query("SELECT COUNT(*) FROM ve_orders o WHERE o.status = 'pendiente' AND EXISTS (SELECT 1 FROM ve_proofs p WHERE p.order_id = o.id)")->fetchColumn(); } catch (Throwable $e) { }
    $rv = 0; try { $rv = (int) ve_pdo()->query('SELECT COUNT(*) FROM ve_reviews WHERE published = 0')->fetchColumn(); } catch (Throwable $e) { }
    ?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow">
<title>Admin Venezuela · <?= h($title) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php include __DIR__ . '/../venezuela-styles.php'; ?>
<style>
  .adm { max-width: 1100px; margin: 0 auto; padding: 24px 20px 60px; }
  .adm-top { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
  .adm-top a { color: var(--text-dim); text-decoration: none; margin-right: 16px; font-size: .92rem; }
  .adm-top a:hover { color: var(--gold); }
  .badge { background: var(--gold); color: #fff; border-radius: 10px; padding: 1px 8px; font-size: .75rem; }
  table.t { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
  table.t th, table.t td { text-align: left; padding: 11px 13px; border-bottom: 1px solid var(--line); font-size: .88rem; vertical-align: top; }
  table.t th { color: var(--ash); font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; background: var(--surface-tint); }
  .pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .74rem; font-weight: 600; color: #fff; background: var(--ash); }
  .pill.pagado, .pill.entregado { background: var(--teal); } .pill.pendiente { background: var(--gold); } .pill.anulado { background: #8a4b4b; } .pill.revisar { background: #3E5C76; }
  .box { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
  .box h2 { font-family: var(--serif); font-size: 1.1rem; margin: 0 0 12px; }
  .btn-a { background: var(--gold); color: #FFFBF3; border: none; padding: 9px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: var(--sans); font-size: .88rem; text-decoration: none; display: inline-block; }
  .btn-a.ghost { background: transparent; color: var(--text); border: 1px solid var(--line); } .btn-a.red { background: #8a4b4b; } .btn-a.green { background: var(--teal); }
  .flash { background: var(--surface-tint); border: 1px solid rgba(42,125,116,.5); border-radius: 10px; padding: 12px 16px; margin-bottom: 18px; } .flash.err { border-color: rgba(138,75,75,.6); background: #fdeaea; }
  .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; } @media (max-width: 800px) { .grid2 { grid-template-columns: 1fr; } }
  .row-hl { background: #fdf8ea; } .tag { font-size: .78rem; color: var(--ash); } .adm .field input[type=text], .adm .field input[type=email], .adm .field input[type=password], .adm .field input[type=number], .adm .field textarea, .adm .field select { width: 100%; padding: 9px 11px; border: 1px solid var(--line); border-radius: 8px; font-family: var(--sans); background: #fff; }
  .actions form { display: inline; } .muted { color: var(--ash); } .toolbar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; align-items: center; }
  .toolbar a.f { padding: 6px 13px; border: 1px solid var(--line); border-radius: 20px; text-decoration: none; font-size: .82rem; color: var(--text-dim); background: #fff; } .toolbar a.f.on { background: var(--gold); border-color: var(--gold); color: #fff; }
  .proof-img { max-width: 100%; max-height: 420px; border: 1px solid var(--line); border-radius: 10px; }
</style></head><body>
<div class="adm">
  <div class="adm-top">
    <div><strong style="font-family:var(--serif);font-size:1.2rem;">Panel · Venezuela</strong></div>
    <div><a href="index.php">Pedidos<?= $att ? ' <span class="badge">' . $att . '</span>' : '' ?></a><a href="portada.php">Portada</a><a href="productos.php">Productos</a><a href="resenas.php">Reseñas<?= $rv ? ' <span class="badge">' . $rv . '</span>' : '' ?></a><a href="ajustes.php">Ajustes</a><a href="../" target="_blank">Ver tienda</a><a href="logout.php">Salir</a></div>
  </div>
<?php }
function admin_footer(): void { echo '</div></body></html>'; }
