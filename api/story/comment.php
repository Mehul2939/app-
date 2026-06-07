<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/story.php';
$me = current_user();
$data = input();
$action = clean_string($data['action'] ?? 'add', 20);
$id = (int)($data['comment_id'] ?? 0);
$text = clean_string($data['comment_text'] ?? '', 1500);
if ($action === 'delete') {
    $stmt = db()->prepare("UPDATE story_comments SET status='deleted' WHERE id=? AND user_id=?");
    $stmt->execute([$id, $me['id']]);
    json_response(['success' => $stmt->rowCount() > 0]);
}
if ($text === '') json_response(['success' => false, 'message' => 'Comment required'], 422);
if ($action === 'edit') {
    $stmt = db()->prepare("UPDATE story_comments SET comment_text=? WHERE id=? AND user_id=? AND status='active'");
    $stmt->execute([$text, $id, $me['id']]);
    json_response(['success' => $stmt->rowCount() > 0]);
}
$storyId = (int)($data['story_id'] ?? 0);
$parentId = (int)($data['parent_comment_id'] ?? 0);
db()->prepare('INSERT INTO story_comments (story_id,user_id,parent_comment_id,comment_text) VALUES (?,?,?,?)')->execute([$storyId, $me['id'], $parentId ?: null, $text]);
notify_story_admin($storyId, (int)$me['id'], $parentId ? 'story_reply' : 'story_comment', $me['name'] . ($parentId ? ' replied on your story' : ' commented on your story'));
json_response(['success' => true, 'comment_id' => (int)db()->lastInsertId()]);

