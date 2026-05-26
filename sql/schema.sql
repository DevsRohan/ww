-- =============================================================
-- WhatsApp CRM + Cold Outreach Operating System
-- MySQL Schema (Production Grade)
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- Table: users (dashboard authentication)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(180) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','operator') NOT NULL DEFAULT 'admin',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: leads
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_name` VARCHAR(255) NOT NULL,
  `address` TEXT NULL,
  `locality` VARCHAR(120) NULL,
  `city` VARCHAR(120) NULL,
  `state` VARCHAR(120) NULL,
  `phone_number` VARCHAR(20) NOT NULL,
  `phone_e164` VARCHAR(20) NOT NULL,
  `website_url` VARCHAR(500) NULL,
  `website_status` ENUM('has_website','no_website','unknown') NOT NULL DEFAULT 'unknown',
  `rating` DECIMAL(3,2) NULL,
  `review_count` INT UNSIGNED NULL DEFAULT 0,
  `whatsapp_status` ENUM('pending','valid','not_on_whatsapp','invalid','failed') NOT NULL DEFAULT 'pending',
  `outreach_status` ENUM('new','queued','sending','sent','delivered','read','replied','failed','skipped','blocked') NOT NULL DEFAULT 'new',
  `pitch_type` ENUM('type_a','type_b','unknown') NOT NULL DEFAULT 'unknown',
  `language_preference` VARCHAR(40) NOT NULL DEFAULT 'hinglish',
  `tags` JSON NULL,
  `notes` TEXT NULL,
  `last_outbound_at` DATETIME NULL,
  `last_inbound_at` DATETIME NULL,
  `last_contacted_at` DATETIME NULL,
  `unread_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `source` VARCHAR(60) NOT NULL DEFAULT 'csv_import',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_leads_phone_e164` (`phone_e164`),
  KEY `idx_leads_whatsapp_status` (`whatsapp_status`),
  KEY `idx_leads_outreach_status` (`outreach_status`),
  KEY `idx_leads_city` (`city`),
  KEY `idx_leads_state` (`state`),
  KEY `idx_leads_pitch_type` (`pitch_type`),
  KEY `idx_leads_last_inbound` (`last_inbound_at`),
  KEY `idx_leads_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: messages
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` BIGINT UNSIGNED NOT NULL,
  `direction` ENUM('inbound','outbound') NOT NULL,
  `sender` VARCHAR(120) NOT NULL DEFAULT 'system',
  `message_text` MEDIUMTEXT NOT NULL,
  `media_url` VARCHAR(500) NULL,
  `wa_message_id` VARCHAR(160) NULL,
  `status` ENUM('queued','sent','delivered','read','failed','received') NOT NULL DEFAULT 'queued',
  `error_message` TEXT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `is_first_outreach` TINYINT(1) NOT NULL DEFAULT 0,
  `meta` JSON NULL,
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_messages_wa_message_id` (`wa_message_id`),
  KEY `idx_messages_lead_id` (`lead_id`),
  KEY `idx_messages_direction` (`direction`),
  KEY `idx_messages_status` (`status`),
  KEY `idx_messages_timestamp` (`timestamp`),
  CONSTRAINT `fk_messages_lead_id` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: campaigns
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(180) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('draft','running','paused','completed','stopped') NOT NULL DEFAULT 'draft',
  `total_leads` INT UNSIGNED NOT NULL DEFAULT 0,
  `sent_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `replied_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `skipped_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `daily_limit` INT UNSIGNED NOT NULL DEFAULT 60,
  `min_delay_seconds` INT UNSIGNED NOT NULL DEFAULT 120,
  `max_delay_seconds` INT UNSIGNED NOT NULL DEFAULT 300,
  `filter_payload` JSON NULL,
  `started_at` DATETIME NULL,
  `paused_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaigns_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: campaign_leads (many-to-many tracking)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_leads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `lead_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('queued','sending','sent','failed','skipped','replied') NOT NULL DEFAULT 'queued',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_attempt_at` DATETIME NULL,
  `error_message` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_campaign_leads` (`campaign_id`, `lead_id`),
  KEY `idx_campaign_leads_status` (`status`),
  CONSTRAINT `fk_cl_campaign_id` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cl_lead_id` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: settings (key-value config)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(120) NOT NULL,
  `setting_value` MEDIUMTEXT NULL,
  `setting_type` ENUM('string','int','bool','json','secret') NOT NULL DEFAULT 'string',
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `description` VARCHAR(255) NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: logs
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `level` ENUM('debug','info','warn','error','critical') NOT NULL DEFAULT 'info',
  `source` VARCHAR(80) NOT NULL DEFAULT 'app',
  `message` TEXT NOT NULL,
  `context` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_level` (`level`),
  KEY `idx_logs_source` (`source`),
  KEY `idx_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: webhook_events
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` VARCHAR(120) NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `payload` JSON NOT NULL,
  `signature` VARCHAR(255) NULL,
  `processed` TINYINT(1) NOT NULL DEFAULT 0,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_error` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_webhook_event_id` (`event_id`),
  KEY `idx_webhook_processed` (`processed`),
  KEY `idx_webhook_event_type` (`event_type`),
  KEY `idx_webhook_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: csv_imports
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `csv_imports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename` VARCHAR(255) NOT NULL,
  `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `imported_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `duplicate_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `error_log` TEXT NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_csv_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: activity_log (lead-level audit trail)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` BIGINT UNSIGNED NULL,
  `actor` VARCHAR(80) NOT NULL DEFAULT 'system',
  `action` VARCHAR(80) NOT NULL,
  `description` VARCHAR(500) NULL,
  `meta` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_lead_id` (`lead_id`),
  KEY `idx_activity_action` (`action`),
  KEY `idx_activity_created_at` (`created_at`),
  CONSTRAINT `fk_activity_lead_id` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
