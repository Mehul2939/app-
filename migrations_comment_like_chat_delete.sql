USE myself;

CREATE TABLE IF NOT EXISTS comment_likes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  comment_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_comment_like (comment_id, user_id),
  INDEX idx_comment_likes_comment (comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE messages
  ADD COLUMN IF NOT EXISTS deleted_by_receiver TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS deleted_for_everyone TINYINT(1) NOT NULL DEFAULT 0;

