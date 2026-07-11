<?php
// TEMPORAR: test de trimitere email de pe server. Se ȘTERGE după test.
// Protejat cu un token ca să nu-l poată declanșa oricine.
declare(strict_types=1);

if (($_GET['k'] ?? '') !== 'tq7Vm2kXp9Rw4z') {
    http_response_code(404);
    exit('Not found');
}

$to      = 'ciudicastefan@gmail.com';
$subject = 'Test email — Ora de Mate Online';
$body    = "Salut!\n\nAcesta este un email de test trimis de pe serverul orademateonline.ro (" . date('Y-m-d H:i:s') . ").\n\nDacă îl citești, fluxul de activare pe email e fezabil.\n\n— serverul tău";
$headers = implode("\r\n", [
    'From: Ora de Mate Online <no-reply@orademateonline.ro>',
    'Reply-To: no-reply@orademateonline.ro',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . PHP_VERSION,
]);

$ok = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);

header('Content-Type: text/plain; charset=UTF-8');
echo $ok ? "TRIMIS: mail() a acceptat mesajul. Verifică inboxul (și spamul)." : "EROARE: mail() a refuzat mesajul.";
