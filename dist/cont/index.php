<?php
/** Pagina „Contul meu” — datele părintelui + profilurile copiilor. Necesită login. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require dirname(__DIR__) . '/api/_lib.php';

$u = current_user();
if (!$u) {
    redirect('/autentificare');
}

$st = db()->prepare('SELECT * FROM children WHERE user_id = ? ORDER BY id');
$st->execute([$u['id']]);
$children = $st->fetchAll();

$grades = ['Pregătitoare', 'Clasa I', 'Clasa a II-a', 'Clasa a III-a', 'Clasa a IV-a', 'Clasa a V-a',
           'Clasa a VI-a', 'Clasa a VII-a', 'Clasa a VIII-a', 'Clasa a IX-a', 'Clasa a X-a',
           'Clasa a XI-a', 'Clasa a XII-a'];

$flash = null;
$isErr = false;
$map = [
    'ok=copil'        => 'Profilul copilului a fost adăugat.',
    'ok=copil-sters'  => 'Profilul a fost șters.',
    'ok=profil'       => 'Datele tale au fost actualizate.',
    'err=copil-nume'  => 'Prenumele copilului trebuie să aibă între 2 și 80 de caractere.',
    'err=copil-clasa' => 'Alege clasa copilului.',
    'err=copil-limita'=> 'Ai atins numărul maxim de profiluri (6).',
    'err=nume'        => 'Numele trebuie să aibă între 2 și 120 de caractere.',
];
$qs = $_SERVER['QUERY_STRING'] ?? '';
foreach ($map as $k => $msg) {
    if (str_contains($qs, $k)) {
        $flash = $msg;
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
    header{width:min(920px,92vw);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:22px 16px 0;position:relative;z-index:2}
    .brand{display:inline-flex;align-items:center;gap:10px;font-family:Georgia,serif;font-weight:700;font-size:1.15rem;color:var(--ink);text-decoration:none}
    .brand em{color:var(--pen);font-style:normal}
    .brand-mark{display:inline-grid;place-items:center;width:34px;height:34px;border-radius:9px;background:var(--ink);color:var(--paper)}
    nav{display:flex;gap:4px;align-items:center}
    nav a{padding:8px 14px;border-radius:999px;font-size:.95rem;font-weight:700;color:var(--ink-soft);text-decoration:none}
    nav a:hover{color:var(--ink);background:rgba(43,74,139,.07)}
    main{width:min(920px,92vw);padding:clamp(32px,6vh,56px) 16px 40px;flex:1;position:relative;z-index:2}
    .kicker{font-size:.9rem;font-weight:700;letter-spacing:.32em;text-transform:uppercase;color:var(--pen)}
    h1{margin-top:10px;font-family:Georgia,serif;font-size:clamp(1.7rem,4.5vw,2.5rem)}
    .flash{margin-top:18px;max-width:560px;padding:13px 18px;border-radius:12px;border:1.5px dashed rgba(43,74,139,.4);background:rgba(43,74,139,.06);line-height:1.5}
    .flash.is-err{border-color:rgba(228,87,46,.55);background:rgba(228,87,46,.07)}
    .grid2{margin-top:26px;display:grid;gap:20px}
    @media(min-width:820px){.grid2{grid-template-columns:1fr 1fr}}
    .panel{background:var(--card);border:1px solid rgba(43,74,139,.14);border-radius:14px;padding:24px;box-shadow:0 3px 16px rgba(29,46,82,.06)}
    .panel h2{font-family:Georgia,serif;font-size:1.2rem;margin-bottom:14px}
    label{display:grid;gap:6px;font-weight:700;font-size:.93rem;margin-bottom:14px}
    input,select{font:inherit;font-weight:400;padding:10px 13px;border-radius:10px;border:1.5px solid rgba(43,74,139,.25);background:#fff;color:var(--ink);width:100%}
    input:focus-visible,select:focus-visible{outline:2px solid var(--pen);outline-offset:1px}
    .muted{color:var(--ink-soft);font-weight:400;font-size:.88rem}
    button{font:inherit;font-weight:700;padding:10px 18px;border:none;border-radius:999px;background:var(--ink);color:var(--paper);cursor:pointer}
    button:hover{background:var(--pen)}
    button.ghost{background:transparent;color:var(--pen);border:1.5px solid rgba(228,87,46,.5);padding:6px 14px;font-size:.85rem}
    .child{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 0;border-bottom:1px dashed rgba(43,74,139,.2)}
    .child:last-of-type{border-bottom:none}
    .child .who{font-weight:700}
    .child .meta{color:var(--ink-soft);font-size:.9rem;font-weight:400}
    .empty{color:var(--ink-soft);font-size:.95rem;padding:8px 0 4px}
    footer{padding:26px 16px 34px;font-size:.88rem;color:var(--ink-soft)}
  </style>
</head>
<body>
  <header>
    <a class="brand" href="/"><span class="brand-mark">π</span> Ora de Mate <em>Online</em></a>
    <nav>
      <a href="/">Acasă</a>
      <a href="/cursuri">Cursuri</a>
      <form method="post" action="/api/logout.php" style="display:inline">
        <button type="submit" class="ghost">Ieși din cont</button>
      </form>
    </nav>
  </header>

  <main>
    <p class="kicker">Contul meu</p>
    <h1>Salut, <?= e($u['full_name'] !== '' ? $u['full_name'] : 'părinte') ?>! 👋</h1>

    <?php if ($flash !== null): ?>
      <div class="flash<?= $isErr ? ' is-err' : '' ?>"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="grid2">
      <section class="panel">
        <h2>Datele tale</h2>
        <p class="muted" style="margin-bottom:14px">Email: <strong><?= e($u['email']) ?></strong> ✓ verificat</p>
        <form method="post" action="/api/profile.php">
          <label>
            Numele afișat
            <input type="text" name="full_name" required minlength="2" maxlength="120" value="<?= e($u['full_name']) ?>" />
          </label>
          <button type="submit">Salvează</button>
        </form>
      </section>

      <section class="panel">
        <h2>Copiii înscriși</h2>
        <?php if (!$children): ?>
          <p class="empty">Niciun profil încă — adaugă mai jos primul copil. 🎒</p>
        <?php endif; ?>
        <?php foreach ($children as $c): ?>
          <div class="child">
            <div>
              <span class="who"><?= e($c['first_name']) ?></span>
              <div class="meta">
                <?= e($grades[(int) $c['grade']] ?? ('Clasa ' . (int) $c['grade'])) ?>
                <?= $c['school'] !== null && $c['school'] !== '' ? ' · ' . e($c['school']) : '' ?>
              </div>
            </div>
            <form method="post" action="/api/child-delete.php" onsubmit="return confirm('Ștergi profilul <?= e($c['first_name']) ?>?')">
              <input type="hidden" name="child_id" value="<?= (int) $c['id'] ?>" />
              <button type="submit" class="ghost">Șterge</button>
            </form>
          </div>
        <?php endforeach; ?>

        <form method="post" action="/api/child-save.php" style="margin-top:18px">
          <label>
            Prenumele copilului
            <input type="text" name="first_name" required minlength="2" maxlength="80" />
          </label>
          <label>
            Clasa
            <select name="grade" required>
              <option value="" disabled selected>alege clasa</option>
              <?php foreach ($grades as $i => $g): ?>
                <option value="<?= $i ?>"><?= e($g) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Școala <span class="muted">(opțional)</span>
            <input type="text" name="school" maxlength="160" />
          </label>
          <button type="submit">Adaugă copilul</button>
        </form>
      </section>
    </div>
  </main>

  <footer>© 2026 Ora de Mate Online · orademateonline.ro</footer>
</body>
</html>
