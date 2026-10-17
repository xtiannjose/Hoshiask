<?php

/*
 * A stand-in for the ClamAV daemon, for trying upload scanning on a computer without ClamAV.
 * It is NOT a virus scanner: it only answers PING and INSTREAM, and calls a file infected when it
 * contains HOSHIASK-TEST-VIRUS-SIGNATURE or the standard EICAR test file.
 *
 *   php scripts/fake-clamd.php 3310                     (listen on 127.0.0.1:3310)
 *   php scripts/fake-clamd.php /tmp/fake-clamd.sock     (listen on a Unix socket)
 *
 * Then set HOSHIASK_UPLOAD_SCAN=clamav and HOSHIASK_CLAMAV_PORT (or HOSHIASK_CLAMAV_SOCKET) in .env.
 */

if (PHP_SAPI !== 'cli' || ! isset($argv[1])) {
    fwrite(STDERR, "Usage: php scripts/fake-clamd.php <port|socket-path>\n");
    exit(1);
}

$address = ctype_digit($argv[1]) ? 'tcp://127.0.0.1:'.$argv[1] : 'unix://'.$argv[1];
$server = stream_socket_server($address, $errorCode, $errorMessage);
if ($server === false) {
    fwrite(STDERR, "Could not listen on {$address}: {$errorMessage}\n");
    exit(1);
}

// Built from pieces so the file itself never looks like the test virus to antivirus software.
$markers = ['HOSHIASK-TEST-VIRUS-SIGNATURE', 'X5O!P%@AP[4'.chr(92).'PZX54(P^)7CC)7}'.'$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!'.'$H+H*'];
$readExactly = static function ($client, int $length): ?string {
    $data = '';
    while (strlen($data) < $length) {
        $part = fread($client, $length - strlen($data));
        if ($part === false || $part === '') {
            return null;
        }
        $data .= $part;
    }

    return $data;
};

echo "Fake clamd listening on {$address}. Press Ctrl+C to stop.\n";

while (true) {
    $client = @stream_socket_accept($server, -1);
    if ($client === false) {
        continue;
    }

    $command = '';
    while (! str_ends_with($command, "\0") && strlen($command) < 64 && ($byte = fread($client, 1)) !== false && $byte !== '') {
        $command .= $byte;
    }

    if ($command === "zPING\0") {
        fwrite($client, "PONG\0");
    } elseif ($command === "zINSTREAM\0") {
        $data = '';
        while (($header = $readExactly($client, 4)) !== null && ($length = unpack('N', $header)[1]) > 0) {
            $data .= $readExactly($client, $length) ?? '';
        }
        $infected = array_filter($markers, static fn (string $marker): bool => str_contains($data, $marker)) !== [];
        fwrite($client, $infected ? "stream: HOSHIASK-TEST-VIRUS-SIGNATURE FOUND\0" : "stream: OK\0");
        echo date('H:i:s').' scanned '.strlen($data).' bytes: '.($infected ? 'FOUND' : 'OK')."\n";
    } else {
        fwrite($client, "UNKNOWN COMMAND\0");
    }

    fclose($client);
}
