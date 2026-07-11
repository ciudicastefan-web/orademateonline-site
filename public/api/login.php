<?php
/** Autentificare: parolă + protecție anti brute-force (blocare 15 min după 5 eșecuri). */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
throttle('login:' . client_ip(), 30, 900);

$email = strtolower(trim($_POST['email'] ?? ''));
$pass  = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '') {
    redirect('/autentificare?err=gresit');
}

$pdo = db();
$st = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$st->execute([$email]);
$u = $st->fetch();

// răspuns identic pentru „user inexistent” și „parolă greșită” — nu confirmăm existența emailurilor
if (!$u) {
    redirect('/autentificare?err=gresit');
}

if (array_key_exists('blocked_at', $u) && $u['blocked_at'] !== null) {
    redirect('/autentificare?err=blocat-admin');
}

if ($u['locked_until'] !== null && strtotime((string) $u['locked_until']) > time()) {
    redirect('/autentificare?err=blocat');
}

if (!password_verify($pass, $u['pass_hash'])) {
    $fails = (int) $u['failed_logins'] + 1;
    if ($fails >= 5) {
        $pdo->prepare('UPDATE users SET failed_logins = 0, locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?')
            ->execute([$u['id']]);
    } else {
        $pdo->prepare('UPDATE users SET failed_logins = ? WHERE id = ?')->execute([$fails, $u['id']]);
    }
    redirect('/autentificare?err=gresit');
}

if ($u['email_verified_at'] === null) {
    redirect('/autentificare?err=neactivat');
}

$pdo->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([$u['id']]);

session_boot();
session_regenerate_id(true);
$_SESSION['uid'] = (int) $u['id'];

redirect(sanitize_next($_POST['next'] ?? null));
