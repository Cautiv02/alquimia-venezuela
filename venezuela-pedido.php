<?php
/**
 * venezuela-pedido.php — recibe el pedido desde la ficha del producto (POST). Un producto por pedido, sin carrito.
 * Precio y tasa se leen SIEMPRE del servidor (nunca del navegador). Crea el pedido "pendiente", avisa al admin,
 * envía los datos de pago al cliente y lo lleva a venezuela-pago.php para subir su comprobante.
 */
require_once __DIR__ . '/venezuela-data.php';

function ve_order_fail(string $slug, string $msg): void {
    $back = $slug !== '' ? 'venezuela-producto.php?slug=' . urlencode($slug) . '&error=' . urlencode($msg) . '#buy-form' : './';
    header('Location: ' . $back);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ./'); exit; }
if (!empty($_POST['website'])) { http_response_code(204); exit; } // campo trampa para bots

$slug = (string) ($_POST['slug'] ?? '');
if (!ve_db_ready()) ve_order_fail($slug, 'La tienda está en mantenimiento. Escríbenos por WhatsApp y te atendemos.');

$catalog = ve_catalog();
$p = $catalog[$slug] ?? null;
if (!$p || !$p['pid']) ve_order_fail('', 'Ese producto ya no está disponible.');
if ($p['soldout']) ve_order_fail($slug, 'Este producto está agotado por ahora.');

$variant = null; $vid = (int) ($_POST['variant_id'] ?? 0);
foreach ($p['variants'] as $v) if ((int) $v['id'] === $vid) { $variant = $v; break; }
if (!$variant) ve_order_fail($slug, 'Elige una opción del producto.');
if (!$variant['avail']) ve_order_fail($slug, 'Esa opción está agotada.');

$method = (string) ($_POST['method'] ?? '');
if (!ve_method_enabled($method)) ve_order_fail($slug, 'Elige un medio de pago disponible.');
$name  = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')), 0, 120));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone = trim(mb_substr((string) ($_POST['phone'] ?? ''), 0, 40));
if (mb_strlen($name) < 2) ve_order_fail($slug, 'Escribe tu nombre.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) ve_order_fail($slug, 'Escribe un correo válido: ahí te enviamos los datos de pago y tu licencia.');
if (ve_phone_intl($phone) === '') ve_order_fail($slug, 'Escribe tu número de WhatsApp (ej. 0412 5859613).');

$rate = get_bcv_rate();
$amountBs = 0;
if ($method === 'pagomovil') {
    if ($rate <= 0) ve_order_fail($slug, 'No pudimos obtener la tasa BCV en este momento. Elige Binance o escríbenos por WhatsApp.');
    $amountBs = ve_bs_amount($variant['price'], $rate);
}

$pdo = ve_pdo();
$ipHash = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (VE_SECRET !== '' ? VE_SECRET : VE_DB_PASS)), 0, 16);
// Límite sencillo contra abuso: máximo 5 pedidos pendientes por dispositivo en la última hora
$cnt = $pdo->prepare("SELECT COUNT(*) FROM ve_orders WHERE ip_hash = :h AND status = 'pendiente' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$cnt->execute([':h' => $ipHash]);
if ((int) $cnt->fetchColumn() >= 5) ve_order_fail($slug, 'Tienes varios pedidos pendientes. Págalos o espera a que venzan antes de hacer otro, o escríbenos por WhatsApp.');

// Código único y pedido
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $orderId = 0; $code = '';
$pdo->beginTransaction();
try {
    // Reserva de stock (solo opciones con stock manual): evita vender de más si dos clientes piden a la vez
    if ($variant['stock'] !== null) {
        $reserve = $pdo->prepare('UPDATE product_variants SET stock_manual = stock_manual - 1 WHERE id = :v AND stock_manual IS NOT NULL AND stock_manual > 0');
        $reserve->execute([':v' => $vid]);
        if (!$reserve->rowCount()) throw new RuntimeException('agotado');
    }

    for ($try = 0; $try < 6 && !$orderId; $try++) {
        $code = 'VE-';
        for ($i = 0; $i < 4; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
        $code .= '-';
        for ($i = 0; $i < 4; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
        try {
            $pdo->prepare("INSERT INTO ve_orders (code, product_id, variant_id, product_title, variant_title, name, email, phone, method, price_usd, rate, amount_bs, status, ip_hash, created_at, updated_at)
                VALUES (:c,:p,:v,:pt,:vt,:n,:e,:ph,:m,:usd,:r,:bs,'pendiente',:ip,NOW(),NOW())")
                ->execute([':c' => $code, ':p' => $p['pid'], ':v' => $vid, ':pt' => mb_substr($p['title'], 0, 255), ':vt' => mb_substr($variant['label'], 0, 120), ':n' => $name, ':e' => $email,
                           ':ph' => $phone, ':m' => $method, ':usd' => $variant['price'], ':r' => $rate, ':bs' => $amountBs, ':ip' => $ipHash]);
            $orderId = (int) $pdo->lastInsertId();
        } catch (PDOException $e) { if ($e->getCode() !== '23000') throw $e; }
    }
    if (!$orderId) throw new RuntimeException('no se pudo generar un código único');
    $pdo->prepare('UPDATE ve_orders SET order_number = :n WHERE id = :id')->execute([':n' => 'VE-' . (1000 + $orderId), ':id' => $orderId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[ve] crear pedido: ' . $e->getMessage());
    ve_order_fail($slug, $e->getMessage() === 'agotado' ? 'Esa opción se acaba de agotar.' : 'No pudimos crear tu pedido. Inténtalo de nuevo en unos minutos o escríbenos por WhatsApp.');
}

$order = ve_order_by_id($orderId);
ve_notify('venta', 'Nuevo pedido ' . $order['order_number'] . ' — ' . $order['product_title'] . ' (' . $order['variant_title'] . ') — ' . ve_method_label($method) . ' — ' . ve_order_total_text($order) . ' — ' . $order['name'], $orderId);
ve_mail_order_created($order);

header('Location: venezuela-pago.php?c=' . urlencode($code));
exit;
