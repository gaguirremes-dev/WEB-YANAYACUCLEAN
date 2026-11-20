<?php
/**
 * Minimal SMTP sender for first-party forms (implicit TLS on 465, STARTTLS otherwise,
 * certificate verified by default). Same mechanism and config constants as the claims
 * book (SMTP_HOST / SMTP_PORT / SMTP_USER / SMTP_PASS / SMTP_FROM_NAME / SMTP_VERIFY_TLS).
 * Blocked from HTTP by .htaccess (lib/). Functions only.
 */

function yyHeaderSafe(string $s): string {
    $s = str_ireplace(['%0d', '%0a'], '', $s);
    return trim((string)preg_replace('/[\x00-\x1F\x7F]+/', '', $s));
}

function yySmtpSend(string $toEmail, string $subject, string $htmlBody, string &$err = ''): bool {
    if (!defined('SMTP_HOST') || !defined('SMTP_USER') || !defined('SMTP_PASS')) {
        $err = 'SMTP configuration missing.';
        return false;
    }
    $port      = defined('SMTP_PORT') ? (int)SMTP_PORT : 465;
    $fromName  = defined('SMTP_FROM_NAME') ? (string)SMTP_FROM_NAME : 'YanaYacu Clean';
    $toEmail   = yyHeaderSafe($toEmail);
    if (strlen($toEmail) > 254 || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $err = 'Invalid recipient address.';
        return false;
    }
    $subject   = mb_substr(yyHeaderSafe($subject), 0, 200, 'UTF-8');
    $fromEmail = yyHeaderSafe(SMTP_USER);
    $enc = function (string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; };
    $eol = "\r\n";
    $headers  = 'Date: ' . date('r') . $eol;
    $headers .= 'From: ' . $enc(yyHeaderSafe($fromName)) . ' <' . $fromEmail . '>' . $eol;
    $headers .= 'To: <' . $toEmail . '>' . $eol;
    $headers .= 'Subject: ' . $enc($subject) . $eol;
    $headers .= 'MIME-Version: 1.0' . $eol;
    $headers .= 'Content-Type: text/html; charset=UTF-8' . $eol;
    $headers .= 'Content-Transfer-Encoding: base64' . $eol;
    $message = $headers . $eol . chunk_split(base64_encode($htmlBody), 76, $eol);

    $verify = defined('SMTP_VERIFY_TLS') ? filter_var(SMTP_VERIFY_TLS, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : true;
    $verify = ($verify === null) ? true : $verify;
    $sslOpts = ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify, 'SNI_enabled' => true];
    if ($verify) $sslOpts['peer_name'] = SMTP_HOST;
    $ctx = stream_context_create(['ssl' => $sslOpts]);
    $implicitTls = in_array($port, [465, 4655], true);
    $fp = @stream_socket_client(($implicitTls ? 'ssl://' : '') . SMTP_HOST . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $err = "SMTP connection failed: $errstr ($errno)"; return false; }
    stream_set_timeout($fp, 20);
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) { $data .= $line; if (strlen($line) < 4 || $line[3] === ' ') break; }
        return $data;
    };
    $cmd = function (string $c) use ($fp, $read): string { fwrite($fp, $c . "\r\n"); return $read(); };
    $ok  = function (string $resp, array $codes) use (&$err): bool {
        foreach ($codes as $code) { if (strncmp($resp, $code, strlen($code)) === 0) return true; }
        $err = trim($resp); return false;
    };
    $fail = function () use ($fp) { @fwrite($fp, "QUIT\r\n"); @fclose($fp); return false; };

    if (!$ok($read(), ['220']))                                   return $fail();
    if (!$ok($cmd('EHLO ' . SMTP_HOST), ['250']))                 return $fail();
    if (!$implicitTls) { // never send credentials in clear text
        if (!$ok($cmd('STARTTLS'), ['220']))                      return $fail();
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { $err = 'STARTTLS negotiation failed.'; return $fail(); }
        if (!$ok($cmd('EHLO ' . SMTP_HOST), ['250']))             return $fail();
    }
    if (!$ok($cmd('AUTH LOGIN'), ['334']))                        return $fail();
    if (!$ok($cmd(base64_encode(SMTP_USER)), ['334']))            return $fail();
    if (!$ok($cmd(base64_encode(SMTP_PASS)), ['235']))            return $fail();
    if (!$ok($cmd('MAIL FROM:<' . $fromEmail . '>'), ['250']))    return $fail();
    if (!$ok($cmd('RCPT TO:<' . $toEmail . '>'), ['250', '251'])) return $fail();
    if (!$ok($cmd('DATA'), ['354']))                              return $fail();
    if (!$ok($cmd($message . $eol . '.'), ['250']))               return $fail();
    $cmd('QUIT'); fclose($fp);
    return true;
}
