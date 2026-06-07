<?php
require_once __DIR__ . '/../helpers/admin_auth.php';
current_admin();
$pdo = db();
$cards = [
    'total_users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'active_users' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
    'new_registrations' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'total_posts' => (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'total_comments' => (int)$pdo->query('SELECT COUNT(*) FROM post_comments')->fetchColumn(),
    'total_messages' => (int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
    'total_gifts' => (int)$pdo->query('SELECT COUNT(*) FROM gift_transactions')->fetchColumn(),
    'coins_distributed' => (int)$pdo->query('SELECT COALESCE(SUM(total_earned), 0) FROM user_wallets')->fetchColumn(),
    'total_withdrawals' => (int)$pdo->query('SELECT COUNT(*) FROM wallet_withdrawals')->fetchColumn(),
    'pending_withdrawals' => (int)$pdo->query("SELECT COUNT(*) FROM wallet_withdrawals WHERE status = 'pending'")->fetchColumn(),
    'total_stories' => (int)$pdo->query('SELECT COUNT(*) FROM stories')->fetchColumn(),
    'published_stories' => (int)$pdo->query("SELECT COUNT(*) FROM stories WHERE status = 'published'")->fetchColumn(),
    'draft_stories' => (int)$pdo->query("SELECT COUNT(*) FROM stories WHERE status = 'draft'")->fetchColumn(),
    'story_views' => (int)$pdo->query('SELECT COALESCE(SUM(view_count),0) FROM story_views')->fetchColumn(),
    'story_comments' => (int)$pdo->query("SELECT COUNT(*) FROM story_comments WHERE status = 'active'")->fetchColumn(),
    'story_reactions' => (int)$pdo->query('SELECT COUNT(*) FROM story_reactions')->fetchColumn(),
    'total_admins' => (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE status = 'active'")->fetchColumn(),
];
json_response(['success' => true, 'cards' => $cards]);
