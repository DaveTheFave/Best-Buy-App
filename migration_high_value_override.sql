-- Migration to add overridden_high_value flag to sales table
-- This tracks revenue entries > 8000 that were manually overridden

USE bestbuy_tamagotchi;

-- Add overridden_high_value column to sales table if it doesn't exist
-- Compatibility-safe migration: check INFORMATION_SCHEMA before altering

DELIMITER $$
CREATE PROCEDURE ensure_overridden_high_value()
BEGIN
	IF NOT EXISTS (
		SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = 'bestbuy_tamagotchi'
			AND TABLE_NAME = 'sales'
			AND COLUMN_NAME = 'overridden_high_value'
	) THEN
		ALTER TABLE bestbuy_tamagotchi.sales
			ADD COLUMN overridden_high_value BOOLEAN DEFAULT FALSE AFTER has_warranty;
	END IF;
END$$

CALL ensure_overridden_high_value()$$
DROP PROCEDURE ensure_overridden_high_value$$
DELIMITER ;

-- Ensure no NULLs (initialize to false for existing rows)
UPDATE bestbuy_tamagotchi.sales
SET overridden_high_value = 0
WHERE overridden_high_value IS NULL;
