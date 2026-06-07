USE mysocialmedia;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS gender ENUM('Male','Female','Other') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS state VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS city VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS is_demo_user TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS account_created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS last_active_at DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS profile_completed TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS interests VARCHAR(500) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS preferred_gender_filter ENUM('Male','Female','Other','Any') NOT NULL DEFAULT 'Any';

CREATE INDEX IF NOT EXISTS idx_users_discovery ON users(status, is_demo_user, gender, state, city, last_active_at);
CREATE INDEX IF NOT EXISTS idx_users_location ON users(latitude, longitude);

UPDATE users u
LEFT JOIN user_profiles p ON p.user_id = u.id
SET u.gender = CASE WHEN p.gender IN ('Male','Female','Other') THEN p.gender ELSE u.gender END,
    u.city = COALESCE(NULLIF(p.city,''), u.city),
    u.bio = COALESCE(NULLIF(p.bio,''), u.bio),
    u.profile_photo = COALESCE(NULLIF(p.profile_photo,''), u.profile_photo),
    u.account_created_at = COALESCE(u.account_created_at, u.created_at),
    u.last_active_at = COALESCE(u.last_active_at, u.last_active, u.last_seen),
    u.profile_completed = IF(COALESCE(p.bio,'') <> '' AND COALESCE(p.city,'') <> '', 1, u.profile_completed);

