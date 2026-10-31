-- NeighbourNet Prompt 11: Admin dashboard, moderation and audit trail
-- Run once after Prompt 10 migration.
CREATE TABLE IF NOT EXISTS admin_audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_id BIGINT NULL,
  details TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_audit_user FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_admin_audit_time (created_at),
  INDEX idx_admin_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS incident_admin_notes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  incident_id INT NOT NULL,
  admin_id INT NOT NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_note_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_note_user FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_admin_note_incident (incident_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Idempotent column additions for MySQL 8.0.29+.
SET @schema_name = DATABASE();
SET @sql = (SELECT IF(COUNT(*)=0, 'ALTER TABLE comments ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN moderation_note VARCHAR(500) NULL', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='comments' AND COLUMN_NAME='is_hidden');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*)=0, 'ALTER TABLE community_votes ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN moderation_note VARCHAR(500) NULL', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='community_votes' AND COLUMN_NAME='is_hidden');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*)=0, 'ALTER TABLE incidents ADD COLUMN review_flag ENUM(''none'',''suspicious'',''potentially_false'',''abusive'') NOT NULL DEFAULT ''none'', ADD COLUMN review_note VARCHAR(500) NULL', 'SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='incidents' AND COLUMN_NAME='review_flag');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
