<?php
/**
 * ve-lib.php — núcleo de la tienda de Venezuela (PHP 7.4+).
 * Base de datos compartida con la tienda de Chile (products / product_variants) y tablas propias ve_*
 * para precios en USD, pedidos, comprobantes, reseñas y avisos. No toca ninguna tabla de pedidos de Chile.
 */
if (defined('VE_LIB_LOADED')) return;
define('VE_LIB_LOADED', 1);
date_default_timezone_set('America/Caracas'); // todo el sitio (y NOW() de MySQL, ver ve_pdo) en hora de Venezuela

if (is_file(__DIR__ . '/config-ve.php')) require_once __DIR__ . '/config-ve.php';
$ve_defaults = [
    'VE_DB_HOST' => 'localhost', 'VE_DB_NAME' => '', 'VE_DB_USER' => '', 'VE_DB_PASS' => '',
    'VE_IMAGES_BASE' => 'https://alquimia-digital.cl/',   // de dónde salen las fotos de la tienda de Chile
    'VE_NOTIFY_EMAIL' => '', 'VE_SMTP_HOST' => '', 'VE_SMTP_PORT' => 465, 'VE_SMTP_USER' => '', 'VE_SMTP_PASS' => '',
    'VE_MAIL_FROM' => '', 'VE_MAIL_FROM_NAME' => 'Alquimia Digital Venezuela',
    'VE_ADMIN_USER' => 'admin', 'VE_ADMIN_PASS_HASH' => '', 'VE_SECRET' => '',
    'VE_SITE_HOST' => '', 'TRUSTOO_PUBLIC_TOKEN' => '', 'TRUSTOO_PRIVATE_TOKEN' => '',
];
foreach ($ve_defaults as $k => $v) { if (!defined($k)) define($k, $v); }
unset($ve_defaults, $k, $v);

// Evita que el navegador, LiteSpeed o Cloudflare guarden copias viejas de las páginas dinámicas (precios, estado de pedidos)
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-LiteSpeed-Cache-Control: no-cache');
}

if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}

/** URL base de esta carpeta (se detecta sola; funciona en cualquier carpeta del hosting). */
function ve_site_url(): string {
    static $u = null; if ($u !== null) return $u;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $host = VE_SITE_HOST !== '' ? VE_SITE_HOST : ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host);
    $dir  = str_replace('\\', '/', realpath(__DIR__) ?: __DIR__);
    $root = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/') : '';
    if ($root !== '' && strpos($dir, $root) === 0) $path = substr($dir, strlen($root));
    else $path = preg_replace('#/ve-admin$#', '', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/'));
    $path = ($path === '/' || $path === '.') ? '' : rtrim($path, '/');
    return $u = ($https ? 'https' : 'http') . '://' . $host . $path;
}

/* ───────────── Base de datos ───────────── */
function ve_configured(): bool { return VE_DB_NAME !== '' && VE_DB_USER !== ''; }

function ve_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . VE_DB_HOST . ';dbname=' . VE_DB_NAME . ';charset=utf8mb4', VE_DB_USER, VE_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec("SET time_zone = '-04:00'"); // hora de Venezuela para NOW()
    }
    return $pdo;
}

/** true si hay base configurada, conecta y las tablas existen. Nunca lanza excepción. */
function ve_db_ready(): bool {
    static $ok = null; if ($ok !== null) return $ok;
    if (!ve_configured()) return $ok = false;
    try { ve_ensure_schema(ve_pdo()); return $ok = true; }
    catch (Throwable $e) { error_log('[ve] base no disponible: ' . $e->getMessage()); return $ok = false; }
}

