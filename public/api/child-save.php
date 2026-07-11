<?php
/** Adaugă profilul unui copil la contul părintelui logat (date minime: prenume + clasă, școală opțional). */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();

$u = current_user();
if (!$u) {
    redirect('/autentificare');
}

$firstName = trim($_POST['first_name'] ?? '');
$grade = (int) ($_POST['grade'] ?? -1);
$school = trim($_POST['school'] ?? '');

if (mb_strlen($firstName) < 2 || mb_strlen($firstName) > 80) {
    redirect('/cont/?err=copil-nume');
}
if ($grade < 0 || $grade > 12) {
    redirect('/cont/?err=copil-clasa');
}
if (mb_strlen($school) > 160) {
    $school = mb_substr($school, 0, 160);
}

// maximum 6 profiluri per cont — limită de bun-simț
$st = db()->prepare('SELECT COUNT(*) AS n FROM children WHERE user_id = ?');
$st->execute([$u['id']]);
if ((int) $st->fetch()['n'] >= 6) {
    redirect('/cont/?err=copil-limita');
}

db()->prepare('INSERT INTO children (user_id, first_name, grade, school) VALUES (?, ?, ?, ?)')
    ->execute([$u['id'], $firstName, $grade, $school !== '' ? $school : null]);

redirect('/cont/?ok=copil');
