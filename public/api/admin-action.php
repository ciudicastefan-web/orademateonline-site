<?php
/** Acțiuni de administrare asupra conturilor: blocare, deblocare, activare manuală,
 *  retrimiterea emailului de activare. Doar pentru adminii din ADMIN_EMAILS. */
declare(strict_types=1);
define('APP_ENTRY', 1);
require __DIR__ . '/_lib.php';

require_post();
require_same_origin();
$admin = require_admin();

$action = $_POST['action'] ?? '';
$userId = (int) ($_POST['user_id'] ?? 0);

$st = db()->prepare('SELECT * FROM users WHERE id = ?');
$st->execute([$userId]);
$target = $st->fetch();

if (!$target) {
    redirect('/admin/?err=negasit');
}
if ((int) $target['id'] === (int) $admin['id'] && in_array($action, ['block'], true)) {
    redirect('/admin/?err=autoblocare');
}

switch ($action) {
    case 'block':
        db()->prepare('UPDATE users SET blocked_at = NOW() WHERE id = ?')->execute([$userId]);
        redirect('/admin/?ok=blocat');

    case 'unblock':
        db()->prepare('UPDATE users SET blocked_at = NULL WHERE id = ?')->execute([$userId]);
        redirect('/admin/?ok=deblocat');

    case 'activate':
        db()->prepare('UPDATE users SET email_verified_at = NOW(), verify_token_hash = NULL, verify_expires_at = NULL WHERE id = ?')
            ->execute([$userId]);
        redirect('/admin/?ok=activat');

    case 'resend':
        if ($target['email_verified_at'] !== null) {
            redirect('/admin/?err=deja-activat');
        }
        $token = bin2hex(random_bytes(32));
        db()->prepare('UPDATE users SET verify_token_hash = ?, verify_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?')
            ->execute([hash('sha256', $token), $userId]);
        $link = BASE_URL . '/api/verify.php?uid=' . $userId . '&t=' . $token;
        send_app_mail(
            $target['email'],
            'Activează-ți contul — Ora de Mate Online',
            "Salut, {$target['full_name']}!\n\nActivează-ți contul printr-un click (link valabil 24h):\n\n{$link}\n\n— Ora de Mate Online"
        );
        redirect('/admin/?ok=retrimis');

    default:
        redirect('/admin/?err=actiune');
}
