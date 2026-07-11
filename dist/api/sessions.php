<?php
/** Calendarul orelor (JSON): sesiunile active din următoarele 21 de zile,
 *  cu locurile ocupate/libere și starea de înscriere a elevilor utilizatorului.
 *  Doar pentru utilizatori logați — alimentează calendarul din pagina Cursuri. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$u = current_user();
if (!$u || $u['blocked_at'] !== null) {
    http_response_code(401);
    echo json_encode(['auth' => false]);
    exit;
}

$pdo = db();

$st = $pdo->prepare('SELECT id, first_name, grade FROM children WHERE user_id = ? ORDER BY id');
$st->execute([$u['id']]);
$children = array_map(static fn ($c) => [
    'id' => (int) $c['id'],
    'name' => $c['first_name'],
    'grade' => (int) $c['grade'],
], $st->fetchAll());

$st = $pdo->prepare(
    'SELECT s.id, s.title, s.grade, s.grade_max, s.starts_at, s.duration_min, s.capacity, s.meet_link, s.price_cents,
       (SELECT COUNT(*) FROM bookings b WHERE b.session_id = s.id AND b.status = "booked") AS booked
     FROM class_sessions s
     WHERE s.status = "active" AND s.starts_at >= NOW()
       AND s.starts_at < DATE_ADD(NOW(), INTERVAL 21 DAY)
     ORDER BY s.starts_at'
);
$st->execute();
$sessions = $st->fetchAll();

// pentru fiecare sesiune: care dintre elevii mei sunt deja înscriși
$myBookings = [];
if ($children) {
    $st = $pdo->prepare(
        'SELECT b.session_id, b.child_id, b.paid_at FROM bookings b
         WHERE b.user_id = ? AND b.status = "booked"'
    );
    $st->execute([$u['id']]);
    foreach ($st->fetchAll() as $b) {
        $myBookings[(int) $b['session_id']][] = ['child' => (int) $b['child_id'], 'paid' => $b['paid_at'] !== null];
    }
}

$out = [];
foreach ($sessions as $s) {
    $id = (int) $s['id'];
    $mine = $myBookings[$id] ?? [];
    $enrolled = array_values(array_map(static fn ($m) => $m['child'], $mine));
    $unpaid = array_values(array_map(
        static fn ($m) => $m['child'],
        array_filter($mine, static fn ($m) => !$m['paid'])
    ));
    $hasPaid = count($unpaid) < count($mine);
    $out[] = [
        'id' => $id,
        'title' => $s['title'],
        'grade' => (int) $s['grade'],
        'grade_max' => $s['grade_max'] !== null ? (int) $s['grade_max'] : null,
        'starts_at' => $s['starts_at'],
        'duration' => (int) $s['duration_min'],
        'capacity' => (int) $s['capacity'],
        'booked' => (int) $s['booked'],
        'free' => max(0, (int) $s['capacity'] - (int) $s['booked']),
        'price_cents' => (int) ($s['price_cents'] ?? 0),
        'enrolled_children' => $enrolled,
        'unpaid_children' => $unpaid,
        // linkul orei se dă doar celor înscriși cu plata confirmată (gratuitele sunt marcate plătite din start)
        'meet_link' => $hasPaid ? $s['meet_link'] : null,
    ];
}

echo json_encode(['auth' => true, 'children' => $children, 'sessions' => $out], JSON_UNESCAPED_UNICODE);
