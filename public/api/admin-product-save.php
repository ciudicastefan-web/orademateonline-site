<?php
/** Admin: creează un produs (PDF/curs). Fișierul se urcă separat, prin File Manager,
 *  în /home/olsibrej/materiale/ — aici se trece doar numele lui. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
require_admin();

$title = trim($_POST['title'] ?? '');
$type = in_array($_POST['type'] ?? '', ['pdf', 'curs'], true) ? $_POST['type'] : 'pdf';
$fileName = basename(trim($_POST['file_name'] ?? ''));
$priceLei = (float) str_replace(',', '.', (string) ($_POST['price_lei'] ?? '0'));

if (mb_strlen($title) < 3) {
    redirect('/admin/?err=produs-titlu');
}

db()->prepare('INSERT INTO products (type, title, file_name, price_cents) VALUES (?, ?, ?, ?)')
    ->execute([$type, $title, $fileName !== '' ? $fileName : null, (int) round($priceLei * 100)]);

redirect('/admin/?ok=produs-creat');
