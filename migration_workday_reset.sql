-- Migration for Workday Reset Feature
-- This migration adds the daily_reset_marker table to track automatic 8am resets

USE bestbuy_tamagotchi;

-- Create the daily reset marker table
-- This table will only ever have one row (id=1) to track the last reset
CREATE TABLE IF NOT EXISTS daily_reset_marker (
    id INT PRIMARY KEY,
    last_reset_date DATE NOT NULL,
    last_reset_time DATETIME NOT NULL
);

-- Initialize the marker with today's date if it's 8am or later
-- Otherwise, don't insert (will be created on first 8am login)
INSERT INTO daily_reset_marker (id, last_reset_date, last_reset_time)
SELECT 1, CURDATE(), NOW()
WHERE HOUR(NOW()) >= 8
ON DUPLICATE KEY UPDATE id = id;
