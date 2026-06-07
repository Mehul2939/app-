<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';
$me = current_user();
$data = input();
$postId = (int)($data['post_id'] ?? 0);
$text = clean_string($data['comment_text'] ?? '', 1000);
$parentId = (int)($data['parent_comment_id'] ?? 0);
if ($text === '') json_response(['success' => false, 'message' => 'Comment required'], 422);
db()->prepare('INSERT INTO post_comments (post_id, user_id, comment_text, parent_comment_id) VALUES (?, ?, ?, ?)')->execute([$postId, $me['id'], $text, $parentId ?: null]);
$commentId = (int)db()->lastInsertId();
$owner = db()->prepare('SELECT user_id FROM posts WHERE id = ?');
$owner->execute([$postId]);
if ($p = $owner->fetch()) notify_user((int)$p['user_id'], (int)$me['id'], 'post_comment', $me['name'] . ' commented on your post', $postId);
if ($parentId) {
    $parent = db()->prepare('SELECT user_id FROM post_comments WHERE id = ?');
    $parent->execute([$parentId]);
    if ($c = $parent->fetch()) notify_user((int)$c['user_id'], (int)$me['id'], 'comment_reply', $me['name'] . ' replied to your comment', $postId);
}
if (preg_match_all('/@([a-zA-Z0-9_]+)/', $text, $matches)) {
    $mention = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    foreach (array_unique($matches[1]) as $username) {
        $mention->execute([$username]);
        if ($u = $mention->fetch()) notify_user((int)$u['id'], (int)$me['id'], 'mention', $me['name'] . ' mentioned you in a comment', $postId);
    }
}
json_response(['success' => true, 'comment_id' => $commentId]);
