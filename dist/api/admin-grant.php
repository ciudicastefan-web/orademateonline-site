<?php
/** Admin: acordă manual unui utilizator accesul la un produs (ex. plată prin transfer,
 *  bonus, test). Devine "achiziție" vizibilă în contul lui, cu descărcare. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
require_admin();

$email = strtolower(trim($_POST['email'] ?? ''));
$productId = (int) ($_POST['product_id'] ?? 0);

$st = db()->prepare('SELECT id FROM users WHERE email = ?');
$st->execute([$email]);
$user = $st->fetch();
if (!$user) {
    redirect('/admin/?err=negasit');
}

$st = db()->prepare('SELECT id FROM products WHERE id = ?');
$st->execute([$productId]);
if (!$st->fetch()) {
    redirect('/admin/?err=produs-negasit');
}

$st = db()->prepare('SELECT id FROM purchases WHERE user_id = ? AND product_id = ? AND status = "paid"');
$st->execute([$user['id'], $productId]);
if ($st->fetch()) {
    redirect('/admin/?err=acces-existent');
}

db()->prepare('INSERT INTO purchases (user_id, product_id, status) VALUES (?, ?, "paid")')
    ->execute([$user['id'], $productId]);

redirect('/admin/?ok=acces-acordat');
