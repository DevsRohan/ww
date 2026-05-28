-- Migration: Add business_type column to leads table
-- Date: 2026-05-28
-- Description: Adds business_type field for hyper-personalized AI messaging
--              Used for industry-specific prompts and language selection

ALTER TABLE leads ADD COLUMN business_type VARCHAR(120) NULL AFTER business_name;

-- Add index for filtering by business_type
CREATE INDEX idx_leads_business_type ON leads (business_type);
