<?php
/** Admin: pagina de gestiune a unei ore — editare, anulare, anunț către înscriși,
 *  prezență. Acces doar pentru ADMIN_EMAILS (altfel 404). */
declare(strict_types=1);
define('APP_ENTRY', 1);
require dirname(__DIR__) . '/api/_lib.php';

$admin = require_admin();
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM class_sessions WHERE id = ?');
$st->execute([$id]);
$sess = $st->fetch();
if (!$sess) {
    redirect('/admin/?err=ora-negasita');
}

$st = $pdo->prepare(
    'SELECT b.id AS booking_id, b.status, b.attended, c.first_name, c.grade, u.full_name, u.email
     FROM bookings b
     JOIN children c ON c.id = b.child_id
     JOIN users u ON u.id = b.user_id
     WHERE b.session_id = ? ORDER BY b.created_at'
);
$st->execute([$id]);
$allBookings = $st->fetchAll();
$booked = array_values(array_filter($allBookings, fn ($b) => $b['status'] === 'booked'));

$grades = ['Pregătitoare','Clasa I','Clasa a II-a','Clasa a III-a','Clasa a IV-a','Clasa a V-a',
           'Clasa a VI-a','Clasa a VII-a','Clasa a VIII-a','Clasa a IX-a','Clasa a X-a','Clasa a XI-a','Clasa a XII-a'];
$startsTs = strtotime((string) $sess['starts_at']);
$isPast = $startsTs < time();
$isCancelled = $sess['status'] !== 'active';

