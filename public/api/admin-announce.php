<?php
/** Admin: trimite un anunț pe email tuturor părinților cu copii înscriși la o oră. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
require_admin();

$id = (int) ($_POST['session_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

$st = db()->prepare('SELECT * FROM class_sessions WHERE id = ?');
$st->execute([$id]);
$sess = $st->fetch();
if (!$sess) {
    redirect('/admin/?err=ora-negasita');
}
if (mb_strlen($message) < 5 || mb_strlen($message) > 3000) {
    redirect('/admin/ora.php?id=' . $id . '&err=anunt-gol');
}

$body = 'Anunț legat de ora „' . $sess['title'] . '” ('
      . date('d.m.Y, H:i', strtotime((string) $sess['starts_at'])) . "):\n\n"
      . $message;
$sent = notify_session_parents($id, 'Anunț — ' . $sess['title'], $body);

redirect('/admin/ora.php?id=' . $id . '&ok=anunt-trimis&n=' . $sent);
