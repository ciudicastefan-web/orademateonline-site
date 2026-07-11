<?php
/** Șterge un profil de copil — doar dacă aparține contului logat. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();

$u = current_user();
if (!$u) {
    redirect('/autentificare');
}

$id = (int) ($_POST['child_id'] ?? 0);
db()->prepare('DELETE FROM children WHERE id = ? AND user_id = ?')->execute([$id, $u['id']]);

redirect('/cont/?ok=copil-sters');