function ve_ensure_schema(PDO $pdo): void {
    $marker = __DIR__ . '/ve-schema.ok';
    if (is_file($marker) && trim((string) @file_get_contents($marker)) === '4') return;
    $t = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $pdo->exec("CREATE TABLE IF NOT EXISTS ve_settings (k VARCHAR(60) NOT NULL PRIMARY KEY, v TEXT NOT NULL)$t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ve_products (
        product_id INT UNSIGNED NOT NULL PRIMARY KEY, slug VARCHAR(80) NOT NULL, title VARCHAR(255) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 100, UNIQUE KEY uq_slug (slug))$t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ve_prices (variant_id INT UNSIGNED NOT NULL PRIMARY KEY, price_usd DECIMAL(10,2) NOT NULL, label VARCHAR(120) NULL)$t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ve_orders (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(20) NOT NULL DEFAULT '', code VARCHAR(16) NOT NULL,
        product_id INT UNSIGNED NOT NULL, variant_id INT UNSIGNED NOT NULL,
        product_title VARCHAR(255) NOT NULL, variant_title VARCHAR(120) NOT NULL DEFAULT '',
        name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL, phone VARCHAR(40) NOT NULL DEFAULT '',
        method VARCHAR(20) NOT NULL, price_usd DECIMAL(10,2) NOT NULL, rate DECIMAL(14,4) NOT NULL DEFAULT 0, amount_bs BIGINT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'pendiente', reminder_sent_at DATETIME NULL, review_requested_at DATETIME NULL,
        ip_hash CHAR(16) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
        paid_at DATETIME NULL, delivered_at DATETIME NULL, cancel_reason VARCHAR(120) NULL,
        UNIQUE KEY uq_code (code), KEY (status, created_at), KEY (email), KEY (ip_hash, created_at))$t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ve_proofs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, order_id INT UNSIGNED NOT NULL,
        file_name VARCHAR(80) NULL, file_mime VARCHAR(10) NULL, sender VARCHAR(150) NULL, reference VARCHAR(150) NULL,
        note TEXT NULL, created_at DATETIME NOT NULL, KEY (order_id))$t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ve_notifications (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, type VARCHAR(20) NOT NULL, message VARCHAR(400) NOT NULL,
        order_id INT UNSIGNED NULL, is_read TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, KEY (is_read, id))$t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ve_reviews (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, order_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL,
        product_title VARCHAR(255) NOT NULL, author VARCHAR(120) NOT NULL, rating TINYINT UNSIGNED NOT NULL, content TEXT NOT NULL,
        verified TINYINT(1) NOT NULL DEFAULT 1, published TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL,
        UNIQUE KEY uq_order_prod (order_id, product_id), KEY (product_id, published), KEY (published, created_at))$t");
    // Carrito: un pedido puede tener varios productos; una reseña por producto y pedido
    $pdo->exec("CREATE TABLE IF NOT EXISTS ve_order_items (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, order_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL, variant_id INT UNSIGNED NOT NULL,
        product_title VARCHAR(255) NOT NULL, variant_title VARCHAR(120) NOT NULL DEFAULT '', unit_usd DECIMAL(10,2) NOT NULL, qty TINYINT UNSIGNED NOT NULL DEFAULT 1,
        KEY (order_id))$t");
    try { $pdo->exec('ALTER TABLE ve_reviews DROP INDEX uq_order'); } catch (Throwable $e) { /* ya no existe */ }
    try { $pdo->exec('ALTER TABLE ve_reviews ADD UNIQUE KEY uq_order_prod (order_id, product_id)'); } catch (Throwable $e) { /* ya existe */ }
    try { $pdo->exec('ALTER TABLE ve_products ADD COLUMN image VARCHAR(200) NULL'); } catch (Throwable $e) { /* ya existe */ }
    @file_put_contents($marker, '4');
}

