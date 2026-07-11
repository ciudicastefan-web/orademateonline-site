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
$newChildName = trim($_POST['new_child_name'] ?? '');
$newChildSchool = trim($_POST['new_child_school'] ?? '');
// înscrierea se poate face și din calendarul paginii Cursuri — revii acolo
$back = ($_POST['back'] ?? '') === '/cursuri' ? '/cursuri' : '/cont/';
$pdo = db();

// ora se încarcă prima: dacă profilul elevului se creează pe loc, clasa lui e clasa orei
$st = $pdo->prepare('SELECT * FROM class_sessions WHERE id = ?');
$st->execute([$sessionId]);
$sess = $st->fetch();
if (!$sess || $sess['status'] !== 'active' || strtotime((string) $sess['starts_at']) < time()) {
    redirect($back . '?err=programare');
}

$sessGrade = (int) $sess['grade'];
$sessGradeMax = $sess['grade_max'] !== null ? (int) $sess['grade_max'] : null;

if ($childId > 0) {
    $st = $pdo->prepare('SELECT * FROM children WHERE id = ? AND user_id = ?');
    $st->execute([$childId, $u['id']]);
    $child = $st->fetch();
    if (!$child) {
        redirect($back . '?err=programare');
    }
} elseif ($newChildName !== '') {
    // înscriere direct din calendar, fără elev potrivit în cont:
    // creăm profilul (aceleași reguli ca în child-save.php), apoi programăm
    if (mb_strlen($newChildName) < 2 || mb_strlen($newChildName) > 80) {
        redirect($back . '?err=copil-nume');
    }
    if (mb_strlen($newChildSchool) > 160) {
        $newChildSchool = mb_substr($newChildSchool, 0, 160);
    }
    // la clasa exactă o preluăm de la oră; la grupele mixte o alege părintele din interval
    if ($sessGradeMax !== null && $sessGradeMax > $sessGrade) {
        $newGrade = (int) ($_POST['new_child_grade'] ?? -1);
        if (!grade_matches($newGrade, $sessGrade, $sessGradeMax)) {
            redirect($back . '?err=copil-clasa');
        }
    } else {
        $newGrade = $sessGrade;
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM children WHERE user_id = ?');
    $st->execute([$u['id']]);
    if ((int) $st->fetchColumn() >= 6) {
        redirect($back . '?err=copil-limita');
    }
    $pdo->prepare('INSERT INTO children (user_id, first_name, grade, school) VALUES (?, ?, ?, ?)')
        ->execute([$u['id'], $newChildName, $newGrade, $newChildSchool !== '' ? $newChildSchool : null]);
    $childId = (int) $pdo->lastInsertId();
    $child = ['id' => $childId, 'grade' => $newGrade];
} else {
    redirect($back . '?err=programare');
}

if (!grade_matches((int) $child['grade'], $sessGrade, $sessGradeMax)) {
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
