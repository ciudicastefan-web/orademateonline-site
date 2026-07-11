<?php
/** „Am uitat parola” — trimite email cu link de resetare (token 1h). Răspuns identic
 *  indiferent dacă emailul există sau nu — nu confirmăm existența conturilor. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
throttle('reset:' . client_ip(), 5, 3600);

$email = strtolower(trim($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('/autentificare?ok=reset-trimis');
}

$st = db()->prepare('SELECT id, full_name FROM users WHERE email = ?');
$st->execute([$email]);
$u = $st->fetch();

if ($u) {
    $token = bin2hex(random_bytes(32));
    db()->prepare('UPDATE users SET reset_token_hash = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?')
        ->execute([hash('sha256', $token), $u['id']]);

    $link = BASE_URL . '/resetare?uid=' . (int) $u['id'] . '&t=' . $token;
    $body = "Salut, {$u['full_name']}!\n\n"
          . "Am primit o cerere de resetare a parolei contului tău. Dacă tu ai cerut-o,\n"
          . "setează o parolă nouă aici (linkul e valabil o oră):\n\n"
          . $link . "\n\n"
          . "Dacă nu tu ai cerut resetarea, ignoră acest email — parola rămâne neschimbată.\n\n"
          . "— Ora de Mate Online";
    send_app_mail($email, 'Resetarea parolei — Ora de Mate Online', $body);
}

redirect('/autentificare?ok=reset-trimis');
