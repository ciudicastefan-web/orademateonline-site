<?php
/** Activarea contului din linkul primit pe email. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

$uid = (int) ($_GET['uid'] ?? 0);
$token = $_GET['t'] ?? '';

if ($uid <= 0 || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    redirect('/autentificare?err=token');
}

$st = db()->prepare('SELECT verify_token_hash, verify_expires_at, email_verified_at FROM users WHERE id = ?');
$st->execute([$uid]);
$u = $st->fetch();

if (!$u) {
    redirect('/autentificare?err=token');
}
if ($u['email_verified_at'] !== null) {
    redirect('/autentificare?ok=activat');
}
if (
    $u['verify_token_hash'] === null
    || strtotime((string) $u['verify_expires_at']) < time()
    || !hash_equals($u['verify_token_hash'], hash('sha256', $token))
) {
    redirect('/autentificare?err=token');
}

db()->prepare('UPDATE users SET email_verified_at = NOW(), verify_token_hash = NULL, verify_expires_at = NULL WHERE id = ?')
    ->execute([$uid]);

$next = sanitize_next($_GET['next'] ?? null);
redirect('/autentificare?ok=activat' . ($next !== '/cont/' ? '&next=' . rawurlencode($next) : ''));
