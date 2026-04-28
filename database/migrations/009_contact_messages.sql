CREATE TABLE IF NOT EXISTS contact_messages (
  id         INT          NOT NULL AUTO_INCREMENT,
  name       VARCHAR(255) NOT NULL,
  email      VARCHAR(255) NOT NULL,
  subject    VARCHAR(255) NOT NULL,
  message    TEXT         NOT NULL,
  phone      VARCHAR(20),
  read_at    TIMESTAMP,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email (email),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
