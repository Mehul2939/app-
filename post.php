<?php
require_once __DIR__ . '/api/config/db.php';
require_once __DIR__ . '/api/helpers/seo.php';

$slug = preg_replace('/[^a-zA-Z0-9-]/', '', $_GET['slug'] ?? '');
$stmt = db()->prepare("SELECT p.*, u.name, u.username, up.profile_photo
  FROM posts p
  JOIN users u ON u.id = p.user_id
  LEFT JOIN user_profiles up ON up.user_id = u.id
  WHERE p.slug = ? AND p.status = 'active' AND p.privacy = 'public'
  LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();
if (!$post) {
    http_response_code(404);
    echo '<!doctype html><title>Post not found - myself</title><h1>Post not found</h1>';
    exit;
}
$mediaStmt = db()->prepare('SELECT * FROM post_media WHERE post_id = ? ORDER BY id ASC');
$mediaStmt->execute([$post['id']]);
$media = $mediaStmt->fetchAll();
$commentCount = db()->prepare('SELECT COUNT(*) FROM post_comments WHERE post_id = ?');
$commentCount->execute([$post['id']]);
$relatedStmt = db()->prepare("SELECT slug, post_text FROM posts WHERE status = 'active' AND privacy = 'public' AND id <> ? ORDER BY created_at DESC LIMIT 5");
$relatedStmt->execute([$post['id']]);
$related = $relatedStmt->fetchAll();
$base = site_url();
$canonical = $base . '/post/' . rawurlencode($post['slug']);
$title = htmlspecialchars($post['meta_title'] ?: excerpt_text($post['post_text'], 70) ?: 'Public post on myself');
$description = htmlspecialchars($post['meta_description'] ?: excerpt_text($post['post_text'], 155));
$image = '';
foreach ($media as $m) {
    if ($m['media_type'] === 'image') {
        $image = $base . '/' . ltrim($m['media_path'], '/');
        break;
    }
}
$articleSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => html_entity_decode($title),
    'description' => html_entity_decode($description),
    'author' => ['@type' => 'Person', 'name' => $post['name'], 'url' => $base . '/user/' . $post['username']],
    'datePublished' => date('c', strtotime($post['created_at'])),
    'dateModified' => date('c', strtotime($post['updated_at'])),
    'mainEntityOfPage' => $canonical,
];
if ($image) $articleSchema['image'] = [$image];
$breadcrumbSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Posts', 'item' => $base . '/'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => html_entity_decode($title), 'item' => $canonical],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $title ?> - myself</title>
  <meta name="description" content="<?= $description ?>">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
  <meta property="og:type" content="article">
  <meta property="og:title" content="<?= $title ?>">
  <meta property="og:description" content="<?= $description ?>">
  <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
  <?php if ($image): ?><meta property="og:image" content="<?= htmlspecialchars($image) ?>"><?php endif; ?>
  <meta name="twitter:card" content="<?= $image ? 'summary_large_image' : 'summary' ?>">
  <meta name="twitter:title" content="<?= $title ?>">
  <meta name="twitter:description" content="<?= $description ?>">
  <?php if ($image): ?><meta name="twitter:image" content="<?= htmlspecialchars($image) ?>"><?php endif; ?>
  <script type="application/ld+json"><?= json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <style>
    body{margin:0;background:#f7f8fb;color:#171717;font-family:system-ui,-apple-system,Segoe UI,sans-serif;letter-spacing:0}a{color:inherit}.wrap{max-width:820px;margin:auto;padding:24px}.card{background:#fff;border:1px solid #e6e8ef;border-radius:8px;padding:22px}.brand{font-weight:900;font-size:28px;text-decoration:none}.crumbs{font-size:14px;color:#71717a;margin:18px 0}.author{color:#71717a}.media{display:grid;gap:10px;margin:18px 0}.media img,.media video{width:100%;height:auto;border-radius:8px}.sensitive{filter:blur(18px)}.comments-lock{margin-top:18px;padding:16px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc}.related{display:grid;gap:10px;margin-top:20px}.footer{margin-top:30px;color:#71717a;font-size:14px;display:flex;gap:14px;flex-wrap:wrap}</style>
</head>
<body>
<main class="wrap">
  <a class="brand" href="<?= htmlspecialchars($base) ?>/public">myself</a>
  <nav class="crumbs"><a href="<?= htmlspecialchars($base) ?>/public">Home</a> / <span>Post</span> / <span><?= $title ?></span></nav>
  <article class="card">
    <h1><?= $title ?></h1>
    <p class="author">By <a href="<?= htmlspecialchars($base . '/user/' . $post['username']) ?>"><?= htmlspecialchars($post['name']) ?></a> · <?= htmlspecialchars(date('M d, Y', strtotime($post['created_at']))) ?></p>
    <h2>Post</h2>
    <p><?= nl2br(htmlspecialchars($post['post_text'])) ?></p>
    <?php if ($media): ?><h3>Media</h3><div class="media"><?php foreach ($media as $m): ?>
      <?php if ($m['media_type'] === 'video'): ?><video controls preload="metadata" src="<?= htmlspecialchars($base . '/' . $m['media_path']) ?>"></video>
      <?php else: ?><img loading="lazy" class="<?= $post['is_sensitive'] ? 'sensitive' : '' ?>" src="<?= htmlspecialchars($base . '/' . $m['media_path']) ?>" alt="<?= htmlspecialchars($m['alt_text'] ?: $title) ?>">
      <?php endif; ?>
    <?php endforeach; ?></div><?php endif; ?>
    <section class="comments-lock"><strong><?= (int)$commentCount->fetchColumn() ?> comments</strong><p>Please login to view and participate in comments.</p><a href="<?= htmlspecialchars($base) ?>/public/login">Login</a></section>
  </article>
  <section class="related"><h2>Related posts</h2><?php foreach ($related as $r): ?><a class="card" href="<?= htmlspecialchars($base . '/post/' . $r['slug']) ?>"><?= htmlspecialchars(excerpt_text($r['post_text'], 90)) ?></a><?php endforeach; ?></section>
  <footer class="footer"><a href="<?= htmlspecialchars($base) ?>/public/legal/privacy-policy">Privacy Policy</a><a href="<?= htmlspecialchars($base) ?>/public/legal/terms-of-service">Terms</a><a href="<?= htmlspecialchars($base) ?>/public/legal/18-plus-policy">18+ Policy</a></footer>
</main>
</body>
</html>