/* ───────────── Ajustes (editables en el panel) ───────────── */
function ve_setting_defaults(): array {
    return [
        'rate_mode'        => 'auto',   // auto = BCV desde internet con respaldo manual | manual = siempre la tasa manual
        'rate_manual'      => '',       // Bs por 1 USD (respaldo)
        'rate_last'        => '', 'rate_last_at' => '', 'rate_alert' => '',
        'pay_hours'        => '4',      // horas para pagar antes de la cancelación automática
        'binance_enabled'  => '0', 'binance_details' => '', 'binance_qr' => '',
        'binance_note'     => 'Paga desde la app de Binance (Pay → Enviar) con el ID de arriba, escribe tu número de pedido en la nota y sube aquí tu comprobante.',
        'pagomovil_enabled'=> '0', 'pagomovil_details' => '',
        'pagomovil_note'   => 'Haz el PagoMóvil por el monto exacto en bolívares y sube aquí tu comprobante (captura) con la referencia.',
        'expiry_last_run'  => '',
        'clp_per_usd'      => '',       // solo para sugerir precios en USD desde los precios en CLP (opcional)
    ];
}
function ve_settings_all(bool $fresh = false): array {
    static $cache = null; if ($cache !== null && !$fresh) return $cache;
    $cache = ve_setting_defaults();
    try {
        foreach (ve_pdo()->query('SELECT k, v FROM ve_settings')->fetchAll() as $r) if (array_key_exists($r['k'], $cache)) $cache[$r['k']] = (string) $r['v'];
    } catch (Throwable $e) { }
    return $cache;
}
function ve_setting(string $k): string { $a = ve_settings_all(); return (string) ($a[$k] ?? ''); }
function ve_settings_save(array $values): void {
    $st = ve_pdo()->prepare('INSERT INTO ve_settings (k, v) VALUES (:k, :v) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    foreach ($values as $k => $v) $st->execute([':k' => $k, ':v' => (string) $v]);
    ve_settings_all(true);
}
function ve_pay_hours(): int { return max(1, min(72, (int) ve_setting('pay_hours') ?: 4)); }
function ve_method_enabled(string $m): bool {
    if ($m === 'binance')   return ve_setting('binance_enabled') === '1' && trim(ve_setting('binance_details')) !== '';
    if ($m === 'pagomovil') return ve_setting('pagomovil_enabled') === '1' && trim(ve_setting('pagomovil_details')) !== '';
    return false;
}
function ve_method_label(string $m): string { return $m === 'binance' ? 'Binance Pay (USDT)' : ($m === 'pagomovil' ? 'PagoMóvil (Bs)' : $m); }

/* ───────────── Teléfonos y textos ───────────── */
/** Teléfono venezolano a formato internacional para wa.me ('' si no sirve). */
function ve_phone_intl(string $raw): string {
    $d = preg_replace('/\D+/', '', $raw);
    if ($d === '') return '';
    if (strpos($d, '00') === 0) $d = substr($d, 2);
    if (strlen($d) === 11 && $d[0] === '0') $d = '58' . substr($d, 1);   // 04125859613
    elseif (strlen($d) === 10 && $d[0] === '4') $d = '58' . $d;          // 4125859613
    return strlen($d) >= 8 ? $d : '';
}
function ve_clean_text($t): string {
    return trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]/u', '', (string) $t)));
}
function ve_token(string $what, int $id): string {
    $secret = (VE_SECRET !== '' ? VE_SECRET : VE_DB_PASS . '|ve') . '|' . $what;
    return substr(hash_hmac('sha256', $what . ':' . $id, $secret), 0, 20);
}
function ve_cron_key(): string { return substr(hash_hmac('sha256', 'cron', (VE_SECRET !== '' ? VE_SECRET : VE_DB_PASS . '|ve')), 0, 24); }

/* ───────────── Correo (PHPMailer + SMTP del cPanel) ───────────── */
function ve_mail_ready(): bool { return VE_SMTP_HOST !== '' && VE_SMTP_USER !== '' && VE_SMTP_PASS !== ''; }

