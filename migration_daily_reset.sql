-- Migration for daily health reset feature
-- This adds a column to track when health was last reset/checked for daily reset

USE bestbuy_tamagotchi;
-- Compatibility-safe migration: checks INFORMATION_SCHEMA before altering

DELIMITER $$
CREATE PROCEDURE ensure_daily_reset_columns()
BEGIN
	-- Add last_health_reset to animal_stats if it doesn't exist
	IF NOT EXISTS (
		SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = 'bestbuy_tamagotchi'
			AND TABLE_NAME = 'animal_stats'
			AND COLUMN_NAME = 'last_health_reset'
	) THEN
		ALTER TABLE bestbuy_tamagotchi.animal_stats
			ADD COLUMN last_health_reset DATE DEFAULT NULL;
	END IF;

	-- Add is_admin to users if it doesn't exist
	IF NOT EXISTS (
		SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = 'bestbuy_tamagotchi'
			AND TABLE_NAME = 'users'
			AND COLUMN_NAME = 'is_admin'
	) THEN
		ALTER TABLE bestbuy_tamagotchi.users
			ADD COLUMN is_admin BOOLEAN DEFAULT FALSE;
	END IF;
END$$

CALL ensure_daily_reset_columns()$$
DROP PROCEDURE ensure_daily_reset_columns$$
DELIMITER ;

-- Initialize last_health_reset to today for existing records
UPDATE bestbuy_tamagotchi.animal_stats
SET last_health_reset = CURDATE()
WHERE last_health_reset IS NULL;

-- Note: To create an admin user, run the following command manually with your chosen username:
-- UPDATE bestbuy_tamagotchi.users SET is_admin = TRUE WHERE username = 'your_admin_username';
