<?php
/**
 * venezuela-data.php
 * ---------------------
 * Datos y funciones compartidas por las páginas de la tienda de Venezuela: catálogo (desde la base de datos de la
 * tienda de Chile + precios en USD), tasa BCV con respaldo manual, WhatsApp, horario y reseñas.
 * Si la base de datos aún no está configurada, usa el catálogo fijo de ve-static.php (pedido por WhatsApp, como antes).
 */
require_once __DIR__ . '/ve-lib.php';

define('WHATSAPP_NUMBER', '584125859613'); // sin el + al inicio
define('BCV_CACHE_FILE', __DIR__ . '/bcv-rate-cache.json');
define('BCV_API_URL', 'https://bcv.today/api/v1/rate.json');
define('BCV_API_URL_2', 'https://ve.dolarapi.com/v1/dolares/oficial'); // segunda fuente (campo "promedio")

/* ───────────── Tasa BCV ───────────── */
function ve_http_json(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $resp = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200 || !$resp) return null;
    $d = json_decode($resp, true);
    return is_array($d) ? $d : null;
}

/**
 * Tasa Bs por USD y de dónde salió: ['rate' => float, 'source' => api|cache|ultima|manual|ninguna, 'at' => texto].
 * Orden: modo manual (si el panel lo fuerza) → caché de hoy → internet (2 fuentes) → última tasa guardada → tasa manual.
 */
function get_bcv_rate_info(): array {
    static $info = null; if ($info !== null) return $info;
    $db = ve_db_ready();
    $manual = $db ? (float) str_replace(',', '.', preg_replace('/[^0-9,.]/', '', ve_setting('rate_manual'))) : 0.0;
    if ($db && ve_setting('rate_mode') === 'manual' && $manual > 0) return $info = ['rate' => $manual, 'source' => 'manual', 'at' => ''];

    $today = date('Y-m-d');
    $cached = is_file(BCV_CACHE_FILE) ? json_decode((string) @file_get_contents(BCV_CACHE_FILE), true) : null;
    if (is_array($cached) && ($cached['cached_date'] ?? '') === $today && !empty($cached['rate'])) {
        return $info = ['rate' => (float) $cached['rate'], 'source' => 'cache', 'at' => (string) ($cached['fetched_at'] ?? '')];
    }
    $prev = is_array($cached) && !empty($cached['rate']) ? (float) $cached['rate'] : ($db ? (float) ve_setting('rate_last') : 0.0);

    $new = 0.0;
    $d = ve_http_json(BCV_API_URL);
    if ($d && !empty($d['USD'])) $new = (float) $d['USD'];
    if ($new <= 0) { $d = ve_http_json(BCV_API_URL_2); if ($d && !empty($d['promedio'])) $new = (float) $d['promedio']; }

    // Protección: una tasa que se aleja más de 60 % de la anterior se considera dato dañado
    if ($new > 0 && $prev > 0 && abs($new - $prev) / $prev > 0.6) {
        if ($db) { try { ve_settings_save(['rate_alert' => 'La tasa recibida de internet (' . $new . ') difiere demasiado de la anterior (' . $prev . ') y se ignoró. Revísala en Ajustes.']); } catch (Throwable $e) { } }
        $new = 0.0;
    }
    if ($new > 0) {
        $at = date('Y-m-d H:i:s');
        @file_put_contents(BCV_CACHE_FILE, json_encode(['rate' => $new, 'cached_date' => $today, 'fetched_at' => $at]));
        if ($db) { try { ve_settings_save(['rate_last' => (string) $new, 'rate_last_at' => $at, 'rate_alert' => '']); } catch (Throwable $e) { } }
        return $info = ['rate' => $new, 'source' => 'api', 'at' => $at];
    }
    if ($prev > 0) return $info = ['rate' => $prev, 'source' => 'ultima', 'at' => is_array($cached) ? (string) ($cached['fetched_at'] ?? '') : ($db ? ve_setting('rate_last_at') : '')];
    if ($manual > 0) return $info = ['rate' => $manual, 'source' => 'manual', 'at' => ''];
    return $info = ['rate' => 0.0, 'source' => 'ninguna', 'at' => ''];
}
function get_bcv_rate(): float { return (float) get_bcv_rate_info()['rate']; }

