<?php
/** ve-admin/_auth.php — protege cada página del panel. */
require_once __DIR__ . '/_boot.php';
if (!ve_configured() || VE_ADMIN_PASS_HASH === '') { header('Location: instalar.php'); exit; }
if (!ve_db_ready()) { http_response_code(500); exit('No se pudo conectar a la base de datos. Revisa config-ve.php o ejecuta de nuevo el instalador (borrando antes config-ve.php).'); }
if (empty($_SESSION['ve_ok'])) { header('Location: login.php'); exit; }
ve_maintenance_lazy();
