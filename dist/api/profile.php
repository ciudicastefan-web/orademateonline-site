<?php
/** Actualizează datele părintelui (numele afișat). Schimbarea emailului vine separat, cu re-verificare. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();

$u = current_user();
if (!$u) {
    redirect('/autentificare');
}

$name = trim($_POST['full_name'] ?? '');
if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
    redirect('/cont/?err=nume');
}

db()->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$name, $u['id']]);

redirect('/cont/?ok=profil');
