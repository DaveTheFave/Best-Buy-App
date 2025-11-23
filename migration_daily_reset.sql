-- Migration for daily health reset feature
-- This adds a column to track when health was last reset/checked for daily reset

USE bestbuy_tamagotchi;

-- Add last_health_reset column to animal_stats table
ALTER TABLE animal_stats 
ADD COLUMN IF NOT EXISTS last_health_reset DATE DEFAULT NULL;

-- Initialize last_health_reset to today for existing records
UPDATE animal_stats 
SET last_health_reset = CURDATE() 
WHERE last_health_reset IS NULL;

-- Add is_admin column to users table for admin authentication
ALTER TABLE users
ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT FALSE;

-- Note: To create an admin user, run the following command manually with your chosen username:
-- UPDATE users SET is_admin = TRUE WHERE username = 'your_admin_username';
