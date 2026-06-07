<?php
require_once __DIR__ . '/api/config/db.php';
require_once __DIR__ . '/api/helpers/seo.php';

header('Content-Type: application/xml; charset=utf-8');
$base = site_url();
$urls = [
    ['loc' => $base . '/', 'lastmod' => date('c'), 'changefreq' => 'daily', 'priority' => '1.0'],
];
$posts = db()->query("SELECT slug, updated_at FROM posts WHERE status = 'active' AND privacy = 'public' ORDER BY updated_at DESC LIMIT 50000")->fetchAll();
foreach ($posts as $p) {
    $urls[] = ['loc' => $base . '/post/' . rawurlencode($p['slug']), 'lastmod' => date('c', strtotime($p['updated_at'])), 'changefreq' => 'weekly', 'priority' => '0.8'];
}
$stories = db()->query("SELECT slug, updated_at FROM stories WHERE (status = 'published' AND (publish_at IS NULL OR publish_at <= NOW())) OR (status = 'scheduled' AND publish_at <= NOW()) ORDER BY updated_at DESC LIMIT 50000")->fetchAll();
foreach ($stories as $s) {
    $urls[] = ['loc' => $base . '/stories/' . rawurlencode($s['slug']), 'lastmod' => date('c', strtotime($s['updated_at'])), 'changefreq' => 'weekly', 'priority' => '0.9'];
}
$profiles = db()->query("SELECT u.username, u.updated_at FROM users u JOIN user_profiles p ON p.user_id = u.id WHERE u.status = 'active' AND p.account_type = 'public' ORDER BY u.updated_at DESC LIMIT 50000")->fetchAll();
foreach ($profiles as $u) {
    $urls[] = ['loc' => $base . '/user/' . rawurlencode($u['username']), 'lastmod' => date('c', strtotime($u['updated_at'])), 'changefreq' => 'weekly', 'priority' => '0.6'];
}
$categories = db()->query("SELECT slug, created_at FROM categories ORDER BY name LIMIT 50000")->fetchAll();
foreach ($categories as $c) {
    $urls[] = ['loc' => $base . '/category/' . rawurlencode($c['slug']), 'lastmod' => date('c', strtotime($c['created_at'])), 'changefreq' => 'weekly', 'priority' => '0.5'];
}
$tags = db()->query("SELECT slug, created_at FROM tags ORDER BY name LIMIT 50000")->fetchAll();
foreach ($tags as $t) {
    $urls[] = ['loc' => $base . '/tag/' . rawurlencode($t['slug']), 'lastmod' => date('c', strtotime($t['created_at'])), 'changefreq' => 'weekly', 'priority' => '0.5'];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo "  <url><loc>" . htmlspecialchars($url['loc']) . "</loc><lastmod>{$url['lastmod']}</lastmod><changefreq>{$url['changefreq']}</changefreq><priority>{$url['priority']}</priority></url>\n";
}
echo '</urlset>';
