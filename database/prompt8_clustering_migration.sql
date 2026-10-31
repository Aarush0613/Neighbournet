-- NeighbourNet Prompt 8 migration for databases already in use.
-- Run this once after backing up your database. It does not drop existing reports.
CREATE TABLE IF NOT EXISTS incident_clusters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  category_id INT NOT NULL,
  approx_latitude DECIMAL(10,8) NULL,
  approx_longitude DECIMAL(11,8) NULL,
  approx_location_text VARCHAR(255) NULL,
  first_report_time DATETIME NOT NULL,
  latest_report_time DATETIME NOT NULL,
  status ENUM('suggested','approved','rejected') NOT NULL DEFAULT 'suggested',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cluster_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  INDEX idx_cluster_status(status), INDEX idx_cluster_category(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cluster_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cluster_id INT NOT NULL,
  incident_id INT NOT NULL,
  similarity_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  added_by ENUM('system_auto','admin') NOT NULL DEFAULT 'system_auto',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_cluster_incident(cluster_id,incident_id),
  CONSTRAINT fk_cluster_reports_cluster FOREIGN KEY (cluster_id) REFERENCES incident_clusters(id) ON DELETE CASCADE,
  CONSTRAINT fk_cluster_reports_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
  INDEX idx_incident(incident_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cluster_audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cluster_id INT NOT NULL,
  admin_id INT NOT NULL,
  action ENUM('approve','reject','add_report','remove_report','separate') NOT NULL,
  details TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cluster_audit_cluster FOREIGN KEY (cluster_id) REFERENCES incident_clusters(id) ON DELETE CASCADE,
  CONSTRAINT fk_cluster_audit_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_cluster_audit_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