function usd($n) { return '$' . number_format((float) $n, 2) . ' <span class="usd-tag">USD/USDT</span>'; }
/** Monto en bolívares, redondeado al múltiplo de 10 más cercano (0 si no hay tasa). */
function ve_bs_amount($usdAmount, $rate): int { return $rate > 0 ? (int) (round(($usdAmount * $rate) / 10) * 10) : 0; }
function bs($usdAmount, $rate) {
    if ($rate <= 0) return null;
    return 'Bs. ' . number_format(ve_bs_amount($usdAmount, $rate), 0, ',', '.');
}
function wa_link($text) { return 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode($text); }

// === HORARIO DE ATENCIÓN (hora de Venezuela) ===
define('BUSINESS_HOURS', [
    1 => ['08:00', '19:00'], 2 => ['08:00', '19:00'], 3 => ['08:00', '19:00'], 4 => ['08:00', '19:00'], 5 => ['08:00', '19:00'],
    6 => ['09:00', '15:00'], // domingo (0) no aparece = cerrado
]);
function is_business_open(): bool {
    $tz = new DateTimeZone('America/Caracas');
    $now = new DateTime('now', $tz);
    $dow = (int) $now->format('w');
    if (!isset(BUSINESS_HOURS[$dow])) return false;
    [$open, $close] = BUSINESS_HOURS[$dow];
    $openTime = DateTime::createFromFormat('H:i', $open, $tz)->setDate((int) $now->format('Y'), (int) $now->format('m'), (int) $now->format('d'));
    $closeTime = DateTime::createFromFormat('H:i', $close, $tz)->setDate((int) $now->format('Y'), (int) $now->format('m'), (int) $now->format('d'));
    return $now >= $openTime && $now <= $closeTime;
}

/* ───────────── Reseñas de Trustoo (tienda de Chile) ───────────── */
define('TRUSTOO_BASE_URL', 'https://rapi.trustoo.io');
define('TRUSTOO_CACHE_FILE', __DIR__ . '/trustoo-reviews-cache.json');
define('TRUSTOO_CACHE_HOURS', 6);

function get_trustoo_reviews(int $displayCount = 9): array {
    if (TRUSTOO_PUBLIC_TOKEN === '' || TRUSTOO_PRIVATE_TOKEN === '') {
        if (is_file(TRUSTOO_CACHE_FILE)) { $c = json_decode((string) @file_get_contents(TRUSTOO_CACHE_FILE), true); return array_slice($c['reviews'] ?? [], 0, $displayCount); }
        return [];
    }
    $poolSize = max(30, (int) ceil($displayCount * 1.3));
    if (is_file(TRUSTOO_CACHE_FILE)) {
        $cached = json_decode((string) file_get_contents(TRUSTOO_CACHE_FILE), true);
        if ($cached && (time() - ($cached['fetched_at'] ?? 0)) < TRUSTOO_CACHE_HOURS * 3600) return array_slice($cached['reviews'] ?? [], 0, $displayCount);
    }
    $params = ['page' => 1, 'page_size' => $poolSize, 'timestamp' => time()];
    ksort($params);
    $queryString = http_build_query($params);
    $sign = hash_hmac('sha256', $queryString, TRUSTOO_PRIVATE_TOKEN);
    $ch = curl_init(TRUSTOO_BASE_URL . '/api/v1/openapi/get_reviews?' . $queryString);
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER => ['Public-Token: ' . TRUSTOO_PUBLIC_TOKEN, 'Timestamp: ' . $params['timestamp'], 'Sign: ' . $sign], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($httpCode !== 200) { return is_file(TRUSTOO_CACHE_FILE) ? array_slice((json_decode((string) file_get_contents(TRUSTOO_CACHE_FILE), true)['reviews'] ?? []), 0, $displayCount) : []; }
    $data = json_decode($response, true);
    if (!isset($data['code']) || $data['code'] !== 0) return [];
    $reviews = [];
    foreach (($data['data']['list'] ?? []) as $r) {
        if (empty($r['is_published']) || empty($r['content'])) continue;
        $dateFormatted = '';
        if (!empty($r['commented_at'])) { try { $dateFormatted = (new DateTime($r['commented_at']))->format('d/m/Y'); } catch (Exception $e) { } }
        $reviews[] = ['author' => $r['author'] ?: 'Cliente verificado', 'rating' => (int) ($r['rating'] ?? 5), 'content' => $r['content'], 'date' => $r['commented_at'] ?? '',
                      'date_fmt' => $dateFormatted, 'product' => $r['product']['title'] ?? '', 'featured' => (int) ($r['is_featured'] ?? 0)];
    }
    usort($reviews, function ($a, $b) {
        if ($a['featured'] !== $b['featured']) return $b['featured'] <=> $a['featured'];
        if ($a['rating'] !== $b['rating']) return $b['rating'] <=> $a['rating'];
        return strcmp($b['date'], $a['date']);
    });
    @file_put_contents(TRUSTOO_CACHE_FILE, json_encode(['fetched_at' => time(), 'reviews' => $reviews]));
    return array_slice($reviews, 0, $displayCount);
}

