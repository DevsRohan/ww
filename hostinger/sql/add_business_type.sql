-- Migration: Add business_type column to leads table
-- Run this in phpMyAdmin or MySQL CLI before uploading new CSV

ALTER TABLE `leads` ADD COLUMN `business_type` VARCHAR(120) NULL DEFAULT NULL AFTER `business_name`;

-- Add index for filtering by business type
ALTER TABLE `leads` ADD INDEX `idx_business_type` (`business_type`);
