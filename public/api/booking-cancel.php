<?php
/** Anularea unei programări — permisă până cu 24 de ore înainte de oră
 *  (aliniat cu Termenii și Condițiile). */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();

$u = current_user();
if (!$u) {
    redirect('/autentificare');
}

$bookingId = (int) ($_POST['booking_id'] ?? 0);

$st = db()->prepare(
    'SELECT b.*, s.starts_at, s.title, s.id AS sid, c.first_name FROM bookings b
     JOIN class_sessions s ON s.id = b.session_id
     JOIN children c ON c.id = b.child_id
     WHERE b.id = ? AND b.user_id = ? AND b.status = "booked"'
);
$st->execute([$bookingId, $u['id']]);
$b = $st->fetch();

if (!$b) {
    redirect('/cont/?err=programare');
}
if (strtotime((string) $b['starts_at']) - time() < 24 * 3600) {
    redirect('/cont/?err=anulare-tarziu');
}

db()->prepare('UPDATE bookings SET status = "cancelled" WHERE id = ?')->execute([$bookingId]);

notify_admins(
    'Anulare programare — ' . $b['title'],
    "{$u['full_name']} ({$u['email']}) a anulat înscrierea lui {$b['first_name']} la „{$b['title']}” din "
    . date('d.m.Y, H:i', strtotime((string) $b['starts_at']))
    . ".\nLocul a redevenit liber.\n\n"
    . 'Pagina orei: ' . BASE_URL . '/admin/ora.php?id=' . (int) $b['sid']
);

redirect('/cont/?ok=anulat');