/** Reseñas propias publicadas (compra verificada), más recientes primero. */
function ve_own_reviews(int $limit = 30, int $productId = 0): array {
    if (!ve_db_ready()) return [];
    try {
        $sql = 'SELECT author, rating, content, product_title, created_at FROM ve_reviews WHERE published = 1' . ($productId ? ' AND product_id = ' . (int) $productId : '') . ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
        $out = [];
        foreach (ve_pdo()->query($sql)->fetchAll() as $r) {
            $out[] = ['author' => $r['author'], 'rating' => (int) $r['rating'], 'content' => $r['content'], 'product' => ve_clean_text($r['product_title']),
                      'date' => $r['created_at'], 'date_fmt' => date('d/m/Y', strtotime($r['created_at'])), 'verified' => true];
        }
        return $out;
    } catch (Throwable $e) { return []; }
}

/* ───────────── Imágenes y marcas ───────────── */
$brandLogos = [
    'microsoft' => 'imagenes/marcas/microsoft.png', 'office' => 'imagenes/marcas/office.png', 'adobe' => 'imagenes/marcas/adobe.png',
    'autodesk' => 'imagenes/marcas/autodesk.png', 'mcafee' => 'imagenes/marcas/mcafee.png', 'eset' => 'imagenes/marcas/eset.png',
    'kaspersky' => 'imagenes/marcas/kaspersky.png', 'norton' => 'imagenes/marcas/norton.png', 'avg' => 'imagenes/marcas/avg.png', 'panda' => 'imagenes/marcas/panda.png',
];

function product_image_path($product) {
    if (!empty($product['image']) && (preg_match('#^https?://#', $product['image']) || file_exists(__DIR__ . '/' . $product['image']))) return $product['image'];
    return null;
}
/** Dirección de una imagen de la tienda de Chile (relativa a VE_IMAGES_BASE), con cada tramo bien codificado. */
function ve_remote_image(string $path): string {
    if ($path === '') return '';
    if (preg_match('#^(https?:)?//#', $path)) return $path;
    $segs = array_map('rawurlencode', explode('/', ltrim($path, '/')));
    return rtrim(VE_IMAGES_BASE, '/') . '/' . implode('/', $segs);
}

