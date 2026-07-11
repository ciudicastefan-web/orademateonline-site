<?php
/** Admin: creează o oră (sesiune live) — titlu, clasă, dată/oră, durată, locuri, link. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
require_admin();

$title = trim($_POST['title'] ?? '');
$grade = (int) ($_POST['grade'] ?? -1);
$date = trim($_POST['date'] ?? '');
$time = trim($_POST['time'] ?? '');
$duration = max(15, min(240, (int) ($_POST['duration'] ?? 60)));
$capacity = max(1, min(30, (int) ($_POST['capacity'] ?? 8)));
$meetLink = trim($_POST['meet_link'] ?? '');

$startsAt = strtotime($date . ' ' . $time);

if (mb_strlen($title) < 3 || $grade < 0 || $grade > 12 || $startsAt === false || $startsAt < time()) {
    redirect('/admin/?err=ora-invalida');
}
if ($meetLink !== '' && !filter_var($meetLink, FILTER_VALIDATE_URL)) {
    redirect('/admin/?err=ora-link');
}

db()->prepare('INSERT INTO class_sessions (title, grade, starts_at, duration_min, capacity, meet_link)
               VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$title, $grade, date('Y-m-d H:i:s', $startsAt), $duration, $capacity, $meetLink !== '' ? $meetLink : null]);

redirect('/admin/?ok=ora-creata');
