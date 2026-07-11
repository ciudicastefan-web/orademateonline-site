<?php
// TEMPORAR: test de trimitere email prin SMTP autentificat. Se ȘTERGE după test.
declare(strict_types=1);
define('APP_ENTRY', 1);

if (($_GET['k'] ?? '') !== 'tq7Vm2kXp9Rw4z') {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/_lib.php';
header('Content-Type: text/plain; charset=UTF-8');

if (!defined('SMTP_USER') || !defined('SMTP_PASS')) {
    exit("CONFIG LIPSA: adaugă SMTP_HOST/PORT/USER/PASS în /home/olsibrej/app_config.php");
}

$ok = send_app_mail(
    'ciudicastefan@gmail.com',
    'Test SMTP — Ora de Mate Online',
    "Salut!\n\nAcesta este un test trimis prin SMTP autentificat de pe orademateonline.ro ("
    . date('Y-m-d H:i:s') . ").\n\nDacă îl citești, fluxul de activare pe email e funcțional.\n\n— serverul tău"
);

echo $ok ? 'TRIMIS prin SMTP. Verifică inboxul (și spamul).' : 'EROARE: SMTP a refuzat (verifică userul/parola căsuței în config).';
