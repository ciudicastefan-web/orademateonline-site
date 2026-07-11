<?php
/** Admin: editează o oră și anunță automat pe email părinții copiilor înscriși. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
require_admin();

$id = (int) ($_POST['session_id'] ?? 0);
$st = db()->prepare('SELECT * FROM class_sessions WHERE id = ?');
$st->execute([$id]);
$old = $st->fetch();
if (!$old) {
    redirect('/admin/?err=ora-negasita');
}

$title = trim($_POST['title'] ?? '');
[$grade, $gradeMax] = parse_grade_field(trim($_POST['grade'] ?? ''));
$duration = max(15, min(240, (int) ($_POST['duration'] ?? 60)));
$capacity = max(1, min(30, (int) ($_POST['capacity'] ?? 8)));
$meetLink = trim($_POST['meet_link'] ?? '');
$startsAt = strtotime(trim($_POST['date'] ?? '') . ' ' . trim($_POST['time'] ?? ''));

$back = '/admin/ora.php?id=' . $id;

if (mb_strlen($title) < 3 || $grade < 0 || $startsAt === false) {
    redirect($back . '&err=ora-invalida');
}
if ($meetLink !== '' && !filter_var($meetLink, FILTER_VALIDATE_URL)) {
    redirect($back . '&err=ora-link');
}

db()->prepare('UPDATE class_sessions SET title = ?, grade = ?, grade_max = ?, starts_at = ?, duration_min = ?, capacity = ?, meet_link = ? WHERE id = ?')
    ->execute([$title, $grade, $gradeMax, date('Y-m-d H:i:s', $startsAt), $duration, $capacity, $meetLink !== '' ? $meetLink : null, $id]);

// anunțăm părinții doar dacă s-a schimbat ceva relevant pentru ei
$relevantChange = $old['title'] !== $title
    || strtotime((string) $old['starts_at']) !== $startsAt
    || (int) $old['duration_min'] !== $duration
    || (string) ($old['meet_link'] ?? '') !== $meetLink;

$sent = 0;
if ($relevantChange) {
    $body = "Ora la care e înscris copilul tău a fost actualizată. Detaliile noi:\n\n"
          . "• Ora: {$title}\n"
          . '• Data: ' . date('d.m.Y, H:i', $startsAt) . "\n"
          . "• Durata: {$duration} minute\n"
          . ($meetLink !== '' ? "• Link de conectare: {$meetLink}\n" : '')
          . "\nDetaliile complete sunt mereu în contul tău: " . BASE_URL . '/cont/';
    $sent = notify_session_parents($id, 'Actualizare oră — ' . $title, $body);
}

redirect($back . '&ok=ora-editata&n=' . $sent);
