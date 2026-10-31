-- NeighbourNet Database Schema & Seed Data
-- Updated for Prompt 4: Resident Dashboard & Complete Incident Reporting

CREATE DATABASE IF NOT EXISTS `neighbournet_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `neighbournet_db`;

DROP TABLE IF EXISTS `cluster_audit_logs`;
DROP TABLE IF EXISTS `cluster_reports`;
DROP TABLE IF EXISTS `incident_clusters`;
DROP TABLE IF EXISTS `community_votes`;
DROP TABLE IF EXISTS `incident_updates`;
DROP TABLE IF EXISTS `incidents`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `societies`;

-- 1. Societies / Neighbourhoods Table
CREATE TABLE `societies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(120) NOT NULL,
    `ward_sector` VARCHAR(80) NOT NULL,
    `city` VARCHAR(80) NOT NULL DEFAULT 'Metro City',
    `pincode` VARCHAR(10) NOT NULL DEFAULT '110001',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Users Table
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('resident', 'admin') NOT NULL DEFAULT 'resident',
    `society_id` INT NULL,
    `profile_image` VARCHAR(255) NULL DEFAULT '/neighbournet/assets/images/default-avatar.svg',
    `account_status` ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'active',
    `reputation_score` INT NOT NULL DEFAULT 15,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`society_id`) REFERENCES `societies`(`id`) ON DELETE SET NULL,
    INDEX `idx_role` (`role`),
    INDEX `idx_society` (`society_id`),
    INDEX `idx_status` (`account_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Login Attempts Table
CREATE TABLE `login_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_rate_limit` (`email`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Incident Categories Table (9 Official Categories)
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(80) NOT NULL,
    `slug` VARCHAR(80) NOT NULL UNIQUE,
    `color_code` VARCHAR(10) NOT NULL DEFAULT '#e74c3c',
    `icon_class` VARCHAR(50) NOT NULL DEFAULT 'fa-exclamation-triangle',
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Incidents Table (Prompt 4 Specification)
CREATE TABLE `incidents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `society_id` INT NULL,
    `title` VARCHAR(160) NOT NULL,
    `description` TEXT NOT NULL,
    `voice_input_used` TINYINT(1) NOT NULL DEFAULT 0,
    `original_transcript` TEXT NULL,
    `original_language` VARCHAR(10) NULL,
    `translated_transcript` TEXT NULL,
    `translation_language` VARCHAR(10) NULL,
    `selected_description_type` ENUM('manual', 'original', 'translated', 'combined') NOT NULL DEFAULT 'manual',
    `image_path` VARCHAR(255) NULL,
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,
    `location_text` VARCHAR(255) NOT NULL,
    `incident_time` DATETIME NOT NULL,
    `status` ENUM('pending', 'under_verification', 'verified', 'in_progress', 'resolved') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`society_id`) REFERENCES `societies`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_society` (`society_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Community Feedback & Verification Votes Table (Prompt 7 Specification)
CREATE TABLE IF NOT EXISTS `community_votes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `incident_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `vote_type` ENUM('TRUE', 'FALSE', 'MAYBE') NOT NULL,
    `optional_comment` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_incident_vote` (`incident_id`, `user_id`),
    FOREIGN KEY (`incident_id`) REFERENCES `incidents`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_incident_vote` (`incident_id`, `vote_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Incident Clusters Table (Prompt 8 Specification)
CREATE TABLE IF NOT EXISTS `incident_clusters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `category_id` INT NOT NULL,
    `approx_latitude` DECIMAL(10, 8) NULL,
    `approx_longitude` DECIMAL(11, 8) NULL,
    `approx_location_text` VARCHAR(255) NULL,
    `first_report_time` DATETIME NOT NULL,
    `latest_report_time` DATETIME NOT NULL,
    `status` ENUM('suggested', 'approved', 'rejected') NOT NULL DEFAULT 'suggested',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
    INDEX `idx_cluster_status` (`status`),
    INDEX `idx_cluster_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Cluster Reports Junction Table
CREATE TABLE IF NOT EXISTS `cluster_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cluster_id` INT NOT NULL,
    `incident_id` INT NOT NULL,
    `similarity_score` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    `added_by` ENUM('system_auto', 'admin') NOT NULL DEFAULT 'system_auto',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_cluster_incident` (`cluster_id`, `incident_id`),
    FOREIGN KEY (`cluster_id`) REFERENCES `incident_clusters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`incident_id`) REFERENCES `incidents`(`id`) ON DELETE CASCADE,
    INDEX `idx_incident` (`incident_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Cluster Administrative Audit Logs
CREATE TABLE IF NOT EXISTS `cluster_audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cluster_id` INT NOT NULL,
    `admin_id` INT NOT NULL,
    `action` ENUM('approve', 'reject', 'add_report', 'remove_report', 'separate') NOT NULL,
    `details` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cluster_id`) REFERENCES `incident_clusters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Incident Lifecycle Audit Updates (Admin official updates)
CREATE TABLE `incident_updates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `incident_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `old_status` ENUM('pending', 'under_verification', 'verified', 'in_progress', 'resolved') NOT NULL,
    `new_status` ENUM('pending', 'under_verification', 'verified', 'in_progress', 'resolved') NOT NULL,
    `note` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`incident_id`) REFERENCES `incidents`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Community Comments Table
CREATE TABLE `comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `incident_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `comment` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`incident_id`) REFERENCES `incidents`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- SEED DATA
-- ========================================================

