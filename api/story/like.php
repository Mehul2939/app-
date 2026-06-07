<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/story.php';
$me = current_user();
$storyId = (int)(input()['story_id'] ?? 0);
$stmt = db()->prepare('SELECT 1 FROM story_likes WHERE story_id=? AND user_id=?');
$stmt->execute([$storyId, $me['id']]);
if ($stmt->fetch()) {
    db()->prepare('DELETE FROM story_likes WHERE story_id=? AND user_id=?')->execute([$storyId, $me['id']]);
    json_response(['success' => true, 'liked' => false]);
}
db()->prepare('INSERT INTO story_likes (story_id,user_id) VALUES (?,?)')->execute([$storyId, $me['id']]);
notify_story_admin($storyId, (int)$me['id'], 'story_like', $me['name'] . ' liked your story');
json_response(['success' => true, 'liked' => true]);

