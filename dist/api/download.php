<?php
/** Descărcarea unui material cumpărat. Fișierele stau în /home/olsibrej/materiale/
 *  (în afara zonei publice) și se servesc doar proprietarului achiziției, max 5 ori. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

const MAX_DOWNLOADS = 5;

$u = current_user();
if (!$u) {
    redirect('/autentificare');
}

$purchaseId = (int) ($_GET['id'] ?? 0);

$st = db()->prepare(
    'SELECT p.*, pr.title, pr.file_name FROM purchases p
     JOIN products pr ON pr.id = p.product_id
     WHERE p.id = ? AND p.user_id = ? AND p.status = "paid"'
);
$st->execute([$purchaseId, $u['id']]);
$purchase = $st->fetch();

if (!$purchase || $purchase['file_name'] === null) {
    redirect('/cont/?err=descarcare');
}
if ((int) $purchase['download_count'] >= MAX_DOWNLOADS) {
    redirect('/cont/?err=descarcare-limita');
}

// fără director traversal: doar numele de fișier simplu din baza de date
$fileName = basename((string) $purchase['file_name']);
$path = dirname(__DIR__, 2) . '/materiale/' . $fileName;

if (!is_file($path)) {
    redirect('/cont/?err=descarcare');
}

db()->prepare('UPDATE purchases SET download_count = download_count + 1 WHERE id = ?')
    ->execute([$purchaseId]);

$niceName = preg_replace('/[^A-Za-z0-9 _.-]/', '', $purchase['title']) ?: 'material';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $niceName . '.' . pathinfo($fileName, PATHINFO_EXTENSION) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
readfile($path);
exit;
