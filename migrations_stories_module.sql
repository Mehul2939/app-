USE mysocialmedia;

ALTER TABLE admin_users
  ADD COLUMN IF NOT EXISTS admin_code VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS role ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
  ADD COLUMN IF NOT EXISTS login_token VARCHAR(128) NULL,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

UPDATE admin_users SET admin_code = CONCAT('ADM', LPAD(id, 3, '0')) WHERE admin_code IS NULL OR admin_code = '';
UPDATE admin_users SET role = 'super_admin' WHERE id = (SELECT first_admin FROM (SELECT MIN(id) first_admin FROM admin_users) x);
CREATE UNIQUE INDEX IF NOT EXISTS uq_admin_code ON admin_users(admin_code);
CREATE INDEX IF NOT EXISTS idx_admin_login_token ON admin_users(login_token);

CREATE TABLE IF NOT EXISTS stories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(220) NOT NULL,
  slug VARCHAR(240) NOT NULL UNIQUE,
  content LONGTEXT NOT NULL,
  excerpt VARCHAR(320) NOT NULL,
  featured_image VARCHAR(255) DEFAULT NULL,
  audio_path VARCHAR(255) DEFAULT NULL,
  category VARCHAR(120) NOT NULL DEFAULT 'Stories',
  keywords VARCHAR(500) DEFAULT NULL,
  seo_tags VARCHAR(500) DEFAULT NULL,
  meta_title VARCHAR(220) NOT NULL,
  meta_description VARCHAR(320) NOT NULL,
  status ENUM('draft','published','scheduled','unpublished') NOT NULL DEFAULT 'draft',
  publish_at DATETIME DEFAULT NULL,
  published_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE RESTRICT,
  INDEX idx_stories_public (status, publish_at, published_at),
  INDEX idx_stories_category (category),
  FULLTEXT INDEX ft_stories_related (title, excerpt, keywords, seo_tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS story_views (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  story_id BIGINT UNSIGNED NOT NULL,
  visitor_hash CHAR(64) NOT NULL,
  first_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  view_count INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_story_visitor (story_id, visitor_hash),
  INDEX idx_story_views_story (story_id),
  FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS story_likes (
  story_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (story_id, user_id),
  FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS story_reactions (
  story_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  reaction_type ENUM('love','hot','amazing','wow') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (story_id, user_id),
  INDEX idx_story_reactions_type (story_id, reaction_type),
  FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS story_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  story_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  parent_comment_id BIGINT UNSIGNED DEFAULT NULL,
  comment_text TEXT NOT NULL,
  status ENUM('active','hidden','deleted') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_story_comments (story_id, status, created_at),
  FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_comment_id) REFERENCES story_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED DEFAULT NULL,
  story_id BIGINT UNSIGNED DEFAULT NULL,
  type VARCHAR(60) NOT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_notifications (admin_id, is_read, created_at),
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
