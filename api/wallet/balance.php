<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/wallet.php';
$me = current_user();
$wallet = wallet_row((int)$me['id']);
$pending = db()->prepare("SELECT COALESCE(SUM(coins), 0) FROM wallet_withdrawals WHERE user_id = ? AND status IN ('pending','approved')");
$pending->execute([$me['id']]);
$today = date('Y-m-d');
$stmt = db()->prepare('SELECT * FROM daily_rewards WHERE user_id = ? ORDER BY claimed_date DESC LIMIT 1');
$stmt->execute([$me['id']]);
$last = $stmt->fetch();
$claimedToday = $last && $last['claimed_date'] === $today;
$nextDay = $claimedToday ? (((int)$last['reward_day'] % 7) + 1) : ($last ? (((int)$last['reward_day'] % 7) + 1) : 1);
$rewards = [1 => 50, 2 => 60, 3 => 70, 4 => 80, 5 => 90, 6 => 100, 7 => 50];
json_response(['success' => true, 'wallet' => $wallet, 'pending_coins' => (int)$pending->fetchColumn(), 'withdrawable_coins' => (int)$wallet['coins_balance'], 'total_earnings_inr' => round(((int)$wallet['total_earned'] / 100) * 49, 2), 'claimed_today' => $claimedToday, 'next_day' => $nextDay, 'next_reward' => $rewards[$nextDay]]);
