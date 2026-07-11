-- Schema bazei de date — Ora de Mate Online
-- Rulată în MySQL-ul de pe Hostico (baza: olsibrej_app).
-- Principiu GDPR: contul e al PĂRINTELUI; despre copil ținem minimul (prenume, clasă, școală opțional).

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL DEFAULT '',
  email_verified_at DATETIME NULL,
  verify_token_hash CHAR(64) NULL,
  verify_expires_at DATETIME NULL,
  reset_token_hash CHAR(64) NULL,
  reset_expires_at DATETIME NULL,
  failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  marketing_optin TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS children (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  grade TINYINT UNSIGNED NOT NULL,      -- clasa: 0 (pregătitoare) – 12
  school VARCHAR(160) NULL,             -- opțional, text liber
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_children_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelele pentru cumpărături/cursuri vin în faza Stripe (legate de users.id).