/* ───────────── Facetas para filtros (deducidas del nombre; igual que en la tienda de Chile) ───────────── */
function ve_facets(string $title): array {
    $t = mb_strtolower(ve_clean_text($title));
    $has = function (string $re) use ($t): bool { return (bool) preg_match($re . 'u', $t); };
    if     ($has('/autodesk|autocad|civil 3d|civil3d|revit/'))                  $brand = 'Autodesk';
    elseif ($has('/adobe|acrobat|photoshop|illustrator|creative cloud/'))       $brand = 'Adobe';
    elseif ($has('/mcafee/')) $brand = 'McAfee'; elseif ($has('/kaspersky/')) $brand = 'Kaspersky';
    elseif ($has('/eset|nod32/')) $brand = 'ESET'; elseif ($has('/avast/')) $brand = 'Avast';
    elseif ($has('/norton/')) $brand = 'Norton'; elseif ($has('/panda/')) $brand = 'Panda';
    elseif ($has('/malwarebytes/')) $brand = 'Malwarebytes'; elseif ($has('/avg/')) $brand = 'AVG';
    elseif ($has('/corel/')) $brand = 'CorelDRAW'; elseif ($has('/spotify/')) $brand = 'Spotify';
    elseif ($has('/canva/')) $brand = 'Canva'; elseif ($has('/hbo/')) $brand = 'HBO Max';
    elseif ($has('/prime video/')) $brand = 'Prime Video'; elseif ($has('/chat ?gpt/')) $brand = 'ChatGPT';
    elseif ($has('/gemini/')) $brand = 'Gemini'; elseif ($has('/perplexity/')) $brand = 'Perplexity';
    elseif ($has('/windows|office|visio|project|365|microsoft/')) $brand = 'Microsoft';
    else $brand = 'Otros';

    if     ($has('/^pack|pack /'))      $cat = 'Packs';
    elseif ($brand === 'Autodesk')      $cat = 'Ingeniería';
    elseif ($has('/^windows/'))         $cat = 'Windows';
    elseif (in_array($brand, ['McAfee', 'Kaspersky', 'ESET', 'Avast', 'Norton', 'Panda', 'Malwarebytes', 'AVG'], true)) $cat = 'Antivirus y seguridad';
    elseif ($has('/creative cloud|photoshop|illustrator|corel|canva/')) $cat = 'Diseño';
    elseif ($has('/office|365|visio|project|acrobat/')) $cat = 'Office y productividad';
    elseif (in_array($brand, ['Spotify', 'HBO Max', 'Prime Video', 'ChatGPT', 'Gemini', 'Perplexity'], true)) $cat = 'Suscripciones y streaming';
    else $cat = 'Otros';

    $plat = [];
    if ($has('/\bmac\b/')) $plat[] = 'Mac';
    elseif (in_array($cat, ['Windows', 'Office y productividad', 'Diseño', 'Ingeniería', 'Packs'], true) && $brand !== 'Canva') $plat[] = 'Windows';

    $dur = '';
    if     ($has('/permanente|vitalicia|lifetime/')) $dur = 'Permanente';
    elseif ($has('/(\d+)\s*a[ñn]os?/') && preg_match('/(\d+)\s*a[ñn]os?/u', $t, $m) && (int) $m[1] >= 2) $dur = 'Más de 1 año';
    elseif ($has('/a[ñn]o|12 meses|anual/')) $dur = '1 año';
    elseif ($has('/(\d+)\s*mes/')) $dur = 'Menos de 1 año';
    return ['brand' => $brand, 'category' => $cat, 'platforms' => $plat, 'duration' => $dur];
}

function ve_slugify(string $t): string {
    $t = mb_strtolower(ve_clean_text($t));
    $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    return trim(substr(preg_replace('/[^a-z0-9]+/', '-', $t), 0, 70), '-') ?: 'producto';
}

/* ───────────── Catálogo normalizado ───────────── */
function ve_static(): array { static $s = null; if ($s === null) $s = require __DIR__ . '/ve-static.php'; return $s; }

