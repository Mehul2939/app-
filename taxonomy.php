<?php
require_once __DIR__ . '/api/config/db.php';
require_once __DIR__ . '/api/helpers/seo.php';

$type = ($_GET['type'] ?? 'category') === 'tag' ? 'tag' : 'category';
$slug = preg_replace('/[^a-zA-Z0-9-]/', '', $_GET['slug'] ?? '');
$base = site_url();

if ($type === 'tag') {
    $stmt = db()->prepare('SELECT * FROM tags WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $item = $stmt->fetch();
    if (!$item) { http_response_code(404); echo '<!doctype html><title>Tag not found - myself</title><h1>Tag not found</h1>'; exit; }
    $postsStmt = db()->prepare("SELECT p.slug, p.post_text, p.created_at FROM posts p JOIN post_tags pt ON pt.post_id = p.id WHERE pt.tag_id = ? AND p.status = 'active' AND p.privacy = 'public' ORDER BY p.created_at DESC LIMIT 30");
    $postsStmt->execute([$item['id']]);
} else {
    $stmt = db()->prepare('SELECT * FROM categories WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $item = $stmt->fetch();
    if (!$item) { http_response_code(404); echo '<!doctype html><title>Category not found - myself</title><h1>Category not found</h1>'; exit; }
    $postsStmt = db()->prepare("SELECT slug, post_text, created_at FROM posts WHERE category_id = ? AND status = 'active' AND privacy = 'public' ORDER BY created_at DESC LIMIT 30");
    $postsStmt->execute([$item['id']]);
}
$posts = $postsStmt->fetchAll();
$title = htmlspecialchars($item['name'] . ' ' . ucfirst($type) . ' - myself');
$canonical = $base . '/' . $type . '/' . $item['slug'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $title ?></title>
  <meta name="description" content="<?= htmlspecialchars('Browse public myself posts for ' . $item['name']) ?>">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
  <meta property="og:title" content="<?= $title ?>">
  <meta property="og:description" content="<?= htmlspecialchars('Browse public myself posts for ' . $item['name']) ?>">
  <style>body{margin:0;background:#f7f8fb;color:#171717;font-family:system-ui,-apple-system,Segoe UI,sans-serif}.wrap{max-width:820px;margin:auto;padding:24px}.card{background:#fff;border:1px solid #e6e8ef;border-radius:8px;padding:18px;margin-top:12px}.brand{font-weight:900;font-size:28px;text-decoration:none}.muted{color:#71717a}</style>
</head>
<body><main class="wrap"><a class="brand" href="<?= htmlspecialchars($base) ?>/public">myself</a><h1><?= $title ?></h1><p class="muted">Public, indexable posts.</p><?php foreach ($posts as $p): ?><article class="card"><h2><a href="<?= htmlspecialchars($base . '/post/' . $p['slug']) ?>"><?= htmlspecialchars(excerpt_text($p['post_text'], 90)) ?></a></h2><p class="muted"><?= htmlspecialchars(date('M d, Y', strtotime($p['created_at']))) ?></p></article><?php endforeach; ?></main></body>
</html>

