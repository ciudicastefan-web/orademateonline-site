<?php
/**
 * Biblioteca comună a aplicației (conturi). Nu se accesează direct —
 * fiecare endpoint definește APP_ENTRY înainte de include.
 * Configul cu parola DB trăiește în /home/olsibrej/app_config.php (în afara git + web).
 */
declare(strict_types=1);

if (!defined('APP_ENTRY')) {
    http_response_code(404);
    exit;
}

require '/home/olsibrej/app_config.php';

const BASE_URL = 'https://orademateonline.ro';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('omo_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Formularele noastre vin doar de pe domeniul nostru. */
function require_same_origin(): void
{
    $src = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    if ($src !== '' && strpos($src, BASE_URL) !== 0) {
        http_response_code(403);
        exit('Cerere refuzată.');
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Metodă invalidă.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/** Limitare de rată pe o cheie (ex. IP): max $max acțiuni pe fereastra $windowSec. */
function throttle(string $key, int $max, int $windowSec): void
{
    $pdo = db();
    $st = $pdo->prepare('SELECT cnt, win_start FROM throttle WHERE k = ?');
    $st->execute([$key]);
    $row = $st->fetch();

    if (!$row || strtotime($row['win_start']) < time() - $windowSec) {
        $pdo->prepare('REPLACE INTO throttle (k, cnt, win_start) VALUES (?, 1, NOW())')->execute([$key]);
        return;
    }
    if ((int) $row['cnt'] >= $max) {
        http_response_code(429);
        exit('Prea multe încercări. Așteaptă câteva minute și încearcă din nou.');
    }
    $pdo->prepare('UPDATE throttle SET cnt = cnt + 1 WHERE k = ?')->execute([$key]);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function send_app_mail(string $to, string $subject, string $body): bool
{
    $headers = implode("\r\n", [
        'From: Ora de Mate Online <no-reply@orademateonline.ro>',
        'Reply-To: no-reply@orademateonline.ro',
        'Content-Type: text/plain; charset=UTF-8',
    ]);
    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

/** Utilizatorul logat sau null. */
function current_user(): ?array
{
    session_boot();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    return $u ?: null;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
