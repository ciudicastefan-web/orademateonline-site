<?php
/** Setarea parolei noi din linkul de resetare. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
throttle('resetdo:' . client_ip(), 10, 3600);

$uid = (int) ($_POST['uid'] ?? 0);
$token = $_POST['t'] ?? '';
$pass = $_POST['password'] ?? '';
$pass2 = $_POST['password2'] ?? '';

$back = '/resetare?uid=' . $uid . '&t=' . urlencode($token);

if ($uid <= 0 || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    redirect('/autentificare?err=reset-token');
}
if (strlen($pass) < 10) {
    redirect($back . '&err=parola-scurta');
}
if ($pass !== $pass2) {
    redirect($back . '&err=parole');
}

$st = db()->prepare('SELECT reset_token_hash, reset_expires_at FROM users WHERE id = ?');
$st->execute([$uid]);
$u = $st->fetch();

if (
    !$u
    || $u['reset_token_hash'] === null
    || strtotime((string) $u['reset_expires_at']) < time()
    || !hash_equals($u['reset_token_hash'], hash('sha256', $token))
) {
    redirect('/autentificare?err=reset-token');
}

db()->prepare('UPDATE users SET pass_hash = ?, reset_token_hash = NULL, reset_expires_at = NULL,
                                failed_logins = 0, locked_until = NULL WHERE id = ?')
    ->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);

redirect('/autentificare?ok=parola-schimbata');
