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

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "MIGRARE OK (v2). Tabele existente: " . implode(', ', $tables) . "\n";
} catch (Throwable $t) {
    http_response_code(500);
    error_log('[migrate] ' . $t->getMessage());
    echo "EROARE MIGRARE: tip " . get_class($t) . " (detaliile sunt în logul serverului)\n";
}
