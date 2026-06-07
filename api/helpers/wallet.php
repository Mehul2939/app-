<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/social.php';

function ensure_wallet(int $userId): void
{
    $stmt = db()->prepare('INSERT IGNORE INTO user_wallets (user_id) VALUES (?)');
    $stmt->execute([$userId]);
}

function wallet_row(int $userId, bool $lock = false): array
{
    ensure_wallet($userId);
    $sql = 'SELECT * FROM user_wallets WHERE user_id = ?' . ($lock ? ' FOR UPDATE' : '');
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [];
}

function add_coins(int $userId, int $coins, string $type, string $reason = '', ?int $referenceId = null): array
{
    if ($coins <= 0) {
        throw new RuntimeException('Coins must be positive');
    }
    $wallet = wallet_row($userId, true);
    $before = (int)$wallet['coins_balance'];
    $after = $before + $coins;
    db()->prepare('UPDATE user_wallets SET coins_balance = ?, total_earned = total_earned + ? WHERE user_id = ?')
        ->execute([$after, $coins, $userId]);
    db()->prepare('INSERT INTO coin_transactions (user_id, type, coins, balance_before, balance_after, reason, reference_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$userId, $type, $coins, $before, $after, $reason, $referenceId]);
    return ['balance_before' => $before, 'balance_after' => $after];
}

function spend_coins(int $userId, int $coins, string $type, string $reason = '', ?int $referenceId = null): array
{
    if ($coins <= 0) {
        throw new RuntimeException('Coins must be positive');
    }
    $wallet = wallet_row($userId, true);
    $before = (int)$wallet['coins_balance'];
    if ($before < $coins) {
        throw new RuntimeException('Insufficient coin balance');
    }
    $after = $before - $coins;
    db()->prepare('UPDATE user_wallets SET coins_balance = ?, total_spent = total_spent + ? WHERE user_id = ?')
        ->execute([$after, $coins, $userId]);
    db()->prepare('INSERT INTO coin_transactions (user_id, type, coins, balance_before, balance_after, reason, reference_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$userId, $type, -$coins, $before, $after, $reason, $referenceId]);
    return ['balance_before' => $before, 'balance_after' => $after];
}
