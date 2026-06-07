<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/social.php';
require_once __DIR__ . '/../helpers/wallet.php';
$me = current_user();
$data = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : input();
$receiver = (int)($data['receiver_id'] ?? 0);
$text = clean_string($data['message_text'] ?? '', 2000);
$replyTo = (int)($data['reply_to_message_id'] ?? 0);
$gifPath = clean_string($data['gif_path'] ?? '', 255);
if ($receiver <= 0 || ($text === '' && empty($_FILES['media']['tmp_name']) && $gifPath === '')) json_response(['success' => false, 'message' => 'Message required'], 422);
if (blocked_between((int)$me['id'], $receiver)) json_response(['success' => false, 'message' => 'Blocked user'], 403);
if (!are_friends((int)$me['id'], $receiver)) json_response(['success' => false, 'message' => 'Only friends can exchange messages.'], 403);

$mediaPath = null;
$mediaType = 'text';
if ($gifPath !== '') {
    $gif = db()->prepare('SELECT gif_path FROM user_gifs WHERE user_id = ? AND gif_path = ? LIMIT 1');
    $gif->execute([$me['id'], $gifPath]);
    if (!$gif->fetch()) {
        json_response(['success' => false, 'message' => 'GIF not found in your gallery'], 404);
    }
    $mediaPath = $gifPath;
    $mediaType = 'image';
}
if (!empty($_FILES['media']['tmp_name'])) {
    $allowed = [
        'image/jpeg' => ['jpg', 'image'], 'image/png' => ['png', 'image'], 'image/webp' => ['webp', 'image'], 'image/gif' => ['gif', 'image'],
        'video/mp4' => ['mp4', 'video'], 'video/webm' => ['webm', 'video'],
        'audio/mpeg' => ['mp3', 'audio'], 'audio/wav' => ['wav', 'audio'], 'audio/webm' => ['webm', 'audio'],
    ];
    $type = $_FILES['media']['type'] ?? '';
    $size = (int)($_FILES['media']['size'] ?? 0);
    if ($size > 20971520) json_response(['success' => false, 'message' => 'Maximum file size is 20 MB.'], 422);
    if (!isset($allowed[$type])) json_response(['success' => false, 'message' => 'Invalid chat media file'], 422);
    [$ext, $mediaType] = $allowed[$type];
    $mediaPath = 'uploads/chats/chat_' . $me['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['media']['tmp_name'], __DIR__ . '/../../' . $mediaPath)) {
        json_response(['success' => false, 'message' => 'Media upload failed'], 500);
    }
}

$pdo = db();
try {
    $pdo->beginTransaction();
    spend_coins((int)$me['id'], 10, 'message_send', 'Message send coin fee', $receiver);
    add_coins($receiver, 8, 'message_receive', 'Message received reward', (int)$me['id']);
    $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, media_path, media_type, reply_to_message_id, delivered_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$me['id'], $receiver, $text, $mediaPath, $mediaType, $replyTo ?: null]);
    $id = (int)$pdo->lastInsertId();
    notify_user($receiver, (int)$me['id'], 'new_message', 'New message from ' . $me['name'], $id);
    $pdo->commit();
    json_response(['success' => true, 'message_id' => $id, 'coins_spent' => 10, 'receiver_earned' => 8]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $message = str_contains($e->getMessage(), 'Insufficient') ? 'Insufficient coins to send message.' : $e->getMessage();
    json_response(['success' => false, 'message' => $message], 409);
}
