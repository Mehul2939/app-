<?php
require_once __DIR__ . '/api/helpers/seo.php';
header('Content-Type: text/plain; charset=utf-8');
$base = site_url();
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Allow: /stories/\n";
echo "Disallow: /login\n";
echo "Disallow: /register\n";
echo "Disallow: /dashboard\n";
echo "Disallow: /settings\n";
echo "Disallow: /admin\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n\n";
echo "Sitemap: {$base}/sitemap.xml\n";
