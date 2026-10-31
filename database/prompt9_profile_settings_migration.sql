-- NeighbourNet Prompt 9 migration. Run once against neighbournet_db.
CREATE TABLE IF NOT EXISTS user_preferences (
  user_id INT NOT NULL PRIMARY KEY,
  language ENUM('en','hi') NOT NULL DEFAULT 'en',
  theme_mode ENUM('light','dark','system') NOT NULL DEFAULT 'system',
  colour_theme ENUM('ocean-blue','forest-green','midnight-navy','purple') NOT NULL DEFAULT 'ocean-blue',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_change_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  new_email VARCHAR(150) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pending_email (new_email),
  INDEX idx_email_change_user (user_id),
  CONSTRAINT fk_email_change_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS society_change_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  requested_society_id INT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  reviewed_by INT NULL,
  INDEX idx_society_request_user (user_id,status),
  CONSTRAINT fk_society_request_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_society_request_society FOREIGN KEY (requested_society_id) REFERENCES societies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_society_request_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO user_preferences (user_id)
SELECT id FROM users;
