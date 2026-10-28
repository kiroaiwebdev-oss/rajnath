<?php

// Minimal single-connection SMTP server for tests: accepts AUTH PLAIN/LOGIN, records the whole
// conversation to a file, then exits. Usage: php smtp-capture-server.php <port> <outfile>
[$_, $port, $out] = $argv;
$srv = stream_socket_server("tcp://127.0.0.1:$port", $errno, $err);
if (! $srv) {
    fwrite(STDERR, "listen failed: $err\n");
    exit(1);
}
$c = @stream_socket_accept($srv, 20);
if (! $c) {
    exit(0);
}
$log = '';
$send = function (string $l) use ($c, &$log) { fwrite($c, $l."\r\n"); $log .= 'S: '.$l."\n"; };
$send('220 capture.test ESMTP');
$inData = false;
while (($line = fgets($c)) !== false) {
    $log .= ($inData ? '' : 'C: ').$line;
    $cmd = strtoupper(trim($line));
    if ($inData) {
        if (rtrim($line, "\r\n") === '.') {
            $inData = false;
            $send('250 OK queued');
            file_put_contents($out, $log);
        }
        continue;
    }
    if (str_starts_with($cmd, 'EHLO') || str_starts_with($cmd, 'HELO')) {
        fwrite($c, "250-capture.test\r\n250-AUTH PLAIN LOGIN\r\n250 8BITMIME\r\n");
        $log .= "S: 250 AUTH PLAIN LOGIN\n";
    } elseif (str_starts_with($cmd, 'AUTH LOGIN')) {
        $send('334 VXNlcm5hbWU6');
        $log .= 'C: '.fgets($c);
        $send('334 UGFzc3dvcmQ6');
        $log .= 'C: '.fgets($c);
        $send('235 Authentication successful');
    } elseif (str_starts_with($cmd, 'AUTH')) {
        $send('235 Authentication successful');
    } elseif (str_starts_with($cmd, 'MAIL FROM') || str_starts_with($cmd, 'RCPT TO') || str_starts_with($cmd, 'RSET') || str_starts_with($cmd, 'NOOP')) {
        $send('250 OK');
    } elseif ($cmd === 'DATA') {
        $send('354 End data with <CR><LF>.<CR><LF>');
        $inData = true;
    } elseif ($cmd === 'QUIT') {
        $send('221 Bye');
        break;
    } else {
        $send('502 Not implemented');
    }
}
file_put_contents($out, $log);
