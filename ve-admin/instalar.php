<?php
/**
 * ve-admin/instalar.php — instalador de una sola vez. Pide los datos en el navegador, prueba la conexión, escribe
 * config-ve.php por ti, crea las tablas y deja el panel listo. Al terminar se borra solo.
 * Para volver a usarlo: borra config-ve.php (y súbelo de nuevo).
 */
require_once __DIR__ . '/_boot.php';
if (ve_configured() && VE_ADMIN_PASS_HASH !== '') {
    ?><!DOCTYPE html><meta charset="UTF-8"><title>Instalador</title><body style="font-family:sans-serif;max-width:520px;margin:60px auto"><h2>Ya está instalado ✅</h2><p>Entra al <a href="login.php">panel</a>. Para repetir la instalación, borra <code>config-ve.php</code> de la carpeta.</p></body><?php exit;
}
$v = ['db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'admin_user' => 'admin',
      'smtp_host' => '', 'smtp_port' => '465', 'smtp_user' => '', 'mail_from' => '',
      'notify' => '', 'images' => 'https://alquimia-digital.cl/', 'tt_pub' => '', 'tt_priv' => ''];
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) if (isset($_POST[$k])) $v[$k] = trim((string) $_POST[$k]);
    $dbPass = (string) ($_POST['db_pass'] ?? ''); $smtpPass = (string) ($_POST['smtp_pass'] ?? ''); $adm = (string) ($_POST['admin_pass'] ?? ''); $adm2 = (string) ($_POST['admin_pass2'] ?? '');
    if (strlen($adm) < 8) $err = 'La clave del panel debe tener al menos 8 caracteres.';
    elseif ($adm !== $adm2) $err = 'Las dos claves del panel no coinciden.';
    elseif ($v['db_name'] === '' || $v['db_user'] === '') $err = 'Completa el nombre y el usuario de la base de datos.';
    else {
        try {
            $pdo = new PDO('mysql:host=' . $v['db_host'] . ';dbname=' . $v['db_name'] . ';charset=utf8mb4', $v['db_user'], $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            foreach (['products', 'product_variants'] as $t) if (!$pdo->query("SHOW TABLES LIKE '$t'")->fetch()) throw new RuntimeException("No encuentro la tabla $t: ¿es la base de la tienda de Chile?");
            @unlink(__DIR__ . '/../ve-schema.ok');
            ve_ensure_schema($pdo);
            $cfg = [
                'VE_DB_HOST' => $v['db_host'], 'VE_DB_NAME' => $v['db_name'], 'VE_DB_USER' => $v['db_user'], 'VE_DB_PASS' => $dbPass,
                'VE_IMAGES_BASE' => rtrim($v['images'], '/') . '/', 'VE_NOTIFY_EMAIL' => $v['notify'],
                'VE_SMTP_HOST' => $v['smtp_host'], 'VE_SMTP_PORT' => (int) $v['smtp_port'], 'VE_SMTP_USER' => $v['smtp_user'], 'VE_SMTP_PASS' => $smtpPass,
                'VE_MAIL_FROM' => $v['mail_from'], 'VE_MAIL_FROM_NAME' => 'Alquimia Digital Venezuela',
                'VE_ADMIN_USER' => $v['admin_user'] ?: 'admin', 'VE_ADMIN_PASS_HASH' => password_hash($adm, PASSWORD_DEFAULT), 'VE_SECRET' => bin2hex(random_bytes(24)),
                'TRUSTOO_PUBLIC_TOKEN' => $v['tt_pub'], 'TRUSTOO_PRIVATE_TOKEN' => $v['tt_priv'],
            ];
            $out = "<?php\n// config-ve.php — generado por el instalador. Credenciales de la tienda de Venezuela. No lo compartas ni lo subas a repositorios.\n";
            foreach ($cfg as $k => $val) $out .= "define('$k', " . var_export($val, true) . ");\n";
            if (@file_put_contents(__DIR__ . '/../config-ve.php', $out) === false) throw new RuntimeException('No pude escribir config-ve.php en la carpeta del sitio (revisa permisos de la carpeta).');
            @chmod(__DIR__ . '/../config-ve.php', 0640);
            @unlink(__FILE__); // el instalador se borra solo
            header('Location: login.php'); exit;
        } catch (Throwable $e) { $err = 'No se pudo completar: ' . $e->getMessage(); }
    }
}
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow"><title>Instalar · Venezuela</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&family=Manrope:wght@400;600&display=swap" rel="stylesheet">
<?php include __DIR__ . '/../venezuela-styles.php'; ?></head><body>
<div class="page-narrow"><div class="panel"><h1>Instalar la tienda de Venezuela</h1>
<p class="lead">Una sola vez. Usa la <b>misma base de datos que la tienda de Chile</b> (así los productos no se cargan dos veces). Con estos datos creo <code>config-ve.php</code> por ti.</p>
<?php if ($err): ?><div class="alert err"><?= h($err) ?></div><?php endif; ?>
<form method="post" autocomplete="off">
<h3>Base de datos (la de Chile)</h3>
<div class="field"><label>Servidor</label><input type="text" name="db_host" value="<?= h($v['db_host']) ?>"></div>
<div class="field"><label>Nombre de la base</label><input type="text" name="db_name" value="<?= h($v['db_name']) ?>"></div>
<div class="field"><label>Usuario</label><input type="text" name="db_user" value="<?= h($v['db_user']) ?>"></div>
<div class="field"><label>Clave</label><input type="password" name="db_pass" style="width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:10px"><small>Los mismos DB_USER / DB_PASS del config.php de Chile. Si el usuario de esa base solo está permitido para otra carpeta, en cPanel → Bases de datos MySQL asegúrate de que tenga todos los privilegios.</small></div>
<h3>Panel de administración</h3>
<div class="field"><label>Usuario</label><input type="text" name="admin_user" value="<?= h($v['admin_user']) ?>"></div>
<div class="field"><label>Clave nueva (mín. 8 caracteres)</label><input type="password" name="admin_pass" style="width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:10px"></div>
<div class="field"><label>Repite la clave</label><input type="password" name="admin_pass2" style="width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:10px"></div>
<h3>Correo (avisos y mensajes a clientes)</h3>
<div class="field"><label>Servidor SMTP</label><input type="text" name="smtp_host" value="<?= h($v['smtp_host']) ?>"></div>
<div class="field"><label>Puerto (465 = SSL, 587 = TLS)</label><input type="text" name="smtp_port" value="<?= h($v['smtp_port']) ?>"></div>
<div class="field"><label>Usuario SMTP</label><input type="text" name="smtp_user" value="<?= h($v['smtp_user']) ?>"></div>
<div class="field"><label>Clave del correo</label><input type="password" name="smtp_pass" style="width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:10px"></div>
<div class="field"><label>Remitente</label><input type="text" name="mail_from" value="<?= h($v['mail_from']) ?>"></div>
<div class="field"><label>Correo(s) donde recibes los avisos (separa con coma)</label><input type="text" name="notify" value="<?= h($v['notify']) ?>"></div>
<h3>Otros (opcional)</h3>
<div class="field"><label>Dirección de la tienda de Chile (para las fotos de los productos)</label><input type="text" name="images" value="<?= h($v['images']) ?>"></div>
<div class="field"><label>Trustoo — token público (déjalo vacío si no lo usas)</label><input type="text" name="tt_pub" value="<?= h($v['tt_pub']) ?>"></div>
<div class="field"><label>Trustoo — token privado</label><input type="text" name="tt_priv" value="<?= h($v['tt_priv']) ?>"></div>
<button class="btn-buy" type="submit">Probar conexión e instalar</button></form></div></div></body></html>
