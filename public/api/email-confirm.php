<?php
/** Confirmarea noii adrese de email (linkul primit pe adresa nouă).
 *  La final, adresa veche primește o notificare de curtoazie. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

$uid = (int) ($_GET['uid'] ?? 0);
$token = $_GET['t'] ?? '';

if ($uid <= 0 || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    redirect('/cont/?err=email-token');
}

$st = db()->prepare('SELECT * FROM users WHERE id = ?');
$st->execute([$uid]);
$u = $st->fetch();

if (
    !$u
    || $u['new_email'] === null
    || $u['new_email_token_hash'] === null
    || strtotime((string) $u['new_email_expires_at']) < time()
    || !hash_equals($u['new_email_token_hash'], hash('sha256', $token))
) {
    redirect('/cont/?err=email-token');
}

$oldEmail = $u['email'];
$newEmail = $u['new_email'];

db()->prepare('UPDATE users SET email = ?, new_email = NULL, new_email_token_hash = NULL,
                                new_email_expires_at = NULL WHERE id = ?')
    ->execute([$newEmail, $uid]);

// curtoazie + securitate: anunțăm adresa veche
send_app_mail(
    $oldEmail,
    'Adresa de email a contului a fost schimbată — Ora de Mate Online',
    "Salut, {$u['full_name']}!\n\n"
    . "Adresa de email a contului tău a fost schimbată în {$newEmail}.\n"
    . "Dacă NU tu ai făcut această schimbare, scrie-ne imediat prin pagina de contact.\n\n"
    . "— Ora de Mate Online"
);

redirect('/cont/?ok=email-schimbat');
