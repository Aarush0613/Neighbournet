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

-- Seed Users
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `society_id`, `account_status`, `reputation_score`) VALUES
(1, 'Civic Administrator', 'admin@neighbournet.org', '$2y$10$xRJ7HlhJbXlLfZM8H2a6ru96uUy6uKQMWYtBhCVd/gx0vBEgX9sNq', 'admin', 1, 'active', 100),
(2, 'Aarav Patel', 'resident@neighbournet.org', '$2y$10$/ZRsgrTjM6Ftx33132q0seNn9Ex4U3hxpNhxVTYWeOLfco4g5l4/e', 'resident', 2, 'active', 35);

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

-- Seed Incidents
INSERT INTO `incidents` (`id`, `user_id`, `category_id`, `society_id`, `title`, `description`, `image_path`, `latitude`, `longitude`, `location_text`, `incident_time`, `status`) VALUES
(1, 2, 8, 1, 'Deep Uncovered Manhole Near Metro Gate 2', 'A high-risk open manhole without warning cones. Poses immediate danger to pedestrians and cyclists at night.', NULL, 28.61450000, 77.20850000, 'Metro Pillar 142, Gate 2, Central Road', NOW() - INTERVAL 3 HOUR, 'verified'),
(2, 2, 5, 2, 'Non-functional Streetlights on 4th Main Cross', 'Entire stretch of 6 consecutive streetlights are out since Monday. Area is pitch dark after 7 PM.', NULL, 28.61820000, 77.21500000, 'Opposite Green Valley Community Park', NOW() - INTERVAL 1 DAY, 'in_progress'),
(3, 1, 3, 1, 'Major Road Cave-in & Pothole Cluster', 'Severe pothole cluster damaging vehicles after water pipeline repair was left unpaved.', NULL, 28.61100000, 77.20300000, 'Intersection of Market Street and 5th Avenue', NOW() - INTERVAL 2 DAY, 'pending'),
(4, 1, 4, 3, 'Garbage Dump Overflowing onto Pedestrian Walkway', 'Illegal dumping accumulating for 4 days. Strong odor and stray animal disturbance.', NULL, 28.62200000, 77.21000000, 'Near Community Health Centre Gate', NOW() - INTERVAL 3 DAY, 'resolved'),
(5, 2, 1, 2, 'Clean Water Main Pipeline Leakage', 'Fresh drinking water gushing onto the street from an underground pipe joint.', NULL, 28.61600000, 77.21950000, 'Near Sunrise High School Playground', NOW() - INTERVAL 4 HOUR, 'under_verification');
