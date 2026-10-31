-- Prompt 12: apply to an existing NeighbourNet database after Prompt 11.
-- Back up the database first. Safe to re-run: source_update_id prevents duplicate backfill.
CREATE TABLE IF NOT EXISTS incident_history (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  incident_id INT NOT NULL,
  actor_id INT NOT NULL,
  source_update_id INT NULL UNIQUE,
  old_status ENUM('pending','under_verification','verified','in_progress','resolved') NOT NULL,
  new_status ENUM('pending','under_verification','verified','in_progress','resolved') NOT NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_incident_history_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
  CONSTRAINT fk_incident_history_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_incident_history_incident (incident_id,created_at),
  INDEX idx_incident_history_actor (actor_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO incident_history
  (incident_id, actor_id, source_update_id, old_status, new_status, note, created_at)
SELECT incident_id, user_id, id, old_status, new_status, note, created_at
FROM incident_updates;

-- Add these indexes only when not already present (MySQL 5.7+/8.x compatible).
SET @db = DATABASE();
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='incidents' AND index_name='idx_incident_created')=0, 'ALTER TABLE incidents ADD INDEX idx_incident_created(created_at)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='incidents' AND index_name='idx_incident_time')=0, 'ALTER TABLE incidents ADD INDEX idx_incident_time(incident_time)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='incidents' AND index_name='idx_incident_status_created')=0, 'ALTER TABLE incidents ADD INDEX idx_incident_status_created(status,created_at)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
