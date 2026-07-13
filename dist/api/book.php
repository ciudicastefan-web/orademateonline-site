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
    $child = ['id' => $childId, 'first_name' => $newChildName, 'grade' => $newGrade];
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

// orele gratuite sunt „achitate” din start; la cele cu preț, linkul se
// deblochează după confirmarea plății (azi manual din admin, mâine prin Stripe)
$price = (int) ($sess['price_cents'] ?? 0);
$paidAt = $price > 0 ? null : date('Y-m-d H:i:s');

if ($existing) {
    // reactivare: dacă plata fusese deja făcută, o păstrăm
    $pdo->prepare('UPDATE bookings SET status = "booked", user_id = ?, paid_at = COALESCE(paid_at, ?) WHERE id = ?')
        ->execute([$u['id'], $paidAt, $existing['id']]);
} else {
    $pdo->prepare('INSERT INTO bookings (session_id, child_id, user_id, paid_at) VALUES (?, ?, ?, ?)')
        ->execute([$sessionId, $childId, $u['id'], $paidAt]);
}

$when = date('d.m.Y, H:i', strtotime((string) $sess['starts_at']));
$childName = (string) ($child['first_name'] ?? 'elevul');

if ($price > 0) {
    send_app_mail(
        (string) $u['email'],
        'Loc rezervat — ' . $sess['title'],
        "Am rezervat locul pentru ora „{$sess['title']}” din {$when}.\n\n"
        . 'Cost: ' . price_label($price) . " / elev.\n"
        . "Te contactăm în scurt timp cu detaliile de plată; după confirmare, "
        . "linkul de conectare apare în contul tău.\n\n" . BASE_URL . '/cont/'
    );
    notify_admins(
        'Rezervare de încasat — ' . $sess['title'],
        "{$u['full_name']} ({$u['email']}) l-a înscris pe {$childName} la „{$sess['title']}” din {$when} — "
        . price_label($price) . ".\n\nDupă ce încasezi banii, confirmă plata de aici:\n"
        . BASE_URL . '/admin/ora.php?id=' . $sessionId
    );
    redirect($back . '?ok=programat-plata');
}

notify_admins(
    'Înscriere nouă — ' . $sess['title'],
    "{$u['full_name']} ({$u['email']}) l-a înscris pe {$childName} la „{$sess['title']}” din {$when} (oră gratuită).\n\n"
    . 'Pagina orei: ' . BASE_URL . '/admin/ora.php?id=' . $sessionId
);
redirect($back . '?ok=programat');
