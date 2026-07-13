<?php
/** Ștergerea contului de către utilizator (GDPR: dreptul de a fi uitat).
 *  Confirmare prin parolă. Copiii, programările și achizițiile se șterg în cascadă. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();

$u = current_user();
if (!$u) {
    redirect('/autentificare');
}

if (!password_verify($_POST['password'] ?? '', $u['pass_hash'])) {
    redirect('/cont/?err=stergere-parola');
}

db()->prepare('DELETE FROM users WHERE id = ?')->execute([$u['id']]);

// notificarea NU păstrează date personale în afara emailului — e doar semnal
// pentru admini (contul + datele lui tocmai au fost șterse definitiv din DB)
notify_admins(
    'Cont șters — orademateonline.ro',
    "Utilizatorul {$u['full_name']} ({$u['email']}) și-a șters contul (împreună cu elevii, programările și achizițiile lui)."
);

session_boot();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

redirect('/?cont=sters');
