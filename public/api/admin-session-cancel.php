<?php
/** Admin: anulează o oră și anunță automat părinții copiilor înscriși. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
require_admin();

$id = (int) ($_POST['session_id'] ?? 0);
$st = db()->prepare('SELECT * FROM class_sessions WHERE id = ? AND status = "active"');
$st->execute([$id]);
$sess = $st->fetch();
if (!$sess) {
    redirect('/admin/?err=ora-negasita');
}

$body = "Ne pare rău — ora de mai jos a fost anulată:\n\n"
      . "• {$sess['title']}\n"
      . '• Programată pentru: ' . date('d.m.Y, H:i', strtotime((string) $sess['starts_at'])) . "\n\n"
      . 'Poți înscrie copilul la altă oră disponibilă, din contul tău: ' . BASE_URL . '/cont/';
$sent = notify_session_parents($id, 'Oră anulată — ' . $sess['title'], $body);

db()->prepare('UPDATE class_sessions SET status = "cancelled" WHERE id = ?')->execute([$id]);

redirect('/admin/?ok=ora-anulata&n=' . $sent);
