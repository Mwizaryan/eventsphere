-- Migration: Add is_admin column to users table
-- Run this in phpMyAdmin: event_site_db > SQL tab > paste & execute.
-- This adds a TINYINT column (0 = regular user, 1 = admin) with default value 0.

ALTER TABLE users 
ADD COLUMN is_admin TINYINT(1) DEFAULT 0 AFTER password_hash;

-- Optional: To make an existing user an admin, run:
-- UPDATE users SET is_admin = 1 WHERE email = 'admin@example.com';
