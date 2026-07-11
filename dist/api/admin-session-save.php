<?php
/** Admin: creează o oră (sesiune live) — titlu, clasă, dată/oră, durată, locuri, link. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
require_admin();

$title = trim($_POST['title'] ?? '');
[$grade, $gradeMax] = parse_grade_field(trim($_POST['grade'] ?? ''));
$date = trim($_POST['date'] ?? '');
$time = trim($_POST['time'] ?? '');
$duration = max(15, min(240, (int) ($_POST['duration'] ?? 60)));
$capacity = max(1, min(30, (int) ($_POST['capacity'] ?? 8)));
$meetLink = trim($_POST['meet_link'] ?? '');
$priceLei = (float) str_replace(',', '.', (string) ($_POST['price_lei'] ?? '0'));
$priceCents = max(0, (int) round($priceLei * 100));

$startsAt = strtotime($date . ' ' . $time);

if (mb_strlen($title) < 3 || $grade < 0 || $startsAt === false || $startsAt < time()) {
    redirect('/admin/?err=ora-invalida');
}
if ($meetLink !== '' && !filter_var($meetLink, FILTER_VALIDATE_URL)) {
    redirect('/admin/?err=ora-link');
}

db()->prepare('INSERT INTO class_sessions (title, grade, grade_max, starts_at, duration_min, capacity, meet_link, price_cents)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([$title, $grade, $gradeMax, date('Y-m-d H:i:s', $startsAt), $duration, $capacity, $meetLink !== '' ? $meetLink : null, $priceCents]);

redirect('/admin/?ok=ora-creata');
