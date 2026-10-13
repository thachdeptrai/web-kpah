<?php
if (!defined('KP_ROOT')) { http_response_code(403); exit; }

/** SMTP AUTH LOGIN, STARTTLS; credentials must come from server-side config. */
function kp_send_mail_smtp($to, $subject, $body)
{
    $c = kp_config('mail', []);
    foreach (['smtp_host','smtp_user','smtp_pass','from_email'] as $k) {
        if (empty($c[$k])) { throw new RuntimeException('Thiếu cấu hình SMTP: '.$k); }
    }
    $host = (string)$c['smtp_host'];
    $port = (int)($c['smtp_port'] ?? 587);
    $secure = (string)($c['smtp_secure'] ?? 'tls');
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
        if (!in_array($code, (array)$expected, true)) { throw new RuntimeException('SMTP trả mã '.$code); }
        return $line;
    };
    $cmd = function($line, $expected) use ($fp, $read) {
        fwrite($fp, $line."\r\n");
        return $read($expected);
    };
    try {
        $read([220]);
        $cmd('EHLO '.(isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^a-z0-9.-]/i','',$_SERVER['HTTP_HOST']) : 'localhost'), [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { throw new RuntimeException('Không bật được TLS cho SMTP.'); }
            $cmd('EHLO localhost', [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode((string)$c['smtp_user']), [334]);
        $cmd(base64_encode((string)$c['smtp_pass']), [235]);
        $from = (string)$c['from_email'];
        $cmd('MAIL FROM:<'.$from.'>', [250]);
        $cmd('RCPT TO:<'.$to.'>', [250,251]);
        $cmd('DATA', [354]);
        $name = (string)($c['from_name'] ?? 'KPAH');
        $headers = [
            'From: =?UTF-8?B?'.base64_encode($name).'?= <'.$from.'>',
            'To: <'.$to.'>',
            'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
            'Date: '.date(DATE_RFC2822),
            'Message-ID: <'.bin2hex(random_bytes(16)).'@'.(strpos($from, '@') !== false ? substr(strrchr($from, '@'), 1) : 'localhost').'>',
            'Reply-To: <'.$from.'>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Auto-Submitted: auto-generated',
            'X-Auto-Response-Suppress: All',
        ];
        $message = implode("\r\n", $headers)."\r\n\r\n".$body;
        $message = preg_replace('/\r?\n\./', "\r\n..", str_replace(["\r\n","\r"],"\n",$message));
        fwrite($fp, str_replace("\n","\r\n",$message)."\r\n.\r\n");
        $read([250]);
        $cmd('QUIT', [221]);
    } finally { fclose($fp); }
}
