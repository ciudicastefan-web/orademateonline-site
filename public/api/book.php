<?php
/** Înscrierea unui copil la o oră: verifică proprietatea copilului, clasa potrivită,
 *  locurile libere și dublurile. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();

$u = current_user();
if (!$u) {
    redirect('/autentificare');
}

$sessionId = (int) ($_POST['session_id'] ?? 0);
$childId = (int) ($_POST['child_id'] ?? 0);
// înscrierea se poate face și din calendarul paginii Cursuri — revii acolo
$back = ($_POST['back'] ?? '') === '/cursuri' ? '/cursuri' : '/cont/';
$pdo = db();

$st = $pdo->prepare('SELECT * FROM children WHERE id = ? AND user_id = ?');
$st->execute([$childId, $u['id']]);
$child = $st->fetch();
if (!$child) {
    redirect($back . '?err=programare');
}

$st = $pdo->prepare('SELECT * FROM class_sessions WHERE id = ?');
$st->execute([$sessionId]);
$sess = $st->fetch();
if (!$sess || strtotime((string) $sess['starts_at']) < time()) {
    redirect($back . '?err=programare');
}
if ((int) $sess['grade'] !== (int) $child['grade']) {
    redirect($back . '?err=programare-clasa');
}

// dublură? (inclusiv anulată — o reactivăm)
$st = $pdo->prepare('SELECT * FROM bookings WHERE session_id = ? AND child_id = ?');
$st->execute([$sessionId, $childId]);
$existing = $st->fetch();
if ($existing && $existing['status'] === 'booked') {
    redirect($back . '?err=programare-dubla');
}

// locuri libere?
$st = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE session_id = ? AND status = "booked"');
$st->execute([$sessionId]);
if ((int) $st->fetchColumn() >= (int) $sess['capacity']) {
    redirect($back . '?err=programare-plina');
}

if ($existing) {
    $pdo->prepare('UPDATE bookings SET status = "booked", user_id = ? WHERE id = ?')
        ->execute([$u['id'], $existing['id']]);
} else {
    $pdo->prepare('INSERT INTO bookings (session_id, child_id, user_id) VALUES (?, ?, ?)')
        ->execute([$sessionId, $childId, $u['id']]);
}

redirect($back . '?ok=programat');
