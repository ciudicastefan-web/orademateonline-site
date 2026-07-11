<?php
/** Pagina „Contul meu”: date părinte, copii, materiale cumpărate (cu descărcare),
 *  programări la ore (înscriere/anulare, regula 24h) și istoric. Necesită login. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require dirname(__DIR__) . '/api/_lib.php';

$u = current_user();
if (!$u) {
    redirect('/autentificare');
}
$pdo = db();

// eticheta din antet: prenumele (primul cuvânt din numele afișat) sau,
// dacă lipsește, începutul adresei de email — niciodată emailul întreg
$who = trim((string) $u['full_name']);
$who = $who !== '' ? (string) preg_split('/\s+/', $who)[0] : (string) explode('@', (string) $u['email'])[0];
if (mb_strlen($who) > 12) {
    $who = mb_substr($who, 0, 12) . '…';
}

$st = $pdo->prepare('SELECT * FROM children WHERE user_id = ? ORDER BY id');
$st->execute([$u['id']]);
$children = $st->fetchAll();

$st = $pdo->prepare(
    'SELECT p.id, p.download_count, p.created_at, pr.title, pr.type, pr.file_name
     FROM purchases p JOIN products pr ON pr.id = p.product_id
     WHERE p.user_id = ? AND p.status = "paid" ORDER BY p.created_at DESC'
);
$st->execute([$u['id']]);
$purchases = $st->fetchAll();

// programările viitoare (cu link de întâlnire) + istoricul
$st = $pdo->prepare(
    'SELECT b.id AS booking_id, s.title, s.starts_at, s.duration_min, s.meet_link, c.first_name
     FROM bookings b
     JOIN class_sessions s ON s.id = b.session_id
     JOIN children c ON c.id = b.child_id
     WHERE b.user_id = ? AND b.status = "booked" AND s.starts_at >= NOW() AND s.status = "active"
     ORDER BY s.starts_at'
);
$st->execute([$u['id']]);
$upcoming = $st->fetchAll();

$st = $pdo->prepare(
    'SELECT s.title, s.starts_at, c.first_name, b.attended
     FROM bookings b
     JOIN class_sessions s ON s.id = b.session_id
     JOIN children c ON c.id = b.child_id
     WHERE b.user_id = ? AND b.status = "booked" AND s.starts_at < NOW() AND s.status = "active"
     ORDER BY s.starts_at DESC LIMIT 20'
);
$st->execute([$u['id']]);
$history = $st->fetchAll();

// orele disponibile pentru fiecare copil (pe clasa lui)
$availByChild = [];
foreach ($children as $c) {
    $st = $pdo->prepare(
        'SELECT s.*,
           (SELECT COUNT(*) FROM bookings b WHERE b.session_id = s.id AND b.status = "booked") AS booked,
           (SELECT COUNT(*) FROM bookings b WHERE b.session_id = s.id AND b.child_id = ? AND b.status = "booked") AS mine
         FROM class_sessions s
         WHERE s.grade = ? AND s.starts_at > NOW() AND s.status = "active"
         ORDER BY s.starts_at LIMIT 8'
    );
    $st->execute([$c['id'], $c['grade']]);
    $availByChild[$c['id']] = $st->fetchAll();
}

$grades = ['Pregătitoare','Clasa I','Clasa a II-a','Clasa a III-a','Clasa a IV-a','Clasa a V-a',
           'Clasa a VI-a','Clasa a VII-a','Clasa a VIII-a','Clasa a IX-a','Clasa a X-a','Clasa a XI-a','Clasa a XII-a'];
$months = [1=>'ian',2=>'feb',3=>'mar',4=>'apr',5=>'mai',6=>'iun',7=>'iul',8=>'aug',9=>'sep',10=>'oct',11=>'nov',12=>'dec'];
function ro_dt(string $dt, array $months): string {
    $t = strtotime($dt);
    return date('j', $t) . ' ' . $months[(int) date('n', $t)] . date(' Y, H:i', $t);
}

$map = [
    'ok=copil' => 'Profilul elevului a fost adăugat.', 'ok=copil-sters' => 'Profilul a fost șters.',
    'ok=profil' => 'Datele tale au fost actualizate.', 'ok=programat' => 'Programare făcută! Linkul orei apare mai jos, la „Programările viitoare”.',
    'ok=anulat' => 'Programarea a fost anulată.',
    'err=copil-nume' => 'Prenumele elevului trebuie să aibă între 2 și 80 de caractere.',
    'err=copil-clasa' => 'Alege clasa elevului.', 'err=copil-limita' => 'Ai atins numărul maxim de profiluri (6).',
    'err=nume' => 'Numele trebuie să aibă între 2 și 120 de caractere.',
    'err=programare' => 'Programarea nu a putut fi făcută. Încearcă din nou.',
    'err=programare-clasa' => 'Ora aleasă e pentru altă clasă decât a elevului.',
    'err=programare-dubla' => 'Elevul e deja înscris la această oră.',
    'err=programare-plina' => 'Ne pare rău, ora s-a umplut între timp.',
    'err=anulare-tarziu' => 'Anularea se poate face cel târziu cu 24 de ore înainte de oră.',
    'err=descarcare' => 'Descărcarea nu a funcționat. Scrie-ne dacă persistă.',
    'err=descarcare-limita' => 'Ai atins limita de 5 descărcări pentru acest material. Scrie-ne dacă ai nevoie de el din nou.',
    'err=stergere-parola' => 'Parola introdusă nu e corectă — contul NU a fost șters.',
    'ok=email-trimis' => 'Ți-am trimis un link de confirmare pe NOUA adresă — emailul se schimbă după click (verifică și spamul).',
    'ok=email-schimbat' => 'Adresa de email a fost schimbată cu succes.',
    'err=email-nou' => 'Noua adresă de email nu pare validă.',
    'err=email-acelasi' => 'Noua adresă e identică cu cea actuală.',
    'err=email-parola' => 'Parola introdusă nu e corectă — emailul NU a fost schimbat.',
    'err=email-ocupat' => 'Există deja un cont cu această adresă de email.',
    'err=email-token' => 'Linkul de confirmare e invalid sau expirat — cere schimbarea din nou.',
];
$flash = null; $isErr = false;
$qs = $_SERVER['QUERY_STRING'] ?? '';
foreach ($map as $k => $m) {
    if (str_contains($qs, $k)) { $flash = $m; $isErr = str_starts_with($k, 'err'); break; }
}
?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Contul meu — Ora de Mate Online</title>
  <meta name="robots" content="noindex, nofollow" />
  <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
  <style>
    :root{--paper:#fbf7ef;--grid:rgba(43,74,139,.09);--margin-line:rgba(228,87,46,.28);--ink:#1d2e52;--ink-soft:#51618a;--pen:#e4572e;--card:#fffdf8;--cell:28px}
    *{margin:0;padding:0;box-sizing:border-box}
    body{min-height:100svh;display:flex;flex-direction:column;align-items:center;font-family:'Atkinson Hyperlegible',system-ui,sans-serif;color:var(--ink);
      background:radial-gradient(1200px 600px at 50% -10%,rgba(255,255,255,.75),transparent 60%),
      repeating-linear-gradient(to right,transparent,transparent calc(var(--cell) - 1px),var(--grid) calc(var(--cell) - 1px),var(--grid) var(--cell)),
      repeating-linear-gradient(to bottom,transparent,transparent calc(var(--cell) - 1px),var(--grid) calc(var(--cell) - 1px),var(--grid) var(--cell)),var(--paper)}
    body::before{content:'';position:fixed;top:0;bottom:0;left:clamp(20px,6vw,84px);width:2px;background:var(--margin-line);pointer-events:none}
    header{position:sticky;top:0;z-index:40;width:100%;display:flex;justify-content:center;background:rgba(251,247,239,.9);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border-bottom:1px solid rgba(43,74,139,.1)}
    .header-in{width:min(1020px,94vw);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:12px 16px}
    .brand{display:inline-flex;align-items:center;gap:10px;font-family:Georgia,serif;font-weight:700;font-size:1.15rem;color:var(--ink);text-decoration:none}
    .brand em{color:var(--pen);font-style:normal}
    .brand-mark{display:inline-grid;place-items:center;width:34px;height:34px;border-radius:9px;background:var(--ink);color:var(--paper)}
    nav{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
    nav a{padding:8px 14px;border-radius:999px;font-size:.95rem;font-weight:700;color:var(--ink-soft);text-decoration:none}
    nav a:hover{color:var(--ink);background:rgba(43,74,139,.07)}
    .me{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:999px;background:rgba(43,74,139,.08);font-weight:700;font-size:.9rem;color:var(--ink);cursor:default;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    main{width:min(1020px,94vw);padding:clamp(28px,5vh,48px) 16px 40px;flex:1;position:relative;z-index:2}
    .kicker{font-size:.9rem;font-weight:700;letter-spacing:.32em;text-transform:uppercase;color:var(--pen)}
    h1{margin-top:10px;font-family:Georgia,serif;font-size:clamp(1.6rem,4.5vw,2.4rem)}
    .flash{margin-top:16px;padding:13px 18px;border-radius:12px;border:1.5px dashed rgba(43,74,139,.4);background:rgba(43,74,139,.06);line-height:1.5}
    .flash.is-err{border-color:rgba(228,87,46,.55);background:rgba(228,87,46,.07)}
    .grid2{margin-top:24px;display:grid;gap:20px}
    @media(min-width:860px){.grid2{grid-template-columns:1fr 1fr}}
    .panel{background:var(--card);border:1px solid rgba(43,74,139,.14);border-radius:14px;padding:24px;box-shadow:0 3px 16px rgba(29,46,82,.06)}
    .panel h2{font-family:Georgia,serif;font-size:1.2rem;margin-bottom:14px}
    .panel h3{font-size:1rem;margin:16px 0 8px}
    label{display:grid;gap:6px;font-weight:700;font-size:.93rem;margin-bottom:14px}
    input,select{font:inherit;font-weight:400;padding:10px 13px;border-radius:10px;border:1.5px solid rgba(43,74,139,.25);background:#fff;color:var(--ink);width:100%}
    input:focus-visible,select:focus-visible{outline:2px solid var(--pen);outline-offset:1px}
    .muted{color:var(--ink-soft);font-weight:400;font-size:.9rem}
    button{font:inherit;font-weight:700;padding:10px 18px;border:none;border-radius:999px;background:var(--ink);color:var(--paper);cursor:pointer}
    button:hover{background:var(--pen)}
    button.ghost{background:transparent;color:var(--pen);border:1.5px solid rgba(228,87,46,.5);padding:6px 14px;font-size:.85rem}
    button.small{padding:6px 14px;font-size:.85rem}
    .row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 0;border-bottom:1px dashed rgba(43,74,139,.2);flex-wrap:wrap}
    .row:last-of-type{border-bottom:none}
    .row .who{font-weight:700}
    .row .meta{color:var(--ink-soft);font-size:.9rem;font-weight:400}
    .empty{color:var(--ink-soft);font-size:.95rem;padding:8px 0 4px}
    .pill{display:inline-block;font-size:.78rem;font-weight:700;padding:3px 10px;border-radius:99px;background:rgba(43,74,139,.1);color:var(--ink-soft)}
    .pill.free{background:rgba(46,160,67,.12);color:#1a7f37}
    .pill.full{background:rgba(228,87,46,.12);color:var(--pen)}
    a.joinlink{color:var(--pen);font-weight:700}
    .danger{border-color:rgba(228,87,46,.4)}
    .danger h2{color:var(--pen)}
    footer{padding:26px 16px 34px;font-size:.88rem;color:var(--ink-soft);position:relative;z-index:2}
  </style>
</head>
<body>
  <header>
    <div class="header-in">
    <a class="brand" href="/"><span class="brand-mark">π</span> Ora de Mate <em>Online</em></a>
    <nav>
      <a href="/">Acasă</a>
      <a href="/cursuri">Cursuri</a>
      <a href="/materiale">Materiale</a>
      <?php if (is_admin($u)): ?>
        <a href="/admin/" style="background:var(--pen);color:var(--paper)">⚙ Admin</a>
      <?php endif; ?>
      <span class="me" title="Ești conectat ca <?= e($u['email']) ?>">👤 <?= e($who) ?></span>
      <form method="post" action="/api/logout.php" style="display:inline">
        <button type="submit" class="ghost" title="Ieși din cont">Ieși</button>
      </form>
    </nav>
    </div>
  </header>

  <main>
    <p class="kicker">Contul meu</p>
    <h1>Salut, <?= e($u['full_name'] !== '' ? $u['full_name'] : 'părinte') ?>! 👋</h1>

    <?php if ($flash !== null): ?>
      <div class="flash<?= $isErr ? ' is-err' : '' ?>"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if ($upcoming): ?>
    <section class="panel" style="margin-top:24px">
      <h2>📅 Programările viitoare</h2>
      <?php foreach ($upcoming as $b): ?>
        <div class="row">
          <div>
            <span class="who"><?= e($b['title']) ?></span> <span class="pill"><?= e($b['first_name']) ?></span>
            <div class="meta"><?= e(ro_dt((string) $b['starts_at'], $months)) ?> · <?= (int) $b['duration_min'] ?> min
              <?php if ($b['meet_link']): ?> · <a class="joinlink" href="<?= e((string) $b['meet_link']) ?>" rel="noopener">Intră la oră →</a><?php endif; ?>
            </div>
          </div>
          <form method="post" action="/api/booking-cancel.php" onsubmit="return confirm('Anulezi programarea?')">
            <input type="hidden" name="booking_id" value="<?= (int) $b['booking_id'] ?>" />
            <button type="submit" class="ghost">Anulează</button>
          </form>
        </div>
      <?php endforeach; ?>
      <p class="muted" style="margin-top:10px">Anularea sau mutarea la altă oră se face cu cel puțin 24 de ore înainte.</p>
    </section>
    <?php endif; ?>

    <div class="grid2">
      <section class="panel">
        <h2>Datele tale</h2>
        <p class="muted" style="margin-bottom:14px">
          Email: <strong><?= e($u['email']) ?></strong> ✓ verificat
          <?php if (array_key_exists('new_email', $u) && $u['new_email'] !== null): ?>
            <br /><span style="color:var(--pen)">→ schimbare în așteptare către <?= e((string) $u['new_email']) ?> (confirmă din emailul primit)</span>
          <?php endif; ?>
        </p>
        <form method="post" action="/api/profile.php">
          <label>Numele afișat
            <input type="text" name="full_name" required minlength="2" maxlength="120" value="<?= e($u['full_name']) ?>" />
          </label>
          <button type="submit">Salvează</button>
        </form>

        <h3 style="margin-top:20px;font-size:1rem">Schimbă adresa de email</h3>
        <p class="muted" style="margin:6px 0 12px">Primești un link de confirmare pe adresa nouă; cea veche rămâne activă până confirmi.</p>
        <form method="post" action="/api/email-change.php">
          <label>Noua adresă de email
            <input type="email" name="new_email" required maxlength="190" autocomplete="email" />
          </label>
          <label>Parola contului <span class="muted">(pentru siguranță)</span>
            <input type="password" name="password" required autocomplete="current-password" />
          </label>
          <button type="submit">Trimite confirmarea</button>
        </form>
      </section>

      <section class="panel">
        <h2>🎒 Elevii din cont</h2>
        <?php if (!$children): ?><p class="empty">Niciun profil încă — adaugă mai jos elevul (copilul tău, sau chiar tu, dacă ești elev cu cont propriu).</p><?php endif; ?>
        <?php foreach ($children as $c): ?>
          <div class="row">
            <div>
              <span class="who"><?= e($c['first_name']) ?></span>
              <div class="meta"><?= e($grades[(int) $c['grade']] ?? '') ?><?= $c['school'] ? ' · ' . e((string) $c['school']) : '' ?></div>
            </div>
            <form method="post" action="/api/child-delete.php" onsubmit="return confirm('Ștergi profilul <?= e($c['first_name']) ?>?')">
              <input type="hidden" name="child_id" value="<?= (int) $c['id'] ?>" />
              <button type="submit" class="ghost">Șterge</button>
            </form>
          </div>
        <?php endforeach; ?>
        <form method="post" action="/api/child-save.php" style="margin-top:16px">
          <label>Prenumele elevului <input type="text" name="first_name" required minlength="2" maxlength="80" /></label>
          <label>Clasa
            <select name="grade" required>
              <option value="" disabled selected>alege clasa</option>
              <?php foreach ($grades as $i => $g): ?><option value="<?= $i ?>"><?= e($g) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>Școala <span class="muted">(opțional)</span> <input type="text" name="school" maxlength="160" /></label>
          <button type="submit">Adaugă elevul</button>
        </form>
      </section>
    </div>

    <section class="panel" style="margin-top:20px">
      <h2>🗓️ Programează o oră</h2>
      <?php if (!$children): ?>
        <p class="empty">Adaugă întâi profilul elevului — orele se afișează pe clasa lui.</p>
      <?php endif; ?>
      <?php foreach ($children as $c): ?>
        <h3><?= e($c['first_name']) ?> · <?= e($grades[(int) $c['grade']] ?? '') ?></h3>
        <?php if (empty($availByChild[$c['id']])): ?>
          <p class="empty">Nicio oră programată încă pentru clasa asta — se anunță aici când apar. ✏️</p>
        <?php endif; ?>
        <?php foreach ($availByChild[$c['id']] as $s): $left = (int) $s['capacity'] - (int) $s['booked']; ?>
          <div class="row">
            <div>
              <span class="who"><?= e($s['title']) ?></span>
              <div class="meta"><?= e(ro_dt((string) $s['starts_at'], $months)) ?> · <?= (int) $s['duration_min'] ?> min ·
                <?php if ((int) $s['mine'] > 0): ?><span class="pill free">înscris ✓</span>
                <?php elseif ($left <= 0): ?><span class="pill full">complet</span>
                <?php else: ?><span class="pill"><?= $left ?> locuri libere</span><?php endif; ?>
              </div>
            </div>
            <?php if ((int) $s['mine'] === 0 && $left > 0): ?>
              <form method="post" action="/api/book.php">
                <input type="hidden" name="session_id" value="<?= (int) $s['id'] ?>" />
                <input type="hidden" name="child_id" value="<?= (int) $c['id'] ?>" />
                <button type="submit" class="small">Înscrie-l</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </section>

    <div class="grid2">
      <section class="panel">
        <h2>📚 Materialele mele</h2>
        <?php if (!$purchases): ?>
          <p class="empty">Aici vor apărea materialele și cursurile cumpărate — cu buton de descărcare. Magazinul se deschide în curând. 🛒</p>
        <?php endif; ?>
        <?php foreach ($purchases as $p): ?>
          <div class="row">
            <div>
              <span class="who"><?= e($p['title']) ?></span> <span class="pill"><?= e($p['type']) ?></span>
              <div class="meta">cumpărat la <?= e(ro_dt((string) $p['created_at'], $months)) ?> · descărcări: <?= (int) $p['download_count'] ?>/5</div>
            </div>
            <?php if ($p['file_name']): ?>
              <a href="/api/download.php?id=<?= (int) $p['id'] ?>"><button type="button" class="small">Descarcă</button></a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="panel">
        <h2>📖 Istoric ore</h2>
        <?php if (!$history): ?>
          <p class="empty">Orele la care a participat elevul vor apărea aici, după ce trec.</p>
        <?php endif; ?>
        <?php foreach ($history as $h): ?>
          <div class="row">
            <div>
              <span class="who"><?= e($h['title']) ?></span> <span class="pill"><?= e($h['first_name']) ?></span>
              <div class="meta"><?= e(ro_dt((string) $h['starts_at'], $months)) ?></div>
            </div>
            <?php if ($h['attended'] !== null): ?>
              <span class="pill <?= $h['attended'] ? 'free' : 'full' ?>"><?= $h['attended'] ? 'prezent ✓' : 'absent' ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </section>
    </div>

    <section class="panel danger" style="margin-top:20px">
      <h2>Ștergerea contului</h2>
      <p class="muted" style="margin-bottom:12px">
        Ștergerea e definitivă: dispar profilurile elevilor, programările și accesul la materiale.
        Pentru confirmare, introdu parola contului.
      </p>
      <form method="post" action="/api/account-delete.php"
            onsubmit="return confirm('Sigur ștergi contul? Acțiunea NU se poate anula.')"
            style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <label style="margin:0;flex:1;min-width:220px">Parola contului
          <input type="password" name="password" required autocomplete="current-password" />
        </label>
        <button type="submit" class="ghost">Șterge contul definitiv</button>
      </form>
    </section>
  </main>

  <footer>© 2026 Ora de Mate Online · orademateonline.ro</footer>
</body>
</html>
