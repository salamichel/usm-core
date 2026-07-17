-- Migration: Member Email Preferences
-- Description: Table locale pour stocker les préférences d'abonnements aux emails par joueur et saison.

CREATE TABLE IF NOT EXISTS member_email_preferences (
  id_joueur INT NOT NULL,
  saison_id INT NOT NULL,
  pref_key VARCHAR(100) NOT NULL,
  is_subscribed TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_joueur, saison_id, pref_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
