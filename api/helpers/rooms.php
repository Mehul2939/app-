<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/social.php';

function room_by_id(int $roomId): ?array
{
    $stmt = db()->prepare("SELECT r.*, u.name owner_name, u.username owner_username,
      COALESCE(u.profile_photo,p.profile_photo) owner_photo,
      (SELECT COUNT(*) FROM room_follows rf WHERE rf.host_id=r.owner_id) followers_count
      FROM rooms r JOIN users u ON u.id=r.owner_id LEFT JOIN user_profiles p ON p.user_id=u.id
      WHERE r.id=? LIMIT 1");
    $stmt->execute([$roomId]);
    return $stmt->fetch() ?: null;
}

function room_role(int $roomId, int $userId): string
{
    $room = room_by_id($roomId);
    if (!$room) return 'none';
    if ((int)$room['owner_id'] === $userId) return 'owner';
    $admin = db()->prepare('SELECT id FROM room_admins WHERE room_id=? AND user_id=? LIMIT 1');
    $admin->execute([$roomId,$userId]);
    if ($admin->fetch()) return 'coadmin';
    $participant = db()->prepare('SELECT role FROM room_participants WHERE room_id=? AND user_id=? AND left_at IS NULL LIMIT 1');
    $participant->execute([$roomId,$userId]);
    return (string)($participant->fetchColumn() ?: 'none');
}

function require_room_role(int $roomId, int $userId, array $roles): string
{
    $role = room_role($roomId,$userId);
    if (!in_array($role,$roles,true)) json_response(['success'=>false,'message'=>'Room permission denied'],403);
    return $role;
}

function room_system_message(int $roomId, string $message): void
{
    db()->prepare("INSERT INTO room_messages(room_id,message_type,message_text) VALUES (?,'system',?)")->execute([$roomId,$message]);
}

function youtube_id(string $url): string
{
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~',$url,$m)) return $m[1];
    return '';
}

function refresh_room_count(int $roomId): void
{
    db()->prepare('UPDATE rooms SET active_users=(SELECT COUNT(*) FROM room_participants WHERE room_id=? AND left_at IS NULL) WHERE id=?')->execute([$roomId,$roomId]);
}
