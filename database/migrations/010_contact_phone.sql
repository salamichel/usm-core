-- Add phone column to contacts table
ALTER TABLE contacts
  ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL DEFAULT NULL AFTER email;