function ve_send_mail(string $to, string $subject, string $html, string $replyTo = ''): bool {
    if (!ve_mail_ready() || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    try {
        require_once __DIR__ . '/PHPMailer/Exception.php';
        require_once __DIR__ . '/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/SMTP.php';
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP(); $m->Host = VE_SMTP_HOST; $m->SMTPAuth = true; $m->Username = VE_SMTP_USER; $m->Password = VE_SMTP_PASS;
        $m->SMTPSecure = ((int) VE_SMTP_PORT === 465) ? 'ssl' : 'tls'; $m->Port = (int) VE_SMTP_PORT; $m->CharSet = 'UTF-8'; $m->Timeout = 12;
        $m->setFrom(VE_MAIL_FROM !== '' ? VE_MAIL_FROM : VE_SMTP_USER, VE_MAIL_FROM_NAME);
        $m->addAddress($to);
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $m->addReplyTo($replyTo);
        $m->isHTML(true); $m->Subject = $subject; $m->Body = $html; $m->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
        $m->send();
        return true;
    } catch (Throwable $e) { error_log('[ve] correo a ' . $to . ': ' . $e->getMessage()); return false; }
}

/** Envuelve el contenido en la plantilla de correo de la tienda. */
function ve_mail_html(string $inner): string {
    return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#222;line-height:1.55">'
        . '<p style="font-size:18px;font-weight:bold;color:#3E5C76;margin:0 0 14px;">Alquimia Digital · Venezuela</p>' . $inner
        . '<p style="color:#888;font-size:12px;margin-top:24px;">Si necesitas ayuda, escríbenos por WhatsApp: +58 412-5859613.</p></div>';
}
function ve_mail_button(string $url, string $label): string {
    return '<p style="margin:18px 0;"><a href="' . h($url) . '" style="background:#B9862A;color:#fff;padding:11px 20px;border-radius:6px;text-decoration:none;font-weight:bold;">' . h($label) . '</a></p>';
}

/** Aviso al admin: queda en el panel y llega por correo a VE_NOTIFY_EMAIL. */
function ve_notify(string $type, string $message, ?int $orderId = null, bool $email = true): void {
    try {
        ve_pdo()->prepare('INSERT INTO ve_notifications (type, message, order_id, created_at) VALUES (:t,:m,:o,NOW())')
            ->execute([':t' => $type, ':m' => mb_substr($message, 0, 400), ':o' => $orderId]);
    } catch (Throwable $e) { error_log('[ve] aviso: ' . $e->getMessage()); }
    if ($email && VE_NOTIFY_EMAIL !== '') {
        foreach (preg_split('/[\s,;]+/', VE_NOTIFY_EMAIL) as $addr) {
            if ($addr === '') continue;
            $link = $orderId ? '<p><a href="' . h(ve_site_url() . '/ve-admin/pedido.php?id=' . $orderId) . '">Abrir el pedido en el panel</a></p>' : '';
            ve_send_mail($addr, '[Venezuela] ' . $message, ve_mail_html('<p>' . h($message) . '</p>' . $link));
        }
    }
}

/* ───────────── Carrito (cookie con opción:cantidad; los precios SIEMPRE se leen de la base) ───────────── */
function ve_cart_raw(): array {
    $out = [];
    foreach (explode('|', (string) ($_COOKIE['ve_cart'] ?? '')) as $part) {
        if (!preg_match('/^(\d{1,9}):(\d{1,2})$/', $part, $m) || (int) $m[1] < 1) continue;
        $out[(int) $m[1]] = max(1, min(10, (int) $m[2]));
        if (count($out) >= 30) break;
    }
    return $out;
}
function ve_cart_save(array $cart): void {
    $parts = [];
    foreach ($cart as $vid => $q) if ((int) $q > 0 && (int) $vid > 0) $parts[] = (int) $vid . ':' . min(10, (int) $q);
    $val = implode('|', $parts);
    $_COOKIE['ve_cart'] = $val;
    if (headers_sent()) return;
    $path = rtrim((string) (parse_url(ve_site_url(), PHP_URL_PATH) ?: ''), '/') . '/';
    setcookie('ve_cart', $val, ['expires' => $val === '' ? time() - 3600 : time() + 30 * 86400, 'path' => $path,
        'secure' => strpos(ve_site_url(), 'https://') === 0, 'httponly' => true, 'samesite' => 'Lax']);
}
function ve_cart_count(): int { return array_sum(ve_cart_raw()); }

/* ───────────── Ítems de un pedido ───────────── */
/** Líneas del pedido. Los pedidos anteriores al carrito no tienen ítems: se arma uno con los datos del propio pedido. */
function ve_order_items(array $o): array {
    try {
        $st = ve_pdo()->prepare('SELECT * FROM ve_order_items WHERE order_id = :o ORDER BY id'); $st->execute([':o' => $o['id']]);
        $rows = $st->fetchAll(); if ($rows) return $rows;
    } catch (Throwable $e) { }
    return [['order_id' => $o['id'], 'product_id' => $o['product_id'], 'variant_id' => $o['variant_id'], 'product_title' => $o['product_title'],
             'variant_title' => $o['variant_title'], 'unit_usd' => $o['price_usd'], 'qty' => 1]];
}
function ve_items_text(array $items): string {
    $t = [];
    foreach ($items as $it) $t[] = $it['product_title'] . ($it['variant_title'] !== '' ? ' (' . $it['variant_title'] . ')' : '') . ((int) $it['qty'] > 1 ? ' ×' . (int) $it['qty'] : '');
    return implode('; ', $t);
}
/** Tabla de productos para los correos. */
function ve_items_html(array $o): string {
    $rows = '';
    foreach (ve_order_items($o) as $it) {
        $rows .= '<tr><td style="padding:6px 0;border-bottom:1px solid #eee">' . h($it['product_title']) . ($it['variant_title'] !== '' ? ' — ' . h($it['variant_title']) : '')
            . ((int) $it['qty'] > 1 ? ' <b>×' . (int) $it['qty'] . '</b>' : '') . '</td><td style="padding:6px 0;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">'
            . number_format((float) $it['unit_usd'] * (int) $it['qty'], 2) . ' USD</td></tr>';
    }
    return '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:8px 0">' . $rows . '</table>';
}

/* ───────────── Pedidos ───────────── */
function ve_order_by_code(string $code): ?array {
    $st = ve_pdo()->prepare('SELECT * FROM ve_orders WHERE code = :c LIMIT 1'); $st->execute([':c' => strtoupper(trim($code))]);
    $r = $st->fetch(); return $r ?: null;
}
function ve_order_by_id(int $id): ?array {
    $st = ve_pdo()->prepare('SELECT * FROM ve_orders WHERE id = :i LIMIT 1'); $st->execute([':i' => $id]);
    $r = $st->fetch(); return $r ?: null;
}
function ve_pay_url(array $o): string { return ve_site_url() . '/venezuela-pago.php?c=' . urlencode($o['code']); }
function ve_order_total_text(array $o): string {
    return $o['method'] === 'pagomovil'
        ? 'Bs. ' . number_format((float) $o['amount_bs'], 0, ',', '.') . ' (' . number_format((float) $o['price_usd'], 2) . ' USD a Bs. ' . number_format((float) $o['rate'], 2, ',', '.') . ' por USD)'
        : number_format((float) $o['price_usd'], 2) . ' USDT';
}
function ve_order_expires_at(array $o): int { return strtotime($o['created_at']) + ve_pay_hours() * 3600; }
function ve_order_has_proof(int $orderId): bool {
    $st = ve_pdo()->prepare('SELECT COUNT(*) FROM ve_proofs WHERE order_id = :o'); $st->execute([':o' => $orderId]);
    return (int) $st->fetchColumn() > 0;
}

/** Datos de pago del medio elegido, en HTML (para correo). */
function ve_payment_block_html(array $o): string {
    $key = $o['method'] === 'binance' ? 'binance_details' : 'pagomovil_details';
    return '<div style="background:#f6f2e6;padding:12px 14px;border-radius:8px;">' . nl2br(h(ve_setting($key)))
        . '<br><br><b>Monto: ' . h(ve_order_total_text($o)) . '</b><br><b>Referencia: ' . h($o['order_number']) . '</b></div>';
}

function ve_mail_order_created(array $o): void {
    $inner = '<p>Hola ' . h($o['name']) . ', recibimos tu pedido <b>' . h($o['order_number']) . '</b>:</p>' . ve_items_html($o)
        . '<p>Para activarlo, paga por <b>' . h(ve_method_label($o['method'])) . '</b> y sube tu comprobante:</p>' . ve_payment_block_html($o)
        . ve_mail_button(ve_pay_url($o), 'Subir mi comprobante')
        . '<p>Tienes <b>' . ve_pay_hours() . ' horas</b> para pagar; pasado ese plazo sin comprobante el pedido se cancela solo.</p>';
    ve_send_mail($o['email'], 'Tu pedido ' . $o['order_number'] . ' — datos de pago', ve_mail_html($inner), VE_NOTIFY_EMAIL);
}

/** Cancela un pedido pendiente y devuelve las unidades al stock manual. true si lo canceló. */
function ve_cancel_order(int $orderId, string $reason, bool $email = true): bool {
    $pdo = ve_pdo();
    $up = $pdo->prepare("UPDATE ve_orders SET status = 'anulado', cancel_reason = :r, updated_at = NOW() WHERE id = :id AND status IN ('pendiente')");
    $up->execute([':r' => mb_substr($reason, 0, 120), ':id' => $orderId]);
    if (!$up->rowCount()) return false;
    $o = ve_order_by_id($orderId);
    try {
        $back = $pdo->prepare('UPDATE product_variants SET stock_manual = stock_manual + :q WHERE id = :v AND stock_manual IS NOT NULL');
        foreach (ve_order_items($o) as $it) $back->execute([':q' => max(1, (int) $it['qty']), ':v' => (int) $it['variant_id']]);
    } catch (Throwable $e) { }
    if ($email && $o) {
        ve_send_mail($o['email'], 'Pedido ' . $o['order_number'] . ' cancelado', ve_mail_html(
            '<p>Hola ' . h($o['name']) . ', tu pedido <b>' . h($o['order_number']) . '</b> (' . h($o['product_title']) . ') fue cancelado: ' . h($reason) . '.</p>'
            . '<p>Si ya pagaste o quieres retomarlo, escríbenos por WhatsApp con tu comprobante y lo reactivamos. También puedes hacer un pedido nuevo cuando quieras.</p>'
            . ve_mail_button(ve_site_url() . '/', 'Ver el catálogo')), VE_NOTIFY_EMAIL);
    }
    return true;
}

/** Mensaje de recordatorio (correo + texto para WhatsApp). */
function ve_reminder_text(array $o): string {
    $left = max(0, (int) ceil((ve_order_expires_at($o) - time()) / 3600));
    return 'Hola ' . $o['name'] . ', te recordamos que tu pedido ' . $o['order_number'] . ' (' . $o['product_title'] . ') está pendiente de pago: ' . ve_order_total_text($o)
        . '. Sube tu comprobante aquí: ' . ve_pay_url($o) . ($left > 0 ? ' (se cancela automáticamente en unas ' . $left . ' h).' : '.');
}
function ve_send_reminder(int $orderId): bool {
    $o = ve_order_by_id($orderId);
    if (!$o || $o['status'] !== 'pendiente') return false;
    $ok = ve_send_mail($o['email'], 'Recordatorio: tu pedido ' . $o['order_number'] . ' está pendiente de pago', ve_mail_html(
        '<p>Hola ' . h($o['name']) . ', tu pedido <b>' . h($o['order_number']) . '</b> (' . h($o['product_title']) . ') sigue pendiente de pago.</p>'
        . ve_payment_block_html($o) . ve_mail_button(ve_pay_url($o), 'Subir mi comprobante')
        . '<p>Si no recibimos el pago, el pedido se cancela automáticamente.</p>'), VE_NOTIFY_EMAIL);
    if ($ok) ve_pdo()->prepare('UPDATE ve_orders SET reminder_sent_at = NOW() WHERE id = :id')->execute([':id' => $orderId]);
    return $ok;
}
function ve_whatsapp_reminder_url(array $o): string {
    $p = ve_phone_intl($o['phone']);
    return $p === '' ? '' : 'https://wa.me/' . $p . '?text=' . rawurlencode(ve_reminder_text($o));
}

/**
 * Recordatorio automático (una vez, a mitad del plazo) y cancelación automática (al vencer el plazo).
 * No cancela pedidos con comprobante enviado: esos los revisas tú. Devuelve [recordatorios, cancelados].
 */
function ve_run_maintenance(): array {
    $sent = 0; $cancelled = 0;
    try {
        $pdo = ve_pdo(); $hours = ve_pay_hours();
        $half = max(1, (int) floor($hours * 30));  // minutos = horas * 60 / 2
        $rem = $pdo->query("SELECT o.id FROM ve_orders o WHERE o.status = 'pendiente' AND o.reminder_sent_at IS NULL
            AND o.created_at <= DATE_SUB(NOW(), INTERVAL $half MINUTE) AND o.created_at > DATE_SUB(NOW(), INTERVAL $hours HOUR)
            AND NOT EXISTS (SELECT 1 FROM ve_proofs p WHERE p.order_id = o.id) ORDER BY o.id LIMIT 30")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rem as $id) {
            if (ve_send_reminder((int) $id)) $sent++;
            else $pdo->prepare('UPDATE ve_orders SET reminder_sent_at = NOW() WHERE id = :id')->execute([':id' => $id]); // sin correo válido: no reintentar
        }
        $exp = $pdo->query("SELECT o.id, o.order_number FROM ve_orders o WHERE o.status = 'pendiente'
            AND o.created_at <= DATE_SUB(NOW(), INTERVAL $hours HOUR)
            AND NOT EXISTS (SELECT 1 FROM ve_proofs p WHERE p.order_id = o.id) ORDER BY o.id LIMIT 50")->fetchAll();
        foreach ($exp as $o) {
            if (ve_cancel_order((int) $o['id'], 'no recibimos el pago dentro de ' . $hours . ' horas')) {
                $cancelled++;
                ve_notify('alerta', 'Pedido ' . $o['order_number'] . ' cancelado automáticamente: sin pago en ' . $hours . ' h.', (int) $o['id'], false);
            }
        }
    } catch (Throwable $e) { error_log('[ve] mantenimiento: ' . $e->getMessage()); }
    return [$sent, $cancelled];
}
/** Lo ejecuta, como mucho cada 5 minutos, quien abra la tienda o el panel (además del cron de cPanel). Nunca rompe la página. */
function ve_maintenance_lazy(): void {
    static $ran = false; if ($ran) return; $ran = true;
    try {
        if (!ve_db_ready()) return;
        if ((int) ve_setting('expiry_last_run') > time() - 300) return;
        ve_settings_save(['expiry_last_run' => (string) time()]);
        ve_run_maintenance();
    } catch (Throwable $e) { }
}

/* ───────────── Comprobantes ───────────── */
function ve_proofs_dir(): string {
    $dir = __DIR__ . '/comprobantes';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
    return $dir;
}

/* ───────────── Reseñas propias ───────────── */
function ve_review_link(int $orderId, int $productId = 0): string { return ve_site_url() . '/venezuela-resena.php?o=' . $orderId . '&k=' . ve_token('resena', $orderId) . ($productId ? '&p=' . $productId : ''); }
function ve_request_review(int $orderId): bool {
    $o = ve_order_by_id($orderId);
    if (!$o || !in_array($o['status'], ['pagado', 'entregado'], true)) return false;
    $ok = ve_send_mail($o['email'], '¿Qué tal tu ' . $o['product_title'] . '? Cuéntanos', ve_mail_html(
        '<p>Hola ' . h($o['name']) . ', gracias por tu compra. ¿Nos dejas tu opinión? Tu reseña aparecerá con la insignia de <b>compra verificada</b> y nos ayuda mucho.</p>'
        . ve_mail_button(ve_review_link($orderId), 'Dejar mi opinión')), VE_NOTIFY_EMAIL);
    if ($ok) ve_pdo()->prepare('UPDATE ve_orders SET review_requested_at = NOW() WHERE id = :id')->execute([':id' => $orderId]);
    return $ok;
}
function ve_verified_badge(): string {
    return '<span class="rv-verified"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#2A7D74"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>Compra verificada</span>';
}
function ve_review_stats(): array {
    static $c = null; if ($c !== null) return $c; $c = [];
    try { foreach (ve_pdo()->query('SELECT product_id, COUNT(*) n, AVG(rating) a FROM ve_reviews WHERE published = 1 GROUP BY product_id')->fetchAll() as $r) $c[(int) $r['product_id']] = ['n' => (int) $r['n'], 'avg' => (float) $r['a']]; } catch (Throwable $e) { }
    return $c;
}

/* ───────────── Correos de estado ───────────── */
function ve_mail_paid(array $o): bool {
    return ve_send_mail($o['email'], 'Pago confirmado — pedido ' . $o['order_number'], ve_mail_html(
        '<p>Hola ' . h($o['name']) . ', ¡recibimos tu pago del pedido <b>' . h($o['order_number']) . '</b>!</p>' . ve_items_html($o)
        . '<p>Estamos preparando tu entrega. Te la enviamos por WhatsApp y correo en cuanto esté lista.</p>'
        . ve_mail_button(ve_pay_url($o), 'Ver el estado de mi pedido')), VE_NOTIFY_EMAIL);
}
function ve_mail_delivered(array $o, string $message = ''): bool {
    $extra = trim($message) !== '' ? '<div style="background:#f6f2e6;padding:12px 14px;border-radius:8px;">' . nl2br(h($message)) . '</div>' : '';
    return ve_send_mail($o['email'], 'Tu pedido ' . $o['order_number'] . ' fue entregado', ve_mail_html(
        '<p>Hola ' . h($o['name']) . ', tu pedido <b>' . h($o['order_number']) . '</b> ya fue entregado:</p>' . ve_items_html($o) . '<p> Revisa tu WhatsApp: ahí tienes tu licencia con las instrucciones.</p>'
        . $extra . '<p>Si necesitas ayuda para instalar o activar, escríbenos: el soporte está incluido.</p>'
        . ve_mail_button(ve_review_link((int) $o['id']), 'Dejar mi opinión (compra verificada)')), VE_NOTIFY_EMAIL);
}
