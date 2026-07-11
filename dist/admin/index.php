<?php
/** Panoul de administrare — vizibil DOAR pentru emailurile din ADMIN_EMAILS (config).
 *  Pentru oricine altcineva răspunde 404, ca și cum pagina n-ar exista. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require dirname(__DIR__) . '/api/_lib.php';

$admin = require_admin();
$pdo = db();

/* ── statistici ─────────────────────────────────────────────── */
$stats = [
    'total'      => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'verificati' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL')->fetchColumn(),
    'blocati'    => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE blocked_at IS NOT NULL')->fetchColumn(),
    'copii'      => (int) $pdo->query('SELECT COUNT(*) FROM children')->fetchColumn(),
    'saptamana'  => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn(),
    'optin'      => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE marketing_optin = 1')->fetchColumn(),
];

$grades = ['Pregătitoare','Clasa I','Clasa a II-a','Clasa a III-a','Clasa a IV-a','Clasa a V-a',
           'Clasa a VI-a','Clasa a VII-a','Clasa a VIII-a','Clasa a IX-a','Clasa a X-a','Clasa a XI-a','Clasa a XII-a'];
$byGrade = $pdo->query('SELECT grade, COUNT(*) AS n FROM children GROUP BY grade ORDER BY grade')->fetchAll();
$maxGrade = 1;
foreach ($byGrade as $g) { $maxGrade = max($maxGrade, (int) $g['n']); }

/* ── utilizatori (cu numărul de copii) ──────────────────────── */
$users = $pdo->query(
    'SELECT u.*, (SELECT COUNT(*) FROM children c WHERE c.user_id = u.id) AS nr_copii
     FROM users u ORDER BY u.created_at DESC LIMIT 200'
)->fetchAll();

/* ── ore viitoare + produse ─────────────────────────────────── */
$sessions = $pdo->query(
    'SELECT s.*, (SELECT COUNT(*) FROM bookings b WHERE b.session_id = s.id AND b.status = "booked") AS booked
     FROM class_sessions s WHERE s.starts_at >= NOW() ORDER BY s.starts_at LIMIT 50'
)->fetchAll();

$products = $pdo->query('SELECT * FROM products ORDER BY created_at DESC LIMIT 100')->fetchAll();

$months = [1=>'ian',2=>'feb',3=>'mar',4=>'apr',5=>'mai',6=>'iun',7=>'iul',8=>'aug',9=>'sep',10=>'oct',11=>'nov',12=>'dec'];
function ro_dt(string $dt, array $months): string {
    $t = strtotime($dt);
    return date('j', $t) . ' ' . $months[(int) date('n', $t)] . date(' Y, H:i', $t);
}

/* ── mesaje flash ───────────────────────────────────────────── */
$map = [
    'ok=blocat' => 'Contul a fost blocat.', 'ok=deblocat' => 'Contul a fost deblocat.',
    'ok=activat' => 'Contul a fost activat manual.', 'ok=retrimis' => 'Emailul de activare a fost retrimis.',
    'ok=ora-creata' => 'Ora a fost creată — părinții cu copii pe clasa respectivă o văd deja în cont.',
    'ok=produs-creat' => 'Produsul a fost adăugat.',
    'ok=acces-acordat' => 'Accesul a fost acordat — apare la „Materialele mele” în contul utilizatorului.',
    'err=negasit' => 'Utilizatorul nu există.', 'err=autoblocare' => 'Nu îți poți bloca propriul cont.',
    'err=deja-activat' => 'Contul e deja activat.', 'err=actiune' => 'Acțiune necunoscută.',
    'err=ora-invalida' => 'Verifică titlul, clasa și data orei (trebuie să fie în viitor).',
    'err=ora-link' => 'Linkul de întâlnire nu pare un URL valid.',
    'err=produs-titlu' => 'Titlul produsului e prea scurt.',
    'err=produs-negasit' => 'Produsul selectat nu există.',
    'err=acces-existent' => 'Utilizatorul are deja acces la acest produs.',
];
$flash = null; $isErr = false;
$qs = $_SERVER['QUERY_STRING'] ?? '';
foreach ($map as $k => $m) {
    if (str_contains($qs, $k)) { $flash = $m; $isErr = str_starts_with($k, 'err'); break; }
}

