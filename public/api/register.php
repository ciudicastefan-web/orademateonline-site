<?php
/** Înregistrare cont de părinte + trimitere email de activare. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
throttle('reg:' . client_ip(), 10, 3600);

// honeypot: câmp invizibil pe care doar boții îl completează
if (($_POST['website'] ?? '') !== '') {
    redirect('/autentificare?ok=confirmare');
}

$name  = trim($_POST['full_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$pass  = $_POST['password'] ?? '';
$pass2 = $_POST['password2'] ?? '';
$gdpr  = ($_POST['gdpr'] ?? '') === 'on';
$optin = ($_POST['marketing'] ?? '') === 'on' ? 1 : 0;

if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
    redirect('/inregistrare?err=nume');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('/inregistrare?err=email');
}
if (strlen($pass) < 10) {
    redirect('/inregistrare?err=parola-scurta');
}
if ($pass !== $pass2) {
    redirect('/inregistrare?err=parole');
}
if (!$gdpr) {
    redirect('/inregistrare?err=gdpr');
}

$pdo = db();
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);

$st = $pdo->prepare('SELECT id, email_verified_at FROM users WHERE email = ?');
$st->execute([$email]);
$existing = $st->fetch();

if ($existing && $existing['email_verified_at'] !== null) {
    redirect('/autentificare?err=exista');
}

if ($existing) {
    // cont neactivat: reîmprospătăm tokenul și retrimitem emailul
    $pdo->prepare('UPDATE users SET verify_token_hash = ?, verify_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?')
        ->execute([$tokenHash, $existing['id']]);
    $uid = (int) $existing['id'];
} else {
    $pdo->prepare('INSERT INTO users (email, pass_hash, full_name, marketing_optin, verify_token_hash, verify_expires_at)
                   VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))')
        ->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $name, $optin, $tokenHash]);
    $uid = (int) $pdo->lastInsertId();
}

$next = sanitize_next($_POST['next'] ?? null);
$link = BASE_URL . '/api/verify.php?uid=' . $uid . '&t=' . $token
      . ($next !== '/cont/' ? '&next=' . rawurlencode($next) : '');
$body = "Salut, {$name}!\n\n"
      . "Mulțumim că ți-ai făcut cont la Ora de Mate Online.\n"
      . "Activează-l printr-un click pe linkul de mai jos (valabil 24 de ore):\n\n"
      . $link . "\n\n"
      . "Dacă nu tu ai creat acest cont, ignoră emailul — contul neactivat se șterge automat.\n\n"
      . "— Ora de Mate Online\norademateonline.ro";

send_app_mail($email, 'Activează-ți contul — Ora de Mate Online', $body);

redirect('/autentificare?ok=confirmare');
