<?php
/**
 * venezuela-pedido.php — convierte el carrito en un pedido (POST desde venezuela-carrito.php).
 * Varios productos, un solo pago. Precios, stock y tasa se leen SIEMPRE del servidor (nunca del navegador).
 * Crea el pedido "pendiente", reserva el stock, avisa al admin, envía los datos de pago al cliente
 * y lo lleva a venezuela-pago.php para subir su comprobante.
 */
require_once __DIR__ . '/venezuela-data.php';

function ve_order_fail(string $msg): void {
    header('Location: venezuela-carrito.php?error=' . urlencode($msg));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: venezuela-carrito.php'); exit; }
if (!empty($_POST['website'])) { http_response_code(204); exit; } // campo trampa para bots
if (!ve_db_ready()) ve_order_fail('La tienda está en mantenimiento. Escríbenos por WhatsApp y te atendemos.');

$cartData = ve_cart_lines();
$lines = $cartData['lines']; $totalUsd = $cartData['total'];
if (!$lines) ve_order_fail('Tu carrito está vacío o los productos ya no están disponibles.');

$method = (string) ($_POST['method'] ?? '');
if (!ve_method_enabled($method)) ve_order_fail('Elige un medio de pago disponible.');
$name  = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')), 0, 120));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone = trim(mb_substr((string) ($_POST['phone'] ?? ''), 0, 40));
if (mb_strlen($name) < 2) ve_order_fail('Escribe tu nombre.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) ve_order_fail('Escribe un correo válido: ahí te enviamos los datos de pago y tus licencias.');
if (ve_phone_intl($phone) === '') ve_order_fail('Escribe tu número de WhatsApp (ej. 0412 5859613).');

$rate = get_bcv_rate();
$amountBs = 0;
if ($method === 'pagomovil') {
    if ($rate <= 0) ve_order_fail('No pudimos obtener la tasa BCV en este momento. Elige Binance o escríbenos por WhatsApp.');
    $amountBs = ve_bs_amount($totalUsd, $rate); // un solo redondeo sobre el total del carrito
}

$pdo = ve_pdo();
$ipHash = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (VE_SECRET !== '' ? VE_SECRET : VE_DB_PASS)), 0, 16);
// Límite sencillo contra abuso: máximo 5 pedidos pendientes por dispositivo en la última hora
$cnt = $pdo->prepare("SELECT COUNT(*) FROM ve_orders WHERE ip_hash = :h AND status = 'pendiente' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$cnt->execute([':h' => $ipHash]);
if ((int) $cnt->fetchColumn() >= 5) ve_order_fail('Tienes varios pedidos pendientes. Págalos o espera a que venzan antes de hacer otro, o escríbenos por WhatsApp.');

// Resumen para las columnas del pedido (el detalle completo vive en ve_order_items)
$first = $lines[0];
if (count($lines) === 1) { $sumTitle = $first['title']; $sumVariant = $first['label'] . ($first['qty'] > 1 ? ' ×' . $first['qty'] : ''); }
else { $sumTitle = $first['title'] . ' (+' . (count($lines) - 1) . ' más)'; $sumVariant = array_sum(array_column($lines, 'qty')) . ' licencias'; }

// Código único y pedido
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $orderId = 0; $code = '';
$pdo->beginTransaction();
try {
    // Reserva de stock (solo opciones con stock manual): evita vender de más si dos clientes piden a la vez
    $reserve = $pdo->prepare('UPDATE product_variants SET stock_manual = stock_manual - :q WHERE id = :v AND stock_manual IS NOT NULL AND stock_manual >= :q2');
    foreach ($lines as $l) {
        if ($l['stock'] === null) continue;
        $reserve->execute([':q' => $l['qty'], ':q2' => $l['qty'], ':v' => $l['vid']]);
        if (!$reserve->rowCount()) throw new RuntimeException('agotado:' . $l['title']);
    }

    for ($try = 0; $try < 6 && !$orderId; $try++) {
        $code = 'VE-';
        for ($i = 0; $i < 4; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
        $code .= '-';
        for ($i = 0; $i < 4; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
        try {
            $pdo->prepare("INSERT INTO ve_orders (code, product_id, variant_id, product_title, variant_title, name, email, phone, method, price_usd, rate, amount_bs, status, ip_hash, created_at, updated_at)
                VALUES (:c,:p,:v,:pt,:vt,:n,:e,:ph,:m,:usd,:r,:bs,'pendiente',:ip,NOW(),NOW())")
                ->execute([':c' => $code, ':p' => $first['pid'], ':v' => $first['vid'], ':pt' => mb_substr($sumTitle, 0, 255), ':vt' => mb_substr($sumVariant, 0, 120), ':n' => $name, ':e' => $email,
                           ':ph' => $phone, ':m' => $method, ':usd' => $totalUsd, ':r' => $rate, ':bs' => $amountBs, ':ip' => $ipHash]);
            $orderId = (int) $pdo->lastInsertId();
        } catch (PDOException $e) { if ($e->getCode() !== '23000') throw $e; }
    }
    if (!$orderId) throw new RuntimeException('no se pudo generar un código único');
    $pdo->prepare('UPDATE ve_orders SET order_number = :n WHERE id = :id')->execute([':n' => 'VE-' . (1000 + $orderId), ':id' => $orderId]);
    $ins = $pdo->prepare('INSERT INTO ve_order_items (order_id, product_id, variant_id, product_title, variant_title, unit_usd, qty) VALUES (:o,:p,:v,:pt,:vt,:u,:q)');
    foreach ($lines as $l) $ins->execute([':o' => $orderId, ':p' => $l['pid'], ':v' => $l['vid'], ':pt' => mb_substr($l['title'], 0, 255), ':vt' => mb_substr($l['label'], 0, 120), ':u' => $l['unit'], ':q' => $l['qty']]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[ve] crear pedido: ' . $e->getMessage());
    $msg = $e->getMessage();
    ve_order_fail(strpos($msg, 'agotado:') === 0 ? 'Se acaba de agotar: ' . substr($msg, 8) . '. Ajusta tu carrito e inténtalo de nuevo.'
        : 'No pudimos crear tu pedido. Inténtalo de nuevo en unos minutos o escríbenos por WhatsApp.');
}

ve_cart_save([]); // el carrito ya se convirtió en pedido
$order = ve_order_by_id($orderId);
ve_notify('venta', 'Nuevo pedido ' . $order['order_number'] . ' — ' . ve_items_text(ve_order_items($order)) . ' — ' . ve_method_label($method) . ' — ' . ve_order_total_text($order) . ' — ' . $order['name'], $orderId);
ve_mail_order_created($order);

header('Location: venezuela-pago.php?c=' . urlencode($code));
exit;
