-- Contacts table for Brevo integration
CREATE TABLE IF NOT EXISTS contacts (
  id          INT          NOT NULL AUTO_INCREMENT,
  name        VARCHAR(255) NOT NULL,
  email       VARCHAR(255) NOT NULL,
  subject     VARCHAR(255) NOT NULL,
  message     LONGTEXT     NOT NULL,
  status      ENUM('new', 'replied', 'archived') NOT NULL DEFAULT 'new',
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contacts_status (status),
  KEY idx_contacts_email (email),
  KEY idx_contacts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email history for tracking responses
CREATE TABLE IF NOT EXISTS contact_replies (
  id          INT          NOT NULL AUTO_INCREMENT,
  contact_id  INT          NOT NULL,
  from_email  VARCHAR(255) NOT NULL,
  reply_text  LONGTEXT     NOT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reply_contact (contact_id),
  CONSTRAINT fk_reply_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
