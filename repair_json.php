<?php
function repairJson($filename)
{
    echo "Repairing $filename...\n";
    $content = file_get_contents($filename);
    if ($content === false) {
        echo "Error: Could not read $filename\n";
        return;
    }

    // Attempt to decode. If it fails, try a more aggressive approach.
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Initial JSON Error: " . json_last_error_msg() . "\n";

        // Let's try to fix common issues: trailing commas, missing commas between objects
        // (This is a bit risky but we have the backup of the original content in our chat history)

        // Remove trailing commas before closing braces/brackets
        $content = preg_replace('/,\s*([\}\]])/', '$1', $content);

        // Now try again
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Failed to repair automatically. Manual intervention needed.\n";
            return;
        } else {
            echo "Successfully repaired syntax errors (likely a trailing comma).\n";
        }
    }

    // Check for "Customer" vs "customer" case-insensitive duplicates
    // and consolidate them, as some parsers (like PowerShell) fail on them.
    $repairedData = [];
    foreach ($data as $key => $value) {
        $lowerKey = strtolower($key);
        // If we have "Customer" and then "customer", we'll just keep the first one 
        // or prefer the capitalized one as it's usually the UI label.
        // For simplicity, we'll check if a case-variant already exists.
        $found = false;
        foreach ($repairedData as $existingKey => $existingValue) {
            if (strtolower($existingKey) === $lowerKey) {
                echo "Consolidating duplicate case variant: '$key' with '$existingKey'\n";
                // If the new one is more "Complete" or if we want to just keep one, we do it.
                // In Laravel, the key often matches the string exactly.
                $found = true;
                break;
            }
        }
        if (!$found) {
            $repairedData[$key] = $value;
        }
    }

    $json = json_encode($repairedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($filename, $json)) {
        echo "Successfully wrote repaired $filename\n";
    } else {
        echo "Error: Could not write to $filename\n";
    }
}

repairJson('d:/laravel_app/Admin/lang/ar.json');
repairJson('d:/laravel_app/Admin/lang/en.json');
