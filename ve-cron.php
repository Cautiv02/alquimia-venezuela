<?php
/**
 * ve-cron.php?k=CLAVE — para la tarea programada de cPanel (cada 10 o 15 minutos): envía los recordatorios de pago
 * y cancela los pedidos vencidos. La clave se ve en el panel (Ajustes). Sin cron, igual se ejecuta cuando alguien visita la tienda.
 */
require_once __DIR__ . '/ve-lib.php';
header('Content-Type: text/plain; charset=utf-8');
if (!ve_db_ready() || !hash_equals(ve_cron_key(), (string) ($_GET['k'] ?? ''))) { http_response_code(403); echo 'no'; exit; }
[$r, $c] = ve_run_maintenance();
ve_settings_save(['expiry_last_run' => (string) time()]);
echo "ok recordatorios=$r cancelados=$c";
