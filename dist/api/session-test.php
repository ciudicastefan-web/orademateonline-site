<?php
// TEMPORAR: test de persistență a sesiunii (se șterge după diagnostic).
// Incrementează un contor în sesiune — dacă la a doua cerere contorul crește,
// sesiunile persistă pe server.
declare(strict_types=1);
define('APP_ENTRY', 1);

if (($_GET['k'] ?? '') !== 'sesTest9m2Xw') {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/_lib.php';
session_boot();

$_SESSION['n'] = ($_SESSION['n'] ?? 0) + 1;

header('Content-Type: text/plain; charset=UTF-8');
echo 'contor=' . $_SESSION['n']
   . ' | session_id=' . substr(session_id(), 0, 8) . '…'
   . ' | save_path=' . session_save_path()
   . ' | writable=' . (is_writable(session_save_path()) ? 'DA' : 'NU')
   . "\n";
