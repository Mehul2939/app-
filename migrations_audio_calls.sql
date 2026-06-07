USE mysocialmedia;

CREATE TABLE IF NOT EXISTS call_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  caller_id BIGINT UNSIGNED NOT NULL,
  receiver_id BIGINT UNSIGNED NOT NULL,
  call_type ENUM('audio') NOT NULL DEFAULT 'audio',
  status ENUM('started','answered','missed','rejected','ended') NOT NULL,
  duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_call_logs_caller (caller_id, created_at),
  INDEX idx_call_logs_receiver (receiver_id, created_at),
  INDEX idx_call_logs_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
