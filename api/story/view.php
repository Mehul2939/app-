<?php
require_once __DIR__ . '/../helpers/response.php';
$data = input();
$storyId = (int)($data['story_id'] ?? 0);
$fingerprint = clean_string($data['fingerprint'] ?? '', 180);
if ($storyId <= 0) json_response(['success' => false, 'message' => 'Story required'], 422);
$identity = ($fingerprint ?: ($_SERVER['REMOTE_ADDR'] ?? '')) . '|' . substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 180);
$hash = hash('sha256', $identity);
$stmt = db()->prepare("INSERT INTO story_views (story_id, visitor_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE view_count=IF(last_viewed_at < DATE_SUB(NOW(), INTERVAL 6 HOUR), view_count+1, view_count), last_viewed_at=NOW()");
$stmt->execute([$storyId, $hash]);
json_response(['success' => true]);
