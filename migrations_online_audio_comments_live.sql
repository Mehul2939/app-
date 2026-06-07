USE mysocialmedia;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_online TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS last_seen DATETIME NULL;

ALTER TABLE post_media
  MODIFY media_type ENUM('image','video','audio') NOT NULL;