-- Seed Societies
INSERT INTO `societies` (`id`, `name`, `ward_sector`, `city`, `pincode`) VALUES
(1, 'Central Avenue Residents Welfare', 'Sector 4', 'Metro City', '110001'),
(2, 'Oakwood Green Gated Enclave', 'Sector 7', 'Metro City', '110002'),
(3, 'Sunrise High Society', 'Sector 9', 'Metro City', '110003'),
(4, 'Metro Palm Apartments', 'Sector 12', 'Metro City', '110004');

-- No default users are created. Register your own resident/admin accounts.

-- Seed Categories (9 Official Categories)
INSERT INTO `categories` (`id`, `name`, `slug`, `color_code`, `icon_class`, `description`) VALUES
(1, 'Water supply disruption', 'water', '#0284c7', 'fa-faucet-drip', 'Low pressure, contaminated water, or pipeline leakage'),
(2, 'Electricity outage', 'electricity', '#eab308', 'fa-bolt', 'Power breakdown, transformer issues, sparking wires'),
(3, 'Road damage', 'roads', '#ea580c', 'fa-road', 'Potholes, broken road surface, open excavation'),
(4, 'Garbage accumulation', 'garbage', '#16a34a', 'fa-trash-can', 'Overflowing community bins, illegal roadside dumping'),
(5, 'Streetlight malfunction', 'lighting', '#f59e0b', 'fa-lightbulb', 'Non-functional lamps, flickering poles, dark streets'),
(6, 'Drainage or flooding', 'drainage', '#06b6d4', 'fa-water', 'Clogged drains, standing monsoon water, sewage overflow'),
(7, 'Fallen trees or blocked roads', 'blocked-roads', '#15803d', 'fa-tree', 'Uprooted trees, fallen branches obstructing vehicular access'),
(8, 'Public infrastructure damage', 'infrastructure', '#dc2626', 'fa-building-circle-exclamation', 'Damaged bus shelters, broken park benches, missing covers'),
(9, 'Other neighbourhood issues', 'other', '#64748b', 'fa-circle-question', 'Any other municipal or community issue');

-- No synthetic incidents are inserted. The fresh database starts with an empty incident feed.

-- =============================================================
-- NEIGHBOURNET COMPLETE FEATURE SCHEMA (PROMPTS 8-12)
-- This file is intended for a NEW empty database only.
-- For an existing installation, apply the numbered migrations instead.
-- =============================================================

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
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pending_email (new_email),
  UNIQUE KEY uq_email_change_token (token_hash),
  INDEX idx_email_change_user (user_id),
  CONSTRAINT fk_email_change_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS society_change_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  requested_society_id INT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  reviewed_by INT NULL,
  INDEX idx_society_request_user (user_id,status),
  INDEX idx_society_request_status (status,created_at),
  CONSTRAINT fk_society_request_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_society_request_society FOREIGN KEY (requested_society_id) REFERENCES societies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_society_request_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS community_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  incident_id INT NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  created_by INT NULL,
  status ENUM('open','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
  priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  max_volunteers SMALLINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_task_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
  CONSTRAINT fk_task_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_task_incident_status (incident_id,status),
  INDEX idx_task_status_priority (status,priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_volunteers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  user_id INT NOT NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_task_volunteer (task_id,user_id),
  CONSTRAINT fk_volunteer_task FOREIGN KEY (task_id) REFERENCES community_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_volunteer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_volunteer_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_updates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  author_id INT NULL,
  update_text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_task_update_task FOREIGN KEY (task_id) REFERENCES community_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_task_update_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_task_update_time (task_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  task_id INT NULL,
  incident_id INT NULL,
  notification_type VARCHAR(40) NOT NULL DEFAULT 'task_update',
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notification_task FOREIGN KEY (task_id) REFERENCES community_tasks(id) ON DELETE SET NULL,
  CONSTRAINT fk_notification_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL,
  INDEX idx_notification_user_unread (user_id,is_read,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  INDEX idx_admin_audit_entity (entity_type,entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS incident_admin_notes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  incident_id INT NOT NULL,
  admin_id INT NOT NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_note_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_note_user FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_admin_note_incident (incident_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Canonical incident lifecycle history. incident_updates remains for backwards
-- compatibility with existing pages; status endpoints write to both tables.
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

-- Prompt 11 moderation fields; direct ALTERs keep this fresh-install script simple.
ALTER TABLE comments
  ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN moderation_note VARCHAR(500) NULL;
ALTER TABLE community_votes
  ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN moderation_note VARCHAR(500) NULL;
ALTER TABLE incidents
  ADD COLUMN review_flag ENUM('none','suspicious','potentially_false','abusive') NOT NULL DEFAULT 'none',
  ADD COLUMN review_note VARCHAR(500) NULL;

-- Additional query indexes used by feeds, dashboards and moderation.
ALTER TABLE incidents
  ADD INDEX idx_incident_created (created_at),
  ADD INDEX idx_incident_time (incident_time),
  ADD INDEX idx_incident_status_created (status,created_at);
ALTER TABLE incident_updates ADD INDEX idx_incident_updates_history (incident_id,created_at);
ALTER TABLE cluster_audit_logs ADD INDEX idx_cluster_audit_created (created_at);
ALTER TABLE comments ADD INDEX idx_comments_incident_visible (incident_id,is_hidden,created_at);
ALTER TABLE community_votes ADD INDEX idx_votes_incident_visible (incident_id,is_hidden,created_at);

INSERT IGNORE INTO user_preferences (user_id) SELECT id FROM users;
