<?php
declare(strict_types=1);

require_once __DIR__ . '/seo.php';

function story_is_public_sql(string $alias = 's'): string
{
    return "(({$alias}.status = 'published' AND ({$alias}.publish_at IS NULL OR {$alias}.publish_at <= NOW())) OR ({$alias}.status = 'scheduled' AND {$alias}.publish_at <= NOW()))";
}

function unique_story_slug(string $title, int $ignoreId = 0): string
{
    $base = slugify($title);
    $slug = $base;
    for ($i = 2; $i < 500; $i++) {
        $stmt = db()->prepare('SELECT id FROM stories WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetch()) return $slug;
        $slug = $base . '-' . $i;
    }
    return $base . '-' . time();
}

function sanitize_story_html(string $html): string
{
    $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html) ?: '';
    $html = strip_tags($html, '<p><br><h2><h3><h4><h5><h6><strong><b><em><i><u><ul><ol><li><blockquote><a>');
    return preg_replace('/\s(on\w+|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?: '';
}

function story_reading_minutes(string $content): int
{
    return max(1, (int)ceil(str_word_count(strip_tags($content)) / 220));
}

function notify_story_admin(int $storyId, int $actorId, string $type, string $message): void
{
    $stmt = db()->prepare('INSERT INTO admin_notifications (admin_id, actor_user_id, story_id, type, message) SELECT admin_id, ?, id, ?, ? FROM stories WHERE id = ?');
    $stmt->execute([$actorId, $type, $message, $storyId]);
}

function store_story_upload(string $key, string $kind): ?string
{
    if (empty($_FILES[$key]['tmp_name']) || !is_uploaded_file($_FILES[$key]['tmp_name'])) return null;
    $allowed = $kind === 'image'
        ? ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']
        : ['audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/webm' => 'webm', 'audio/mp4' => 'm4a'];
    $max = $kind === 'image' ? 6 * 1024 * 1024 : 30 * 1024 * 1024;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$key]['tmp_name']);
    if (!isset($allowed[$mime]) || (int)$_FILES[$key]['size'] > $max) {
        json_response(['success' => false, 'message' => "Invalid {$kind} upload"], 422);
    }
    $dir = __DIR__ . '/../../uploads/stories';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $relative = 'uploads/stories/' . $kind . '_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($_FILES[$key]['tmp_name'], __DIR__ . '/../../' . $relative)) {
        json_response(['success' => false, 'message' => 'Upload failed'], 500);
    }
    return $relative;
}
