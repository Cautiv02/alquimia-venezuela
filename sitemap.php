<?php
/** Sitemap dinámico: /ve/sitemap.php (también accesible como /ve/sitemap.xml) */
require_once __DIR__ . '/venezuela-data.php';
header('Content-Type: application/xml; charset=utf-8');
$base = ve_site_url();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo '  <url><loc>' . htmlspecialchars($base . '/') . '</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>' . "\n";
foreach (ve_catalog() as $slug => $p) {
    echo '  <url><loc>' . htmlspecialchars($base . '/venezuela-producto.php?slug=' . rawurlencode($slug)) . '</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
