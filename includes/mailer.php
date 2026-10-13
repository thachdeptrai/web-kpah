<?php
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

/**
 * SMTP AUTH LOGIN + STARTTLS. Credentials must remain server-side.
 * For Gmail SMTP, align the visible From address with the authenticated Gmail
 * account; a mismatched From address can hurt authentication and deliverability.
 */
function kp_send_mail_smtp($to, $subject, $body)
{
    $c = kp_config('mail', []);
    foreach (['smtp_host','smtp_user','smtp_pass','from_email'] as $k) {
        if (empty($c[$k])) { throw new RuntimeException('Thiếu cấu hình SMTP: '.$k); }
    }

    $host = (string)$c['smtp_host'];
    $port = (int)($c['smtp_port'] ?? 587);
    $secure = (string)($c['smtp_secure'] ?? 'tls');
    $smtpUser = trim((string)$c['smtp_user']);
    $from = trim((string)$c['from_email']);

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Địa chỉ email người nhận không hợp lệ.');
    }
    if (!filter_var($smtpUser, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Địa chỉ email SMTP không hợp lệ.');
    }
    if (preg_match('/[\r\n]/', (string)$subject)) {
        throw new RuntimeException('Tiêu đề email không hợp lệ.');
    }

    // Gmail SMTP: use the authenticated Gmail address as From to avoid spoof-like headers.
    // If a verified Gmail "Send mail as" alias is intentionally used, configure smtp_user
    // to that supported account/alias setup rather than spoofing an unrelated address.
    if (in_array(strtolower($host), ['smtp.gmail.com', 'smtp.googlemail.com'], true)) {
        $from = $smtpUser;
    }

    $remote = ($secure === 'ssl' ? 'ssl://' : '').$host.':'.$port;
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$fp) { throw new RuntimeException('Không kết nối được SMTP: '.$errno); }
    stream_set_timeout($fp, 15);

    $read = function($expected) use ($fp) {
        $line = '';
        do {
            $line = fgets($fp, 515);
            if ($line === false) { throw new RuntimeException('SMTP đóng kết nối.'); }
        } while (isset($line[3]) && $line[3] === '-');
        $code = (int)substr($line, 0, 3);
        if (!in_array($code, (array)$expected, true)) {
            throw new RuntimeException('SMTP trả mã '.$code);
        }
        return $line;
    };
    $cmd = function($line, $expected) use ($fp, $read) {
        fwrite($fp, $line."\r\n");
        return $read($expected);
    };

    try {
        $read([220]);
        $ehloHost = isset($_SERVER['HTTP_HOST'])
            ? preg_replace('/[^a-z0-9.-]/i', '', (string)$_SERVER['HTTP_HOST'])
            : 'localhost';
        if ($ehloHost === '') { $ehloHost = 'localhost'; }
        $cmd('EHLO '.$ehloHost, [250]);

        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Không bật được TLS cho SMTP.');
            }
            $cmd('EHLO '.$ehloHost, [250]);
        }

        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($smtpUser), [334]);
        $cmd(base64_encode((string)$c['smtp_pass']), [235]);
        $cmd('MAIL FROM:<'.$from.'>', [250]);
        $cmd('RCPT TO:<'.$to.'>', [250, 251]);
        $cmd('DATA', [354]);

        $name = trim((string)($c['from_name'] ?? 'KPAH'));
        if ($name === '') { $name = 'KPAH'; }
        $fromDomain = substr(strrchr($from, '@'), 1);
        $headers = [
            'From: =?UTF-8?B?'.base64_encode($name).'?= <'.$from.'>',
            'To: <'.$to.'>',
            'Subject: =?UTF-8?B?'.base64_encode((string)$subject).'?=',
            'Date: '.date(DATE_RFC2822),
            'Message-ID: <'.bin2hex(random_bytes(16)).'@'.$fromDomain.'>',
            'Reply-To: <'.$from.'>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Auto-Submitted: auto-generated',
            'X-Auto-Response-Suppress: All',
            'X-Mailer: KPAH Account Mailer',
        ];

        // Base64-encode UTF-8 text for reliable transport of Vietnamese characters.
        $plainBody = str_replace(["\r\n", "\r"], "\n", (string)$body);
        $encodedBody = rtrim(chunk_split(base64_encode($plainBody), 76, "\r\n"), "\r\n");
        $message = implode("\r\n", $headers)."\r\n\r\n".$encodedBody;
        $message = preg_replace('/\r?\n\./', "\r\n..", $message);
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        fwrite($fp, str_replace("\n", "\r\n", $message)."\r\n.\r\n");
        $read([250]);
        $cmd('QUIT', [221]);
    } finally {
        fclose($fp);
    }
}
