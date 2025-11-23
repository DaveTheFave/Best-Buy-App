-- Migration to add overridden_high_value flag to sales table
-- This tracks revenue entries > 8000 that were manually overridden

USE bestbuy_tamagotchi;

-- Add overridden_high_value column to sales table if it doesn't exist
ALTER TABLE sales 
ADD COLUMN IF NOT EXISTS overridden_high_value BOOLEAN DEFAULT FALSE 
AFTER has_warranty;
