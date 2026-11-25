<?php
require_once __DIR__ . '/_boot.php';
if (!ve_configured() || VE_ADMIN_PASS_HASH === '') { header('Location: instalar.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fails = $_SESSION['fails'] ?? 0; $last = $_SESSION['fail_at'] ?? 0;
    if ($fails >= 5 && time() - $last < 300) { $err = 'Demasiados intentos. Espera 5 minutos.'; }
    elseif (hash_equals(VE_ADMIN_USER, (string) ($_POST['user'] ?? '')) && password_verify((string) ($_POST['pass'] ?? ''), VE_ADMIN_PASS_HASH)) {
        session_regenerate_id(true); $_SESSION['ve_ok'] = 1; $_SESSION['fails'] = 0; header('Location: index.php'); exit;
    } else { $_SESSION['fails'] = $fails + 1; $_SESSION['fail_at'] = time(); usleep(400000); $err = 'Usuario o clave incorrectos.'; }
}
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow"><title>Entrar · Admin Venezuela</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&family=Manrope:wght@400;600&display=swap" rel="stylesheet">
<?php include __DIR__ . '/../venezuela-styles.php'; ?></head><body>
<div class="page-narrow" style="max-width:420px"><div class="panel"><h1>Panel Venezuela</h1><p class="lead">Ingresa para ver tus pedidos.</p>
<?php if ($err): ?><div class="alert err"><?= h($err) ?></div><?php endif; ?>
<form method="post"><div class="field"><label>Usuario</label><input type="text" name="user" autocomplete="username" required></div>
<div class="field"><label>Clave</label><input type="password" name="pass" autocomplete="current-password" required style="width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:10px"></div>
<button class="btn-buy" type="submit">Entrar</button></form></div></div></body></html>
