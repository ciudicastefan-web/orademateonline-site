<?php
/**
 * Creează tabelele aplicației (idempotent: CREATE TABLE IF NOT EXISTS — rularea
 * repetată nu strică nimic, de aceea nu are nevoie de token). Raportează starea.
 */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

header('Content-Type: text/plain; charset=UTF-8');

$ddl = [
    "CREATE TABLE IF NOT EXISTS users (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS children (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL,
      first_name VARCHAR(80) NOT NULL,
      grade TINYINT UNSIGNED NOT NULL,
      school VARCHAR(160) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_children_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS throttle (
      k VARCHAR(120) PRIMARY KEY,
      cnt INT UNSIGNED NOT NULL DEFAULT 1,
      win_start DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // v3: produse (PDF-uri, cursuri) și achiziții
    "CREATE TABLE IF NOT EXISTS products (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      type VARCHAR(20) NOT NULL DEFAULT 'pdf',
      title VARCHAR(160) NOT NULL,
      description TEXT NULL,
      file_name VARCHAR(200) NULL,
      price_cents INT UNSIGNED NOT NULL DEFAULT 0,
      active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS purchases (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL,
      product_id INT UNSIGNED NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'paid',
      download_count INT UNSIGNED NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_purch_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      CONSTRAINT fk_purch_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // v3: orele (sesiunile live) și programările copiilor la ele
    "CREATE TABLE IF NOT EXISTS class_sessions (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(160) NOT NULL,
      grade TINYINT UNSIGNED NOT NULL,
      starts_at DATETIME NOT NULL,
      duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 60,
      capacity TINYINT UNSIGNED NOT NULL DEFAULT 8,
      meet_link VARCHAR(300) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS bookings (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      session_id INT UNSIGNED NOT NULL,
      child_id INT UNSIGNED NOT NULL,
      user_id INT UNSIGNED NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'booked',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_session_child (session_id, child_id),
      CONSTRAINT fk_book_session FOREIGN KEY (session_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
      CONSTRAINT fk_book_child FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
      CONSTRAINT fk_book_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

try {
    $pdo = db();
    foreach ($ddl as $sql) {
        $pdo->query($sql);
    }

    // v2: coloana de suspendare a conturilor (adăugată idempotent)
    $col = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
                          AND COLUMN_NAME = 'blocked_at'")->fetchColumn();
    if ((int) $col === 0) {
        $pdo->query('ALTER TABLE users ADD COLUMN blocked_at DATETIME NULL');
    }

    // v4: statusul orelor (active/anulate) + prezența la ore
    $col = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_sessions'
                          AND COLUMN_NAME = 'status'")->fetchColumn();
    if ((int) $col === 0) {
        $pdo->query("ALTER TABLE class_sessions ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
    }
    $col = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings'
                          AND COLUMN_NAME = 'attended'")->fetchColumn();
    if ((int) $col === 0) {
        $pdo->query('ALTER TABLE bookings ADD COLUMN attended TINYINT(1) NULL');
    }

    // folderul privat pentru fișierele materialelor (în afara public_html)
    $mat = dirname(__DIR__, 2) . '/materiale';
    if (!is_dir($mat)) {
        @mkdir($mat, 0700, true);
    }

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "MIGRARE OK (v4). Tabele existente: " . implode(', ', $tables) . "\n";
} catch (Throwable $t) {
    http_response_code(500);
    error_log('[migrate] ' . $t->getMessage());
    echo "EROARE MIGRARE: tip " . get_class($t) . " (detaliile sunt în logul serverului)\n";
}
