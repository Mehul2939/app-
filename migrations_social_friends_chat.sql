USE myself;

ALTER TABLE users ADD COLUMN IF NOT EXISTS public_user_id CHAR(10) NULL AFTER id;
UPDATE users SET public_user_id = CAST(1000000000 + id AS CHAR) WHERE public_user_id IS NULL OR public_user_id = '';
ALTER TABLE users MODIFY public_user_id CHAR(10) NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_public_user_id ON users(public_user_id);

ALTER TABLE messages
  ADD COLUMN IF NOT EXISTS media_type ENUM('text','image','video') NOT NULL DEFAULT 'text',
  ADD COLUMN IF NOT EXISTS is_typing TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS friend_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sender_id BIGINT UNSIGNED NOT NULL,
  receiver_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_friend_request (sender_id, receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS friends (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  friend_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_friend_pair (user_id, friend_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

UPDATE user_wallets SET coins_balance = 100, total_earned = 100 WHERE coins_balance = 0 AND total_earned = 0;

ALTER TABLE coin_transactions MODIFY type ENUM('daily_reward','gift_send','message_send','message_receive','admin_add','admin_deduct','refund') NOT NULL;
