-- Migration: Add is_archived column to packages table
-- Run this in phpMyAdmin or MySQL command line

USE tour_travels;

-- Check if column exists first (optional - will show error if exists, but won't break)
ALTER TABLE packages 
ADD COLUMN is_archived BOOLEAN DEFAULT FALSE;

-- Add index for better query performance
ALTER TABLE packages 
ADD INDEX idx_archived (is_archived);
