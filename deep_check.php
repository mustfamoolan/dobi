<?php
$path = 'd:/laravel_app/Admin/lang/ar.json';
$content = file_get_contents($path);
if ($content === false) {
    die("Could not read file\n");
}

echo "File size: " . strlen($content) . " bytes\n";
$data = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Error: " . json_last_error_msg() . "\n";
    echo "Error code: " . json_last_error() . "\n";

    // Check for BOM
    $bom = pack('H*', 'EFBBBF');
    if (substr($content, 0, 3) === $bom) {
        echo "Found UTF-8 BOM at the beginning of the file!\n";
    }

    // Find the error position more accurately if possible
    // (PHP 7.3+ has json_decode error position if we use some tricks, but let's do manual)

    // Check each line
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if ($trimmed === '{' || $trimmed === '}' || $trimmed === '')
            continue;

        // This is a very basic check for "key": "value", or "key": "value"
        if (!preg_match('/^"([^"\\\\]|\\\\.)*"\s*:\s*"([^"\\\\]|\\\\.)*",?$/u', $trimmed)) {
            echo "Possible malformed line " . ($i + 1) . ": [" . $line . "]\n";
        }
    }
} else {
    echo "PHP json_decode says it IS VALID.\n";
}
