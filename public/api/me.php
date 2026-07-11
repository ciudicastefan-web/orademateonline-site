<?php
/** „Cine sunt?” — endpoint minimal pentru interfață: spune paginilor statice
 *  dacă vizitatorul e logat și dacă e admin (pentru butonul de admin din meniu). */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$u = current_user();

if (!$u || $u['blocked_at'] !== null) {
    echo json_encode(['auth' => false]);
    exit;
}

echo json_encode([
    'auth'  => true,
    'name'  => $u['full_name'],
    'admin' => is_admin($u),
], JSON_UNESCAPED_UNICODE);
