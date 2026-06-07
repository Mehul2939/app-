<?php
require_once __DIR__ . '/api/config/db.php';
require_once __DIR__ . '/api/helpers/seo.php';
header('Content-Type: application/xml; charset=utf-8');
$base = site_url();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$profiles = db()->query("SELECT u.username, u.updated_at FROM users u JOIN user_profiles p ON p.user_id = u.id WHERE u.status = 'active' AND p.account_type = 'public' ORDER BY u.updated_at DESC LIMIT 50000")->fetchAll();
foreach ($profiles as $u) {
    echo '  <url><loc>' . htmlspecialchars($base . '/user/' . rawurlencode($u['username'])) . '</loc><lastmod>' . date('c', strtotime($u['updated_at'])) . '</lastmod><changefreq>weekly</changefreq><priority>0.6</priority></url>' . "\n";
}
echo '</urlset>';

