<?php
/** Admin: confirmă plata unei programări — părintele primește email, iar linkul
 *  orei i se deblochează în cont și în calendar. Când vom avea Stripe, webhook-ul
 *  va face exact aceeași tranziție (paid_at), deci fluxul rămâne neschimbat. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
require_admin();

$bookingId = (int) ($_POST['booking_id'] ?? 0);

$st = db()->prepare(
    'SELECT b.id, b.paid_at, b.session_id, s.title, s.starts_at, s.meet_link,
            u.email, c.first_name
     FROM bookings b
     JOIN class_sessions s ON s.id = b.session_id
     JOIN users u ON u.id = b.user_id
     JOIN children c ON c.id = b.child_id
     WHERE b.id = ? AND b.status = "booked"'
);
$st->execute([$bookingId]);
$b = $st->fetch();
if (!$b) {
    redirect('/admin/?err=negasit');
}

$back = '/admin/ora.php?id=' . (int) $b['session_id'];

if ($b['paid_at'] === null) {
    db()->prepare('UPDATE bookings SET paid_at = NOW() WHERE id = ?')->execute([$bookingId]);

    $when = date('d.m.Y, H:i', strtotime((string) $b['starts_at']));
    send_app_mail(
        (string) $b['email'],
        'Plata confirmată — ' . $b['title'],
        "Am primit plata pentru ora „{$b['title']}” din {$when}, la care e înscris {$b['first_name']}. Mulțumim!\n\n"
        . ($b['meet_link']
            ? "Linkul de conectare: {$b['meet_link']}\n\n"
            : "Linkul de conectare apare în contul tău înainte de oră.\n\n")
        . 'Îl găsești oricând în contul tău: ' . BASE_URL . '/cont/'
    );
}

redirect($back . '&ok=plata-confirmata');
