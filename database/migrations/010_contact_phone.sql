-- Add phone column and make email optional in contacts table
ALTER TABLE contacts
  MODIFY COLUMN email VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL DEFAULT NULL AFTER email;
