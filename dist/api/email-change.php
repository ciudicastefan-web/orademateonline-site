<?php
/** Cererea de schimbare a emailului: cere parola curentă, trimite link de
 *  confirmare pe ADRESA NOUĂ (emailul vechi rămâne activ până la confirmare). */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
throttle('emailchg:' . client_ip(), 5, 3600);

$u = current_user();
if (!$u) {
    redirect('/autentificare');
}

$newEmail = strtolower(trim($_POST['new_email'] ?? ''));
$password = $_POST['password'] ?? '';

if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    redirect('/cont/?err=email-nou');
}
if ($newEmail === strtolower($u['email'])) {
    redirect('/cont/?err=email-acelasi');
}
if (!password_verify($password, $u['pass_hash'])) {
    redirect('/cont/?err=email-parola');
}

// adresa nouă nu are voie să aparțină altui cont
$st = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
$st->execute([$newEmail, $u['id']]);
if ($st->fetch()) {
    redirect('/cont/?err=email-ocupat');
}

$token = bin2hex(random_bytes(32));
db()->prepare('UPDATE users SET new_email = ?, new_email_token_hash = ?,
                                new_email_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
               WHERE id = ?')
    ->execute([$newEmail, hash('sha256', $token), $u['id']]);

$link = BASE_URL . '/api/email-confirm.php?uid=' . (int) $u['id'] . '&t=' . $token;
send_app_mail(
    $newEmail,
    'Confirmă noua adresă de email — Ora de Mate Online',
    "Salut, {$u['full_name']}!\n\n"
    . "Ai cerut schimbarea adresei de email a contului tău către această adresă.\n"
    . "Confirmă printr-un click (linkul e valabil 24 de ore):\n\n{$link}\n\n"
    . "Dacă nu tu ai cerut schimbarea, ignoră emailul — contul rămâne pe adresa veche.\n\n"
    . "— Ora de Mate Online"
);

redirect('/cont/?ok=email-trimis');
