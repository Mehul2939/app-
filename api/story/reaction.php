<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/story.php';
$me = current_user();
$data = input();
$storyId = (int)($data['story_id'] ?? 0);
$type = clean_string($data['reaction_type'] ?? '', 20);
if (!in_array($type, ['love','hot','amazing','wow'], true)) json_response(['success' => false, 'message' => 'Invalid reaction'], 422);
$existing = db()->prepare('SELECT reaction_type FROM story_reactions WHERE story_id=? AND user_id=?');
$existing->execute([$storyId, $me['id']]);
$old = $existing->fetchColumn();
if ($old === $type) {
    db()->prepare('DELETE FROM story_reactions WHERE story_id=? AND user_id=?')->execute([$storyId, $me['id']]);
    json_response(['success' => true, 'reaction' => null]);
}
db()->prepare('INSERT INTO story_reactions (story_id,user_id,reaction_type) VALUES (?,?,?) ON DUPLICATE KEY UPDATE reaction_type=VALUES(reaction_type),updated_at=NOW()')->execute([$storyId, $me['id'], $type]);
notify_story_admin($storyId, (int)$me['id'], 'story_reaction', $me['name'] . ' reacted to your story');
json_response(['success' => true, 'reaction' => $type]);

