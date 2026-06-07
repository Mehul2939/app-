<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/seo.php';
$me = current_user();
$text = clean_string($_POST['post_text'] ?? (input()['post_text'] ?? ''), 2000);
$privacy = in_array(($_POST['privacy'] ?? 'public'), ['public','followers','private'], true) ? $_POST['privacy'] : 'public';
$isSensitive = !empty($_POST['is_sensitive']) ? 1 : 0;
$altText = clean_string($_POST['alt_text'] ?? '', 255);
if ($text === '' && empty($_FILES['media'])) json_response(['success' => false, 'message' => 'Write something or add media'], 422);

$pdo = db();
$slug = unique_post_slug($text !== '' ? $text : 'post by ' . $me['username']);
$metaTitle = excerpt_text($text, 70) ?: 'Post by ' . $me['name'];
$metaDescription = excerpt_text($text, 155) ?: 'A public post on myself.';
$stmt = $pdo->prepare('INSERT INTO posts (user_id, slug, post_text, meta_title, meta_description, privacy, is_sensitive) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$me['id'], $slug, $text, $metaTitle, $metaDescription, $privacy, $isSensitive]);
$postId = (int)$pdo->lastInsertId();
$allowed = ['image/jpeg' => ['jpg', 'image'], 'image/png' => ['png', 'image'], 'image/webp' => ['webp', 'image'], 'video/mp4' => ['mp4', 'video'], 'audio/mpeg' => ['mp3', 'audio'], 'audio/wav' => ['wav', 'audio'], 'audio/webm' => ['webm', 'audio']];
if (!empty($_FILES['media']['name'][0])) {
    foreach ($_FILES['media']['name'] as $i => $name) {
        $type = $_FILES['media']['type'][$i] ?? '';
        $tmp = $_FILES['media']['tmp_name'][$i] ?? '';
        if (!isset($allowed[$type]) || ($_FILES['media']['size'][$i] ?? 0) > 10485760) continue;
        [$ext, $mediaType] = $allowed[$type];
        $file = 'uploads/posts/' . $postId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        move_uploaded_file($tmp, __DIR__ . '/../../' . $file);
        $pdo->prepare('INSERT INTO post_media (post_id, media_path, media_type, alt_text) VALUES (?, ?, ?, ?)')
            ->execute([$postId, $file, $mediaType, $altText ?: $metaTitle]);
    }
}
json_response(['success' => true, 'message' => 'Post created', 'post_id' => $postId, 'slug' => $slug]);
