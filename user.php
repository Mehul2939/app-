<?php
require_once __DIR__ . '/api/config/db.php';
require_once __DIR__ . '/api/helpers/seo.php';

$username = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['username'] ?? '');
$stmt = db()->prepare("SELECT u.id, u.public_user_id, u.name, u.username, u.created_at, u.updated_at, p.profile_photo, p.bio, p.city, p.account_type,
  (SELECT COUNT(*) FROM friends WHERE user_id = u.id) friends_count
  FROM users u JOIN user_profiles p ON p.user_id = u.id
  WHERE u.username = ? AND u.status = 'active' AND p.account_type = 'public'
  LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(404);
    echo '<!doctype html><title>User not found - myself</title><h1>User not found</h1>';
    exit;
}
$postsStmt = db()->prepare("SELECT slug, post_text, created_at FROM posts WHERE user_id = ? AND status = 'active' AND privacy = 'public' ORDER BY created_at DESC LIMIT 10");
$postsStmt->execute([$user['id']]);
$posts = $postsStmt->fetchAll();
$base = site_url();
$canonical = $base . '/user/' . rawurlencode($user['username']);
$title = htmlspecialchars($user['name'] . ' (@' . $user['username'] . ') on myself');
$description = htmlspecialchars(excerpt_text($user['bio'] ?: 'Public profile on myself', 155));
$image = $user['profile_photo'] ? $base . '/' . ltrim($user['profile_photo'], '/') : '';
$profileSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'ProfilePage',
    'name' => html_entity_decode($title),
    'url' => $canonical,
    'mainEntity' => ['@type' => 'Person', 'name' => $user['name'], 'alternateName' => '@' . $user['username']],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $title ?></title>
  <meta name="description" content="<?= $description ?>">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
  <meta property="og:type" content="profile">
  <meta property="og:title" content="<?= $title ?>">
  <meta property="og:description" content="<?= $description ?>">
  <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
  <?php if ($image): ?><meta property="og:image" content="<?= htmlspecialchars($image) ?>"><?php endif; ?>
  <meta name="twitter:card" content="<?= $image ? 'summary_large_image' : 'summary' ?>">
  <script type="application/ld+json"><?= json_encode($profileSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <style>body{margin:0;background:#f7f8fb;color:#171717;font-family:system-ui,-apple-system,Segoe UI,sans-serif}.wrap{max-width:820px;margin:auto;padding:24px}.card{background:#fff;border:1px solid #e6e8ef;border-radius:8px;padding:22px;margin-top:14px}.brand{font-weight:900;font-size:28px;text-decoration:none}.avatar{width:96px;height:96px;border-radius:50%;object-fit:cover;background:#0ea5a4;color:#fff;display:grid;place-items:center;font-size:44px;font-weight:900}.muted{color:#71717a}.footer{margin-top:30px;color:#71717a;font-size:14px;display:flex;gap:14px;flex-wrap:wrap}</style>
</head>
<body><main class="wrap">
  <a class="brand" href="<?= htmlspecialchars($base) ?>/public">myself</a>
  <section class="card">
    <?php if ($image): ?><img class="avatar" loading="lazy" src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($user['name']) ?> profile photo"><?php else: ?><div class="avatar"><?= htmlspecialchars(mb_substr($user['name'], 0, 1)) ?></div><?php endif; ?>
    <h1><?= htmlspecialchars($user['name']) ?></h1>
    <p class="muted">@<?= htmlspecialchars($user['username']) ?> · ID <?= htmlspecialchars($user['public_user_id']) ?> · <?= (int)$user['friends_count'] ?> friends · <?= htmlspecialchars($user['city'] ?: 'India') ?></p>
    <h2>About</h2>
    <p><?= nl2br(htmlspecialchars($user['bio'] ?: 'Public profile on myself.')) ?></p>
  </section>
  <section class="card"><h2>Public posts</h2><?php foreach ($posts as $p): ?><p><a href="<?= htmlspecialchars($base . '/post/' . $p['slug']) ?>"><?= htmlspecialchars(excerpt_text($p['post_text'], 110)) ?></a></p><?php endforeach; ?></section>
  <footer class="footer"><a href="<?= htmlspecialchars($base) ?>/public/legal/privacy-policy">Privacy Policy</a><a href="<?= htmlspecialchars($base) ?>/public/legal/terms-of-service">Terms</a><a href="<?= htmlspecialchars($base) ?>/public/legal/18-plus-policy">18+ Policy</a></footer>
</main></body></html>