/** Completa un producto con las facetas y el contenido editorial (si existe para ese slug). */
function ve_finish_product(array $p, string $slug, ?array $extra): array {
    global $brandLogos;
    $f = ve_facets($p['title'] . ' ' . ($extra['title'] ?? ''));
    $p['slug'] = $slug;
    $p['brand'] = $f['brand']; $p['facet_category'] = $f['category']; $p['platforms'] = $f['platforms'];
    // la duración también se puede deducir de las opciones (p. ej. "1 Año", "Permanente")
    $dur = $f['duration'];
    if ($dur === '') foreach ($p['variants'] as $v) { $d = ve_facets($p['title'] . ' ' . $v['label'])['duration']; if ($d !== '') { $dur = $d; break; } }
    if ($dur === '' && $extra && !empty($extra['stats'])) foreach ($extra['stats'] as $s) if ($s[1] === 'Duración') { $d = ve_facets($s[2])['duration']; $dur = $d !== '' ? $d : ((stripos($s[2], '1 año') !== false) ? '1 año' : ''); }
    $p['duration'] = $dur;
    $p['category'] = $extra['category'] ?? $f['category'];
    $p['desc'] = $extra['desc'] ?? '';
    $p['long_desc'] = $extra['long_desc'] ?? '';
    $p['requirements'] = $extra['requirements'] ?? [];
    $p['stats'] = $extra['stats'] ?? [];
    $p['apps'] = $extra['apps'] ?? [];
    $logoKey = strtolower($f['brand']);
    if ($logoKey === 'microsoft' && $extra && !empty($extra['brand_logo'])) $logoKey = $extra['brand_logo'];
    $p['brand_logo'] = isset($brandLogos[$logoKey]) ? $logoKey : ($extra['brand_logo'] ?? 'microsoft');
    if (!empty($extra['image']) && file_exists(__DIR__ . '/' . $extra['image'])) $p['image'] = $extra['image'];
    $p['min_price'] = min(array_column($p['variants'], 'price'));
    return $p;
}

