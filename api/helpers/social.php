<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function notify_user(int $userId, ?int $actorId, string $type, string $message, ?int $referenceId = null): void
{
    if ($actorId !== null && $userId === $actorId) {
        return;
    }
    $stmt = db()->prepare('INSERT INTO notifications (user_id, actor_id, type, message, reference_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $actorId, $type, $message, $referenceId]);
}

function generate_public_user_id(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $id = '';
        for ($i = 0; $i < 10; $i++) {
            $id .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = db()->prepare('SELECT id FROM users WHERE public_user_id = ? LIMIT 1');
        $stmt->execute([$id]);
    } while ($stmt->fetch());
    return $id;
}

function blocked_between(int $a, int $b): bool
{
    $stmt = db()->prepare('SELECT id FROM blocked_users WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1');
    $stmt->execute([$a, $b, $b, $a]);
    return (bool)$stmt->fetch();
}

function are_friends(int $a, int $b): bool
{
    $stmt = db()->prepare('SELECT id FROM friends WHERE user_id = ? AND friend_id = ? LIMIT 1');
    $stmt->execute([$a, $b]);
    return (bool)$stmt->fetch();
}

function friend_status(int $me, int $target): string
{
    if ($me <= 0 || $target <= 0 || $me === $target) {
        return $me === $target ? 'me' : 'not_friends';
    }
    if (are_friends($me, $target)) {
        return 'friends';
    }
    $sent = db()->prepare("SELECT id FROM friend_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending' LIMIT 1");
    $sent->execute([$me, $target]);
    if ($sent->fetch()) {
        return 'request_sent';
    }
    $received = db()->prepare("SELECT id FROM friend_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending' LIMIT 1");
    $received->execute([$target, $me]);
    if ($received->fetch()) {
        return 'request_received';
    }
    return 'not_friends';
}

function post_payload(array $post, int $viewerId = 0): array
{
    $pdo = db();
    $media = $pdo->prepare('SELECT id, media_path, media_type FROM post_media WHERE post_id = ? ORDER BY id ASC');
    $media->execute([(int)$post['id']]);
    $counts = $pdo->prepare('SELECT
        (SELECT COUNT(*) FROM post_likes WHERE post_id = ?) likes_count,
        (SELECT COUNT(*) FROM post_reactions WHERE post_id = ?) reactions_count,
        (SELECT COUNT(*) FROM post_comments WHERE post_id = ?) comments_count,
        (SELECT COUNT(*) FROM saved_posts WHERE post_id = ? AND user_id = ?) saved_by_me,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = ? AND user_id = ?) liked_by_me,
        (SELECT reaction_type FROM post_reactions WHERE post_id = ? AND user_id = ? LIMIT 1) my_reaction');
    $counts->execute([(int)$post['id'], (int)$post['id'], (int)$post['id'], (int)$post['id'], $viewerId, (int)$post['id'], $viewerId, (int)$post['id'], $viewerId]);
    $reactionStmt = $pdo->prepare('SELECT reaction_type, COUNT(*) total FROM post_reactions WHERE post_id = ? GROUP BY reaction_type');
    $reactionStmt->execute([(int)$post['id']]);
    return array_merge($post, $counts->fetch() ?: [], ['media' => $media->fetchAll(), 'reaction_summary' => $reactionStmt->fetchAll()]);
}