function status_badge(array $u): string
{
    if ($u['blocked_at'] !== null) return '<span class="b b-blocked">⛔ blocat</span>';
    if ($u['email_verified_at'] === null) return '<span class="b b-pending">⏳ neactivat</span>';
    return '<span class="b b-ok">✓ activ</span>';
}
?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin — Ora de Mate Online</title>
  <meta name="robots" content="noindex, nofollow" />
  <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
  <style>
    :root{--paper:#fbf7ef;--grid:rgba(43,74,139,.09);--ink:#1d2e52;--ink-soft:#51618a;--pen:#e4572e;--card:#fffdf8;--cell:28px}
    *{margin:0;padding:0;box-sizing:border-box}
    body{min-height:100svh;display:flex;flex-direction:column;align-items:center;font-family:system-ui,sans-serif;color:var(--ink);
      background:repeating-linear-gradient(to right,transparent,transparent calc(var(--cell) - 1px),var(--grid) calc(var(--cell) - 1px),var(--grid) var(--cell)),
      repeating-linear-gradient(to bottom,transparent,transparent calc(var(--cell) - 1px),var(--grid) calc(var(--cell) - 1px),var(--grid) var(--cell)),var(--paper)}
    header{width:min(1180px,96vw);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:20px 16px 0}
    .brand{display:inline-flex;align-items:center;gap:10px;font-family:Georgia,serif;font-weight:700;font-size:1.1rem;color:var(--ink);text-decoration:none}
    .brand-mark{display:inline-grid;place-items:center;width:32px;height:32px;border-radius:8px;background:var(--pen);color:var(--paper)}
    nav{display:flex;gap:10px;align-items:center}
    nav a{padding:7px 13px;border-radius:999px;font-size:.9rem;font-weight:700;color:var(--ink-soft);text-decoration:none}
    nav a:hover{color:var(--ink);background:rgba(43,74,139,.07)}
    main{width:min(1180px,96vw);padding:26px 16px 44px;flex:1}
    h1{font-family:Georgia,serif;font-size:1.7rem}
    .sub{color:var(--ink-soft);margin-top:4px;font-size:.92rem}
    .flash{margin-top:16px;padding:12px 16px;border-radius:10px;border:1.5px dashed rgba(43,74,139,.4);background:rgba(43,74,139,.06)}
    .flash.is-err{border-color:rgba(228,87,46,.55);background:rgba(228,87,46,.07)}
    .cards{margin-top:22px;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
    .stat{background:var(--card);border:1px solid rgba(43,74,139,.14);border-radius:12px;padding:16px 18px;box-shadow:0 2px 10px rgba(29,46,82,.05)}
    .stat .n{font-family:Georgia,serif;font-size:1.9rem;font-weight:700}
    .stat .l{color:var(--ink-soft);font-size:.82rem;margin-top:2px}
    .panel{margin-top:22px;background:var(--card);border:1px solid rgba(43,74,139,.14);border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(29,46,82,.05)}
    .panel h2{font-family:Georgia,serif;font-size:1.15rem;margin-bottom:12px}
    .gradebar{display:flex;align-items:center;gap:10px;margin:6px 0}
    .gradebar .gl{width:110px;font-size:.85rem;color:var(--ink-soft);flex:none}
    .gradebar .gtrack{flex:1;background:rgba(43,74,139,.08);border-radius:99px;height:18px;overflow:hidden}
    .gradebar .gfill{height:100%;background:var(--pen);border-radius:99px;min-width:18px;color:#fff;font-size:.75rem;display:flex;align-items:center;justify-content:flex-end;padding-right:6px}
    table{width:100%;border-collapse:collapse;font-size:.9rem}
    th,td{padding:9px 10px;text-align:left;border-bottom:1px solid rgba(43,74,139,.1);vertical-align:middle}
    th{color:var(--ink-soft);font-size:.78rem;text-transform:uppercase;letter-spacing:.05em}
    .b{font-size:.78rem;font-weight:700;padding:3px 10px;border-radius:99px;white-space:nowrap}
    .b-ok{background:rgba(46,160,67,.12);color:#1a7f37}
    .b-pending{background:rgba(43,74,139,.1);color:var(--ink-soft)}
    .b-blocked{background:rgba(228,87,46,.12);color:var(--pen)}
    .acts{display:flex;gap:6px;flex-wrap:wrap}
    .acts form{display:inline}
    .acts button{font-size:.78rem;font-weight:700;padding:5px 11px;border-radius:99px;border:1.5px solid rgba(43,74,139,.3);background:transparent;color:var(--ink-soft);cursor:pointer}
    .acts button:hover{border-color:var(--ink);color:var(--ink)}
    .acts button.warn{border-color:rgba(228,87,46,.45);color:var(--pen)}
    .acts button.warn:hover{background:rgba(228,87,46,.08)}
    .muted{color:var(--ink-soft);font-size:.85rem}
    .soon{margin-top:8px;padding:14px 16px;border-radius:10px;border:1.5px dashed rgba(228,87,46,.5);background:rgba(228,87,46,.05);color:var(--ink-soft);font-size:.92rem}
    footer{padding:20px;font-size:.85rem;color:var(--ink-soft)}
    @media(max-width:760px){ .hide-sm{display:none} }
  </style>
</head>
<body>
  <header>
    <a class="brand" href="/admin/"><span class="brand-mark">⚙</span> Admin · Ora de Mate Online</a>
    <nav>
      <a href="/">Site</a>
      <a href="/cont/">Contul meu</a>
      <form method="post" action="/api/logout.php" style="display:inline">
        <button type="submit" style="font:inherit;font-weight:700;padding:7px 13px;border-radius:999px;border:1.5px solid rgba(228,87,46,.5);background:transparent;color:var(--pen);cursor:pointer">Ieși</button>
      </form>
    </nav>
  </header>

  <main>
    <h1>Panou de administrare</h1>
    <p class="sub">Salut, <?= e($admin['full_name']) ?>. Aici vezi tot ce mișcă pe site.</p>

    <?php if ($flash !== null): ?>
      <div class="flash<?= $isErr ? ' is-err' : '' ?>"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="cards">
      <div class="stat"><div class="n"><?= $stats['total'] ?></div><div class="l">conturi totale</div></div>
      <div class="stat"><div class="n"><?= $stats['verificati'] ?></div><div class="l">conturi activate</div></div>
      <div class="stat"><div class="n"><?= $stats['copii'] ?></div><div class="l">copii înscriși</div></div>
      <div class="stat"><div class="n"><?= $stats['saptamana'] ?></div><div class="l">conturi noi (7 zile)</div></div>
      <div class="stat"><div class="n"><?= $stats['optin'] ?></div><div class="l">abonați la noutăți</div></div>
      <div class="stat"><div class="n"><?= $stats['blocati'] ?></div><div class="l">conturi blocate</div></div>
    </div>

    <div class="panel">
      <h2>Copii pe clase</h2>
      <?php if (!$byGrade): ?>
        <p class="muted">Încă niciun copil înscris.</p>
      <?php endif; ?>
      <?php foreach ($byGrade as $g): ?>
        <div class="gradebar">
          <span class="gl"><?= e($grades[(int) $g['grade']] ?? ('Clasa ' . (int) $g['grade'])) ?></span>
          <div class="gtrack"><div class="gfill" style="width: <?= round(100 * (int) $g['n'] / $maxGrade) ?>%"><?= (int) $g['n'] ?></div></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="panel">
      <h2>🗓️ Ore programate (viitoare)</h2>
      <?php if (!$sessions): ?><p class="muted">Nicio oră viitoare — creează prima mai jos.</p><?php endif; ?>
      <?php foreach ($sessions as $s): ?>
        <div style="display:flex;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px dashed rgba(43,74,139,.15);flex-wrap:wrap">
          <div>
            <strong><?= e($s['title']) ?></strong>
            <span class="b b-pending"><?= e($grades[(int) $s['grade']] ?? '') ?></span>
            <div class="muted"><?= e(ro_dt((string) $s['starts_at'], $months)) ?> · <?= (int) $s['duration_min'] ?> min
              · înscriși: <strong><?= (int) $s['booked'] ?>/<?= (int) $s['capacity'] ?></strong>
              <?= $s['meet_link'] ? ' · are link' : ' · fără link încă' ?></div>
          </div>
        </div>
      <?php endforeach; ?>

      <h2 style="margin-top:18px">Creează o oră</h2>
      <form method="post" action="/api/admin-session-save.php" style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));align-items:end">
        <label style="grid-column:1/-1">Titlu (ex. „Fracții — recapitulare”)
          <input type="text" name="title" required minlength="3" maxlength="160" />
        </label>
        <label>Clasa
          <select name="grade" required>
            <?php foreach ($grades as $i => $g): ?><option value="<?= $i ?>"><?= e($g) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Data <input type="date" name="date" required /></label>
        <label>Ora <input type="time" name="time" required /></label>
        <label>Durata (min) <input type="number" name="duration" value="60" min="15" max="240" /></label>
        <label>Locuri <input type="number" name="capacity" value="8" min="1" max="30" /></label>
        <label style="grid-column:1/-1">Link întâlnire (Zoom/Meet — opțional, îl văd doar cei înscriși)
          <input type="url" name="meet_link" placeholder="https://..." />
        </label>
        <div><button type="submit">Creează ora</button></div>
      </form>
    </div>

    <div class="panel">
      <h2>📚 Produse (materiale / cursuri)</h2>
      <?php if (!$products): ?><p class="muted">Niciun produs încă.</p><?php endif; ?>
      <?php foreach ($products as $p): ?>
        <div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px dashed rgba(43,74,139,.15);flex-wrap:wrap">
          <div><strong><?= e($p['title']) ?></strong> <span class="b b-pending"><?= e($p['type']) ?></span>
            <span class="muted"><?= number_format($p['price_cents'] / 100, 2, ',', '.') ?> lei
              · fișier: <?= $p['file_name'] ? e((string) $p['file_name']) : '—' ?></span></div>
        </div>
      <?php endforeach; ?>

      <div style="display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));margin-top:16px">
        <div>
          <h2 style="font-size:1rem">Adaugă produs</h2>
          <form method="post" action="/api/admin-product-save.php" style="display:grid;gap:10px">
            <label>Titlu <input type="text" name="title" required minlength="3" maxlength="160" /></label>
            <label>Tip
              <select name="type"><option value="pdf">PDF (descărcabil)</option><option value="curs">Curs</option></select>
            </label>
            <label>Fișier (nume din folderul privat „materiale”)
              <input type="text" name="file_name" placeholder="ex. culegere-cls5.pdf" />
            </label>
            <label>Preț (lei) <input type="text" name="price_lei" value="0" /></label>
            <button type="submit">Adaugă</button>
          </form>
          <p class="muted" style="margin-top:8px">Fișierele se urcă prin File Manager în <code>/home/olsibrej/materiale/</code>.</p>
        </div>
        <div>
          <h2 style="font-size:1rem">Acordă acces manual</h2>
          <p class="muted" style="margin-bottom:8px">Pentru plăți prin transfer, bonusuri sau teste — produsul apare instant în contul utilizatorului.</p>
          <form method="post" action="/api/admin-grant.php" style="display:grid;gap:10px">
            <label>Emailul utilizatorului <input type="email" name="email" required /></label>
            <label>Produsul
              <select name="product_id" required>
                <?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach; ?>
              </select>
            </label>
            <button type="submit">Acordă accesul</button>
          </form>
        </div>
      </div>
    </div>

    <div class="panel">
      <h2>Vânzări și comenzi</h2>
      <p class="soon">🛒 Plățile online cu cardul se activează odată cu Stripe + facturarea (după PFA) —
        până atunci, accesele acordate manual de mai sus țin loc de vânzări.</p>
    </div>

    <div class="panel">
      <h2>Utilizatori (<?= count($users) ?>)</h2>
      <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>Nume</th><th>Email</th><th>Status</th><th class="hide-sm">Copii</th>
            <th class="hide-sm">Noutăți</th><th class="hide-sm">Creat</th><th>Acțiuni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><strong><?= e($u['full_name']) ?></strong><?= is_admin($u) ? ' <span class="b b-ok">admin</span>' : '' ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= status_badge($u) ?></td>
            <td class="hide-sm"><?= (int) $u['nr_copii'] ?></td>
            <td class="hide-sm"><?= $u['marketing_optin'] ? 'da' : '—' ?></td>
            <td class="hide-sm muted"><?= e(date('d.m.Y H:i', strtotime((string) $u['created_at']))) ?></td>
            <td>
              <div class="acts">
                <?php if ($u['email_verified_at'] === null): ?>
                  <form method="post" action="/api/admin-action.php">
                    <input type="hidden" name="action" value="activate" /><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>" />
                    <button type="submit">Activează</button>
                  </form>
                  <form method="post" action="/api/admin-action.php">
                    <input type="hidden" name="action" value="resend" /><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>" />
                    <button type="submit">Retrimite email</button>
                  </form>
                <?php endif; ?>
                <?php if ($u['blocked_at'] === null && (int) $u['id'] !== (int) $admin['id']): ?>
                  <form method="post" action="/api/admin-action.php" onsubmit="return confirm('Blochezi contul <?= e($u['email']) ?>?')">
                    <input type="hidden" name="action" value="block" /><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>" />
                    <button type="submit" class="warn">Blochează</button>
                  </form>
                <?php elseif ($u['blocked_at'] !== null): ?>
                  <form method="post" action="/api/admin-action.php">
                    <input type="hidden" name="action" value="unblock" /><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>" />
                    <button type="submit">Deblochează</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </main>

  <footer>Panou intern · nu e indexat de motoarele de căutare · © 2026 Ora de Mate Online</footer>
</body>
</html>
