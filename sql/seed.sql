-- =============================================================
-- Seed Data: Default Settings + Admin User
-- =============================================================
-- NOTE: Default admin password = "admin@12345" (hash below uses PHP password_hash with PASSWORD_BCRYPT)
-- CHANGE IMMEDIATELY after first login.

INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `is_active`)
VALUES
('Admin', 'admin@example.com', '$2y$12$H3KldPBN2Sdz3tLHtNUBk.kPSBrkasGXp34jKCatgFBHJ/Z0DgIBK', 'admin', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- -------------------------------------------------------------
-- Default Settings
-- -------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `is_public`, `description`) VALUES
('app_name', 'WhatsApp CRM', 'string', 1, 'Application brand name'),
('app_tagline', 'Cold Outreach Operating System', 'string', 1, 'Short tagline'),
('groq_api_key', '', 'secret', 0, 'Groq API key for AI personalization'),
('groq_model', 'llama-3.3-70b-versatile', 'string', 0, 'Groq model name'),
('node_api_url', 'https://your-space.hf.space', 'string', 0, 'Hugging Face Node.js backend URL'),
('node_api_key', '', 'secret', 0, 'API key for HF Node backend'),
('socket_url', 'https://your-space.hf.space', 'string', 1, 'Public Socket.io URL for browser'),
('webhook_secret', '', 'secret', 0, 'HMAC secret for webhook signature verification'),
('campaign_min_delay', '120', 'int', 0, 'Minimum random delay (seconds) between sends'),
('campaign_max_delay', '300', 'int', 0, 'Maximum random delay (seconds) between sends'),
('campaign_daily_limit', '60', 'int', 0, 'Max messages per day across all campaigns'),
('campaign_batch_size', '5', 'int', 0, 'Batch size per cron run'),
('campaign_active_hours_start', '10', 'int', 0, 'Active sending start hour (24h)'),
('campaign_active_hours_end', '20', 'int', 0, 'Active sending end hour (24h)'),
('retry_max_attempts', '3', 'int', 0, 'Max retry attempts on failed sends'),
('retry_backoff_seconds', '600', 'int', 0, 'Backoff seconds between retries'),
('feature_dark_mode', '0', 'bool', 1, 'UI dark mode toggle'),
('feature_notification_sound', '1', 'bool', 1, 'Play sound on new inbound message'),
('feature_logging', '1', 'bool', 0, 'Enable verbose logging'),
('owner_brand_name', 'Rohan Digital', 'string', 1, 'Outreach sender brand name (used in messages)'),
('owner_signature', 'Rohan from Rohan Digital', 'string', 0, 'Signature appended to messages'),
('owner_offerings', 'Landing Pages, Business Websites, eCommerce, Web Apps, AI Agents, Automation, Android Apps, Chrome Extensions, Digital Marketing', 'string', 0, 'Service catalog (AI picks relevant subset)'),
('whatsapp_country_code', '91', 'string', 0, 'Default country code for phone normalization'),
('engine_status_cache', '{"connected":false,"qr":null,"last_seen":null}', 'json', 0, 'Last known engine status snapshot')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