$map = [
    'ok=ora-editata' => 'Ora a fost actualizată.',
    'ok=anunt-trimis' => 'Anunțul a plecat pe email.',
    'ok=prezenta-salvata' => 'Prezența a fost salvată — părinții o văd în istoricul din cont.',
    'err=ora-invalida' => 'Verifică titlul, clasa și data orei.',
    'err=ora-link' => 'Linkul de întâlnire nu pare un URL valid.',
    'err=anunt-gol' => 'Scrie un mesaj de măcar câteva cuvinte.',
    'err=prezenta-goala' => 'Nu există înscriși activi la această oră.',
];
$flash = null; $isErr = false;
$qs = $_SERVER['QUERY_STRING'] ?? '';
foreach ($map as $k => $m) {
    if (str_contains($qs, $k)) {
        $flash = $m;
        if (preg_match('/[?&]n=(\d+)/', $qs, $mm) && str_starts_with($k, 'ok=') && $k !== 'ok=prezenta-salvata') {
            $flash .= ' (' . $mm[1] . ' emailuri trimise)';
        }
        $isErr = str_starts_with($k, 'err');
        break;
    }
}
?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Gestiune oră — Admin</title>
  <meta name="robots" content="noindex, nofollow" />
  <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
  <style>
    :root{--paper:#fbf7ef;--grid:rgba(43,74,139,.09);--ink:#1d2e52;--ink-soft:#51618a;--pen:#e4572e;--card:#fffdf8;--cell:28px}
    *{margin:0;padding:0;box-sizing:border-box}
    body{min-height:100svh;display:flex;flex-direction:column;align-items:center;font-family:system-ui,sans-serif;color:var(--ink);
      background:repeating-linear-gradient(to right,transparent,transparent calc(var(--cell) - 1px),var(--grid) calc(var(--cell) - 1px),var(--grid) var(--cell)),
      repeating-linear-gradient(to bottom,transparent,transparent calc(var(--cell) - 1px),var(--grid) calc(var(--cell) - 1px),var(--grid) var(--cell)),var(--paper)}
    header{width:min(980px,96vw);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:20px 16px 0}
    .brand{display:inline-flex;align-items:center;gap:10px;font-family:Georgia,serif;font-weight:700;color:var(--ink);text-decoration:none}
    .brand-mark{display:inline-grid;place-items:center;width:32px;height:32px;border-radius:8px;background:var(--pen);color:var(--paper)}
    main{width:min(980px,96vw);padding:24px 16px 44px;flex:1}
    h1{font-family:Georgia,serif;font-size:1.5rem}
    .sub{color:var(--ink-soft);margin-top:4px;font-size:.92rem}
    .flash{margin-top:14px;padding:12px 16px;border-radius:10px;border:1.5px dashed rgba(43,74,139,.4);background:rgba(43,74,139,.06)}
    .flash.is-err{border-color:rgba(228,87,46,.55);background:rgba(228,87,46,.07)}
    .panel{margin-top:18px;background:var(--card);border:1px solid rgba(43,74,139,.14);border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(29,46,82,.05)}
    .panel h2{font-family:Georgia,serif;font-size:1.12rem;margin-bottom:12px}
    label{display:grid;gap:5px;font-weight:700;font-size:.9rem}
    input,select,textarea{font:inherit;font-weight:400;padding:9px 12px;border-radius:9px;border:1.5px solid rgba(43,74,139,.25);background:#fff;color:var(--ink);width:100%}
    textarea{min-height:110px;resize:vertical}
    button{font:inherit;font-weight:700;padding:9px 18px;border:none;border-radius:999px;background:var(--ink);color:var(--paper);cursor:pointer}
    button:hover{background:var(--pen)}
    button.warn{background:transparent;color:var(--pen);border:1.5px solid rgba(228,87,46,.5)}
    button.warn:hover{background:rgba(228,87,46,.08)}
    .muted{color:var(--ink-soft);font-size:.88rem;font-weight:400}
    .grid-form{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));align-items:end}
    .full{grid-column:1/-1}
    table{width:100%;border-collapse:collapse;font-size:.92rem;margin-top:6px}
    th,td{padding:8px 10px;text-align:left;border-bottom:1px solid rgba(43,74,139,.1)}
    th{color:var(--ink-soft);font-size:.76rem;text-transform:uppercase;letter-spacing:.05em}
    .b{font-size:.78rem;font-weight:700;padding:3px 10px;border-radius:99px;white-space:nowrap}
    .b-ok{background:rgba(46,160,67,.12);color:#1a7f37}
    .b-off{background:rgba(228,87,46,.12);color:var(--pen)}
    input[type=checkbox]{width:20px;height:20px;accent-color:var(--pen)}
    footer{padding:20px;font-size:.85rem;color:var(--ink-soft)}
  </style>
</head>
<body>
  <header>
    <a class="brand" href="/admin/"><span class="brand-mark">⚙</span> ← Înapoi la panou</a>
  </header>

  <main>
    <h1><?= e($sess['title']) ?>
      <?php if ($isCancelled): ?><span class="b b-off">ANULATĂ</span>
      <?php elseif ($isPast): ?><span class="b b-off">trecută</span>
      <?php else: ?><span class="b b-ok">activă</span><?php endif; ?>
    </h1>
    <p class="sub">
      <?= e(grade_label((int) $sess['grade'], $sess['grade_max'] !== null ? (int) $sess['grade_max'] : null)) ?> ·
      <?= e(date('d.m.Y, H:i', $startsTs)) ?> · <?= (int) $sess['duration_min'] ?> min ·
      înscriși: <?= count($booked) ?>/<?= (int) $sess['capacity'] ?>
    </p>

    <?php if ($flash !== null): ?>
      <div class="flash<?= $isErr ? ' is-err' : '' ?>"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if (!$isCancelled): ?>
    <div class="panel">
      <h2>✏️ Editează ora <span class="muted">(înscrișii primesc automat email cu noile detalii)</span></h2>
      <form method="post" action="/api/admin-session-update.php" class="grid-form">
        <input type="hidden" name="session_id" value="<?= (int) $sess['id'] ?>" />
        <label class="full">Titlu <input type="text" name="title" required minlength="3" maxlength="160" value="<?= e($sess['title']) ?>" /></label>
        <label>Clasa
          <?php
            // valoarea curentă: clasa exactă (număr) sau litera grupei mixte
            $isMix = $sess['grade_max'] !== null && (int) $sess['grade_max'] > (int) $sess['grade'];
            $current = $isMix ? ([0 => 'p', 5 => 'g', 9 => 'l'][(int) $sess['grade']] ?? 'p') : (string) (int) $sess['grade'];
          ?>
          <select name="grade" required>
            <optgroup label="Clasă exactă">
              <?php foreach ($grades as $i => $g): ?>
                <option value="<?= $i ?>" <?= $current === (string) $i ? 'selected' : '' ?>><?= e($g) ?></option>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="Grupă mixtă (clase apropiate)">
              <option value="p" <?= $current === 'p' ? 'selected' : '' ?>>🔀 Mix Primar — Pregătitoare–IV</option>
              <option value="g" <?= $current === 'g' ? 'selected' : '' ?>>🔀 Mix Gimnaziu — V–VIII</option>
              <option value="l" <?= $current === 'l' ? 'selected' : '' ?>>🔀 Mix Liceu — IX–XII</option>
            </optgroup>
          </select>
        </label>
        <label>Data <input type="date" name="date" required value="<?= e(date('Y-m-d', $startsTs)) ?>" /></label>
        <label>Ora <input type="time" name="time" required value="<?= e(date('H:i', $startsTs)) ?>" /></label>
        <label>Durata (min) <input type="number" name="duration" value="<?= (int) $sess['duration_min'] ?>" min="15" max="240" /></label>
        <label>Locuri <input type="number" name="capacity" value="<?= (int) $sess['capacity'] ?>" min="1" max="30" /></label>
        <label class="full">Link întâlnire <input type="url" name="meet_link" value="<?= e((string) ($sess['meet_link'] ?? '')) ?>" placeholder="https://..." /></label>
        <div><button type="submit">Salvează modificările</button></div>
      </form>
    </div>

    <div class="panel">
      <h2>📣 Anunț către înscriși <span class="muted">(<?= count($booked) ?> părinți vor primi emailul)</span></h2>
      <form method="post" action="/api/admin-announce.php" style="display:grid;gap:12px">
        <input type="hidden" name="session_id" value="<?= (int) $sess['id'] ?>" />
        <label>Mesajul
          <textarea name="message" required minlength="5" maxlength="3000"
            placeholder="Ex.: Nu uitați caietele de teme! Ora începe punctual la 17:00."></textarea>
        </label>
        <div><button type="submit">Trimite anunțul</button></div>
      </form>
    </div>
    <?php endif; ?>

    <div class="panel">
      <h2>✅ Prezența</h2>
      <?php if (!$booked): ?>
        <p class="muted">Niciun copil înscris activ la această oră.</p>
      <?php else: ?>
      <form method="post" action="/api/admin-attendance.php">
        <input type="hidden" name="session_id" value="<?= (int) $sess['id'] ?>" />
        <table>
          <thead><tr><th>Prezent</th><th>Copil</th><th>Părinte</th><th>Email</th></tr></thead>
          <tbody>
            <?php foreach ($booked as $b): ?>
            <tr>
              <td><input type="checkbox" name="present[]" value="<?= (int) $b['booking_id'] ?>"
                <?= (int) ($b['attended'] ?? 0) === 1 ? 'checked' : '' ?> /></td>
              <td><strong><?= e($b['first_name']) ?></strong></td>
              <td><?= e($b['full_name']) ?></td>
              <td class="muted"><?= e($b['email']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="margin-top:14px"><button type="submit">Salvează prezența</button></div>
      </form>
      <?php endif; ?>
    </div>

    <?php if (!$isCancelled): ?>
    <div class="panel" style="border-color:rgba(228,87,46,.4)">
      <h2 style="color:var(--pen)">Anularea orei</h2>
      <p class="muted" style="margin-bottom:12px">
        Ora dispare de la programare, iar cei <?= count($booked) ?> înscriși primesc automat
        email că ora a fost anulată și pot alege alta.
      </p>
      <form method="post" action="/api/admin-session-cancel.php"
            onsubmit="return confirm('Anulezi ora și anunți înscrișii pe email?')">
        <input type="hidden" name="session_id" value="<?= (int) $sess['id'] ?>" />
        <button type="submit" class="warn">Anulează ora</button>
      </form>
    </div>
    <?php endif; ?>
  </main>

  <footer>Panou intern · © 2026 Ora de Mate Online</footer>
</body>
</html>
