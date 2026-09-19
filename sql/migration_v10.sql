-- Migration v10: Add email and image_path columns to wallet_connections
-- Run this in your MySQL database / phpMyAdmin / Railway query console if needed.

ALTER TABLE wallet_connections
  ADD COLUMN IF NOT EXISTS email VARCHAR(150) DEFAULT NULL AFTER label,
  ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL AFTER email;
