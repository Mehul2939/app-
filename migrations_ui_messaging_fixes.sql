USE myself;

UPDATE users
SET public_user_id = UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 10))
WHERE public_user_id REGEXP '^[0-9]{10}$';

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_public_user_id_ui ON users(public_user_id);
