<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/wallet.php';
$me = current_user();
$today = date('Y-m-d');
$pdo = db();
try {
    $pdo->beginTransaction();
    $lastStmt = $pdo->prepare('SELECT * FROM daily_rewards WHERE user_id = ? ORDER BY claimed_date DESC LIMIT 1 FOR UPDATE');
    $lastStmt->execute([$me['id']]);
    $last = $lastStmt->fetch();
    if ($last && $last['claimed_date'] === $today) {
        throw new RuntimeException('Daily reward already claimed today');
    }
    $day = $last ? (((int)$last['reward_day'] % 7) + 1) : 1;
    $rewards = [1 => 50, 2 => 60, 3 => 70, 4 => 80, 5 => 90, 6 => 100, 7 => 50];
    $coins = $rewards[$day];
    $pdo->prepare('INSERT INTO daily_rewards (user_id, reward_day, coins, claimed_date, streak_count) VALUES (?, ?, ?, ?, ?)')
        ->execute([$me['id'], $day, $coins, $today, $last ? ((int)$last['streak_count'] + 1) : 1]);
    $rewardId = (int)$pdo->lastInsertId();
    add_coins((int)$me['id'], $coins, 'daily_reward', 'Daily reward day ' . $day, $rewardId);
    notify_user((int)$me['id'], null, 'daily_reward', 'You claimed ' . $coins . ' coins', $rewardId);
    $pdo->commit();
    json_response(['success' => true, 'message' => 'Reward claimed', 'coins' => $coins, 'reward_day' => $day]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => $e->getMessage()], 409);
}

