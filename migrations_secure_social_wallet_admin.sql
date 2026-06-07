USE myself;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS verification_badge TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS xp_points INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS user_level INT NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS referral_code VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS referred_by BIGINT UNSIGNED NULL;

UPDATE users SET referral_code = CONCAT('MY', public_user_id) WHERE referral_code IS NULL OR referral_code = '';
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_referral_code ON users(referral_code);
CREATE INDEX IF NOT EXISTS idx_users_safe_admin ON users(status, created_at, last_active);

ALTER TABLE post_comments
  ADD COLUMN IF NOT EXISTS parent_comment_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS status ENUM('active','reported','deleted') NOT NULL DEFAULT 'active';

CREATE TABLE IF NOT EXISTS post_reactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  reaction_type ENUM('like','love','laugh','sad','angry') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_post_reaction (post_id, user_id),
  INDEX idx_post_reactions_post (post_id, reaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comment_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_id BIGINT UNSIGNED NOT NULL,
  comment_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(255) NOT NULL,
  status ENUM('open','resolved','rejected') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_comment_reports_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wallet_withdrawals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  coins INT NOT NULL,
  amount_inr DECIMAL(10,2) NOT NULL,
  upi_id VARCHAR(120) NOT NULL,
  contact_number VARCHAR(30) NOT NULL,
  account_holder_name VARCHAR(120) NOT NULL,
  status ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(255) DEFAULT NULL,
  rejection_reason VARCHAR(255) DEFAULT NULL,
  estimated_payout_date DATE DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_withdrawals_user (user_id, created_at),
  INDEX idx_withdrawals_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_badges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  badge_name VARCHAR(80) NOT NULL,
  badge_icon VARCHAR(30) NOT NULL DEFAULT 'star',
  awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_badges_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  friend_id BIGINT UNSIGNED NOT NULL,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  is_muted TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_chat_settings (user_id, friend_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE messages
  MODIFY media_type ENUM('text','image','video','audio') NOT NULL DEFAULT 'text',
  ADD COLUMN IF NOT EXISTS reply_to_message_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS forwarded_from_message_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS deleted_for_everyone TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS deleted_by_receiver TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE settings ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 0;
INSERT INTO settings (setting_key, setting_value, is_public) VALUES
('coin_inr_rate', '100:49', 1),
('minimum_withdrawal_coins', '1000', 1)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
