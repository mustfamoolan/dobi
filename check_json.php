<?php
function checkJson($filename)
{
    echo "Checking $filename...\n";
    $content = file_get_contents($filename);
    if ($content === false) {
        echo "Could not read file.\n";
        return;
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON Error: " . json_last_error_msg() . "\n";

        // Try to find where it breaks
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if (empty($trimmed) || $trimmed == '{' || $trimmed == '}')
                continue;

            // Check for missing commas or quotes
            if (!preg_match('/^".*"\s*:\s*".*",?$/', $trimmed) && !preg_match('/^".*"\s*:\s*".*"$/', $trimmed)) {
                echo "Potential syntax error on line " . ($i + 1) . ": $line\n";
            }
        }
    } else {
        echo "JSON syntax is valid.\n";

        // Check for duplicates (json_decode overwrites duplicates, so we need to parse manually)
        preg_match_all('/"([^"]+)"\s*:/', $content, $matches);
        $keys = $matches[1];
        $counts = array_count_values($keys);
        $duplicates = array_filter($counts, function ($v) {
            return $v > 1; });

        if (!empty($duplicates)) {
            echo "Found exact duplicates:\n";
            foreach ($duplicates as $key => $count) {
                echo " - '$key' ($count times)\n";
            }
        }

        // Check for case-insensitive duplicates (might affect some parsers)
        $lowerKeys = array_map('strtolower', $keys);
        $lowerCounts = array_count_values($lowerKeys);
        $lowerDuplicates = array_filter($lowerCounts, function ($v) {
            return $v > 1; });

        if (!empty($lowerDuplicates)) {
            echo "Found case-insensitive duplicates:\n";
            foreach ($lowerDuplicates as $key => $count) {
                if (!isset($duplicates[$key])) { // If it wasn't an exact duplicate
                    echo " - '$key' ($count times, checking case variants)\n";
                }
            }
        }
    }
}

checkJson('d:/laravel_app/Admin/lang/ar.json');
checkJson('d:/laravel_app/Admin/lang/en.json');
