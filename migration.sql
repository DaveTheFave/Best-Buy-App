-- Migration to add goal tracking fields to work_sessions table
-- Run this to update existing database

USE bestbuy_tamagotchi;

-- Add new columns to work_sessions table
-- If columns already exist, these will fail silently with warnings

ALTER TABLE work_sessions ADD COLUMN goal_paid_memberships INT DEFAULT 0;
ALTER TABLE work_sessions ADD COLUMN goal_credit_cards INT DEFAULT 0;
ALTER TABLE work_sessions ADD COLUMN current_paid_memberships INT DEFAULT 0;
ALTER TABLE work_sessions ADD COLUMN current_credit_cards INT DEFAULT 0;

-- Update existing sessions with default values based on work hours
UPDATE work_sessions 
SET goal_paid_memberships = CEIL(work_hours / 4),
    goal_credit_cards = CEIL(work_hours / 7),
    goal_amount = work_hours * 500
WHERE (goal_paid_memberships = 0 OR goal_paid_memberships IS NULL);

SELECT 'Migration completed! (Ignore duplicate column warnings if any)' as message;