/** @return array<string,array> productos visibles, por slug, listos para pintar */
function ve_catalog(): array {
    static $cache = null; if ($cache !== null) return $cache;
    $static = ve_static(); $out = [];
    if (!ve_db_ready()) {  // modo respaldo: catálogo fijo, pedido por WhatsApp
        foreach ($static as $slug => $s) {
            $vars = []; foreach ($s['variants'] as $v) $vars[] = ['id' => null, 'label' => $v['label'], 'price' => (float) $v['price'], 'stock' => null, 'avail' => true];
            $p = ['pid' => null, 'title' => $s['title'], 'image' => $s['image'] ?? '', 'variants' => $vars, 'soldout' => false, 'desc_html' => '', 'sort' => 100];
            $out[$slug] = ve_finish_product($p, $slug, $s);
        }
        return $cache = $out;
    }
    $pdo = ve_pdo();
    try {
        $rows = $pdo->query("SELECT vp.slug, vp.image AS vimage, vp.title AS vtitle, vp.sort_order, p.id AS pid, p.title AS ptitle, p.image_url, p.description_html
            FROM ve_products vp JOIN products p ON p.id = vp.product_id
            WHERE vp.active = 1 AND p.status = 'activo' ORDER BY vp.sort_order ASC, p.title ASC")->fetchAll();
    } catch (Throwable $e) { error_log('[ve] catálogo: ' . $e->getMessage()); return $cache = []; }
    if (!$rows) return $cache = [];
    $ids = implode(',', array_map('intval', array_column($rows, 'pid')));
    $soldout = [];
    try { foreach ($pdo->query('SELECT id FROM products WHERE sold_out = 1')->fetchAll(PDO::FETCH_COLUMN) as $i) $soldout[(int) $i] = true; } catch (Throwable $e) { }
    $stock = [];
    try { foreach ($pdo->query('SELECT id, stock_manual FROM product_variants WHERE stock_manual IS NOT NULL')->fetchAll() as $r) $stock[(int) $r['id']] = (int) $r['stock_manual']; } catch (Throwable $e) { }
    $vars = [];
    foreach ($pdo->query("SELECT pv.id, pv.product_id, pv.title, pr.price_usd, pr.label FROM product_variants pv JOIN ve_prices pr ON pr.variant_id = pv.id
            WHERE pv.product_id IN ($ids) AND pr.price_usd > 0 ORDER BY pr.price_usd ASC, pv.id ASC")->fetchAll() as $v) {
        $label = ve_clean_text($v['label'] !== null && $v['label'] !== '' ? $v['label'] : $v['title']);
        if ($label === '' || $label === 'Default Title') $label = 'Licencia';
        $vid = (int) $v['id'];
        $vars[(int) $v['product_id']][] = ['id' => $vid, 'label' => $label, 'price' => (float) $v['price_usd'], 'stock' => $stock[$vid] ?? null, 'avail' => !isset($stock[$vid]) || $stock[$vid] > 0];
    }
    $rs = ve_review_stats();
    foreach ($rows as $r) {
        $pid = (int) $r['pid'];
        if (empty($vars[$pid])) continue;
        $title = ve_clean_text($r['vtitle'] !== null && $r['vtitle'] !== '' ? $r['vtitle'] : $r['ptitle']);
        $extra = $static[$r['slug']] ?? null;
        $p = ['pid' => $pid, 'title' => $title, 'image' => ve_remote_image((string) $r['image_url']), 'variants' => $vars[$pid],
              'soldout' => isset($soldout[$pid]), 'sort' => (int) $r['sort_order'],
              'desc_html' => strip_tags((string) $r['description_html'], '<p><br><ul><ol><li><strong><b><em><i><h3><h4>')];
        $p['rating'] = $rs[$pid] ?? null;
        $p = ve_finish_product($p, $r['slug'], $extra);
        if (!empty($r['vimage']) && is_file(__DIR__ . '/' . $r['vimage'])) $p['image'] = $r['vimage'];  // foto propia elegida en el panel
        if (!array_filter(array_column($p['variants'], 'avail'))) $p['soldout'] = true;
        $out[$r['slug']] = $p;
    }
    return $cache = $out;
}

/** Tarjeta de producto del catálogo (misma estructura visual de siempre, con datos para filtros). */
function ve_render_card(array $p, float $rate, bool $filterable = true): void {
    global $brandLogos;
    $imgPath = product_image_path($p);
    $bsPrice = bs($p['min_price'], $rate);
    $data = $filterable ? ' data-brand="' . h($p['brand']) . '" data-cat="' . h($p['facet_category']) . '" data-dur="' . h($p['duration']) . '" data-plat="' . h(implode('|', $p['platforms'])) . '" data-price="' . h($p['min_price']) . '" data-name="' . h($p['title']) . '"' : '';
    ?>
    <a class="product-card<?= $p['soldout'] ? ' is-soldout' : '' ?>" href="venezuela-producto.php?slug=<?= urlencode($p['slug']) ?>"<?= $data ?>>
      <div class="product-card-image">
        <img src="<?= h($imgPath ?: $brandLogos[$p['brand_logo']]) ?>" alt="<?= h($p['title']) ?>" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='<?= h($brandLogos[$p['brand_logo']]) ?>';">
        <?php if ($p['soldout']): ?><span class="soldout-tag">Agotado</span><?php endif; ?>
      </div>
      <div class="product-card-body">
        <span class="product-card-meta"><?= h($p['category']) ?></span>
        <h3><?= h($p['title']) ?></h3>
        <?php if ($p['desc'] !== ''): ?><p class="product-card-desc"><?= h($p['desc']) ?></p><?php endif; ?>
        <?php if (!empty($p['rating'])): ?><p class="product-card-desc" style="color:var(--gold)">★ <?= h(number_format($p['rating']['avg'], 1, ',', '')) ?> <span style="color:var(--ash)">(<?= (int) $p['rating']['n'] ?>)</span></p><?php endif; ?>
        <div class="product-card-price">
          <span class="from-label">Desde</span>
          <span class="price-usd"><?= usd($p['min_price']) ?></span>
          <?php if ($bsPrice): ?><span class="price-bs"><?= h($bsPrice) ?></span><?php endif; ?>
        </div>
      </div>
    </a>
    <?php
}
