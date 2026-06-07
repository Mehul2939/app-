<?php
require_once __DIR__ . '/api/config/db.php';
require_once __DIR__ . '/api/helpers/seo.php';
header('Content-Type: application/xml; charset=utf-8');
$base = site_url();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$posts = db()->query("SELECT slug, updated_at FROM posts WHERE status = 'active' AND privacy = 'public' ORDER BY updated_at DESC LIMIT 50000")->fetchAll();
foreach ($posts as $p) {
    echo '  <url><loc>' . htmlspecialchars($base . '/post/' . rawurlencode($p['slug'])) . '</loc><lastmod>' . date('c', strtotime($p['updated_at'])) . '</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>' . "\n";
}
echo '</urlset>';

