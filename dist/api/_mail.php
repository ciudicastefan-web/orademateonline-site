<?php
/**
 * Trimitere email prin SMTP autentificat (SSL, port 465) către serverul de mail
 * al domeniului. mail() e blocat pe acest hosting, iar SMTP-ul autentificat are
 * oricum livrabilitate mai bună (DKIM semnat de serverul de mail).
 * Constantele SMTP_* vin din /home/olsibrej/app_config.php.
 */
declare(strict_types=1);

if (!defined('APP_ENTRY')) {
    http_response_code(404);
    exit;
}

function smtp_send(string $to, string $subject, string $body): bool
{
    if (!defined('SMTP_HOST') || !defined('SMTP_USER') || !defined('SMTP_PASS')) {
        return false;
    }
    $port = defined('SMTP_PORT') ? SMTP_PORT : 465;

    $fp = @stream_socket_client('ssl://' . SMTP_HOST . ':' . $port, $errno, $errstr, 15);
    if (!$fp) {
        return false;
    }
    stream_set_timeout($fp, 15);

    $read = static function () use ($fp): string {
        $resp = '';
        while (($line = fgets($fp, 515)) !== false) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $resp;
    };
    $cmd = static function (string $c) use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        return $read();
    };
    $ok = static fn (string $resp, string $code): bool => strncmp($resp, $code, strlen($code)) === 0;

    $fail = static function () use ($fp): bool {
        fclose($fp);
        return false;
    };

    if (!$ok($read(), '220')) return $fail();
    if (!$ok($cmd('EHLO orademateonline.ro'), '250')) return $fail();
    if (!$ok($cmd('AUTH LOGIN'), '334')) return $fail();
    if (!$ok($cmd(base64_encode(SMTP_USER)), '334')) return $fail();
    if (!$ok($cmd(base64_encode(SMTP_PASS)), '235')) return $fail();
    if (!$ok($cmd('MAIL FROM:<' . SMTP_USER . '>'), '250')) return $fail();
    if (!$ok($cmd('RCPT TO:<' . $to . '>'), '250')) return $fail();
    if (!$ok($cmd('DATA'), '354')) return $fail();

    $headers = 'From: Ora de Mate Online <' . SMTP_USER . ">\r\n"
             . 'To: <' . $to . ">\r\n"
             . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: base64\r\n"
             . 'Date: ' . date('r') . "\r\n"
             . 'Message-ID: <' . bin2hex(random_bytes(12)) . "@orademateonline.ro>\r\n";

    fwrite($fp, $headers . "\r\n" . chunk_split(base64_encode($body)) . "\r\n.\r\n");
    $sent = $ok($read(), '250');
    $cmd('QUIT');
    fclose($fp);

    return $sent;
}
