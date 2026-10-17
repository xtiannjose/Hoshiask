<?php

/*
 * Decrypts a Hoshiask backup (hoshiask-*.tar.enc) on any computer that has PHP with the openssl extension.
 * No Laravel, no composer: for the day the server is gone.
 *
 *   php hoshiask-backup-decrypt.php hoshiask-20261008-024000.tar.enc hoshiask.tar "base64:...key..."
 *
 * Then unpack the result with any tar program (tar -xf hoshiask.tar). The format is described in
 * app/Services/Backup/BackupEncryption.php.
 */

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php hoshiask-backup-decrypt.php <encrypted-file> <output-file> <key>\n");
    exit(1);
}

[, $inputPath, $outputPath, $keyText] = $argv;

$fail = static function (string $message) use (&$output, $outputPath): never {
    if (isset($output) && is_resource($output)) {
        fclose($output);
    }
    @unlink($outputPath);
    fwrite(STDERR, "FAILED: {$message}\n");
    exit(1);
};

$readExactly = static function ($stream, int $length): string {
    $data = '';
    while (strlen($data) < $length && ! feof($stream)) {
        $part = fread($stream, $length - strlen($data));
        if ($part === false || $part === '') {
            break;
        }
        $data .= $part;
    }

    return $data;
};

$key = str_starts_with($keyText, 'base64:') ? base64_decode(substr($keyText, 7), true) : false;
if (! is_string($key) || strlen($key) !== 32) {
    $fail('The key must look like base64:... (32 bytes).');
}

$input = @fopen($inputPath, 'rb') ?: $fail("Cannot open {$inputPath}.");
if ($readExactly($input, 5) !== "HBK1\n") {
    $fail('This is not an encrypted Hoshiask backup.');
}
$output = @fopen($outputPath, 'wb') ?: $fail("Cannot write {$outputPath}.");

for ($index = 0; ; $index++) {
    $header = $readExactly($input, 33);
    if (strlen($header) !== 33) {
        $fail('The file is cut short: its end is missing.');
    }
    $flag = ord($header[0]);
    $length = unpack('N', substr($header, 1, 4))[1];
    if ($flag > 1 || $length > 1048576) {
        $fail("The file is damaged at piece {$index}.");
    }
    $ciphertext = $readExactly($input, $length);
    if (strlen($ciphertext) !== $length) {
        $fail('The file is cut short: its end is missing.');
    }
    $aad = 'HBK1'.pack('J', $index).($flag === 1 ? "\x01" : "\x00");
    $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($header, 5, 12), substr($header, 17, 16), $aad);
    if ($plain === false) {
        $fail("Cannot decrypt piece {$index}: the key is wrong, or the file was changed or damaged.");
    }
    fwrite($output, $plain);
    if ($flag === 1) {
        break;
    }
}

if (fread($input, 1) !== '') {
    $fail('The file has unexpected bytes after its end.');
}

fclose($output);
echo "Decrypted to {$outputPath}. Unpack it with: tar -xf {$outputPath}\n";
