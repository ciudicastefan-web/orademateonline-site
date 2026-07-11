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

    // Sesiunile trăiesc 30 de zile, într-un folder PRIVAT al contului (în afara
    // public_html și a folderului comun al serverului, unde GC-ul altora le-ar șterge).
    $lifetime = 60 * 60 * 24 * 30;
    $dir = dirname(__DIR__, 2) . '/php_sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
    }
    ini_set('session.gc_maxlifetime', (string) $lifetime);

    session_name('omo_sess');
    session_set_cookie_params([
        'lifetime' => $lifetime,
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

/** Validează un URL de revenire („next”): doar căi locale, fără://, altfel /cont/. */
function sanitize_next(?string $next): string
{
    $next = (string) $next;
    if ($next !== '' && $next[0] === '/' && !str_starts_with($next, '//') && !str_contains($next, '://')) {
        return $next;
    }
    return '/cont/';
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
    require_once __DIR__ . '/_mail.php';
    return smtp_send($to, $subject, $body);
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

/** Adminii se definesc prin ADMIN_EMAILS în app_config.php (listă separată prin virgulă). */
function is_admin(array $u): bool
{
    if (!defined('ADMIN_EMAILS')) {
        return false;
    }
    $admins = array_map('trim', explode(',', strtolower(ADMIN_EMAILS)));
    return in_array(strtolower($u['email']), $admins, true);
}

function require_admin(): array
{
    $u = current_user();
    if (!$u || !is_admin($u)) {
        http_response_code(404);
        exit('Not found');
    }
    return $u;
}

/** Trimite un email tuturor părinților cu copii înscriși (activ) la o oră.
 *  Returnează numărul de destinatari. */
function notify_session_parents(int $sessionId, string $subject, string $body): int
{
    $st = db()->prepare(
        'SELECT DISTINCT u.email, u.full_name FROM bookings b
         JOIN users u ON u.id = b.user_id
         WHERE b.session_id = ? AND b.status = "booked" AND u.blocked_at IS NULL'
    );
    $st->execute([$sessionId]);
    $sent = 0;
    foreach ($st->fetchAll() as $r) {
        $greeting = 'Salut, ' . $r['full_name'] . "!\n\n";
        if (send_app_mail($r['email'], $subject, $greeting . $body . "\n\n— Ora de Mate Online\n(email trimis automat — nu răspunde la el)")) {
            $sent++;
        }
    }
    return $sent;
}
