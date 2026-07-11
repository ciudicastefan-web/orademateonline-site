<?php
/** Admin: salvează prezența la o oră — copiii bifați = prezenți, restul = absenți. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
require_admin();

$sessionId = (int) ($_POST['session_id'] ?? 0);
$present = array_map('intval', (array) ($_POST['present'] ?? []));

$st = db()->prepare('SELECT id FROM bookings WHERE session_id = ? AND status = "booked"');
$st->execute([$sessionId]);
$bookingIds = $st->fetchAll(PDO::FETCH_COLUMN);

if (!$bookingIds) {
    redirect('/admin/ora.php?id=' . $sessionId . '&err=prezenta-goala');
}

$upd = db()->prepare('UPDATE bookings SET attended = ? WHERE id = ?');
foreach ($bookingIds as $bid) {
    $upd->execute([in_array((int) $bid, $present, true) ? 1 : 0, $bid]);
}

redirect('/admin/ora.php?id=' . $sessionId . '&ok=prezenta-salvata');
