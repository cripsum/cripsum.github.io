<?php
header('Content-Type: text/plain; charset=utf-8');
$file = __DIR__ . '/../../includes/game_helpers.php';
if (file_exists($file)) {
    $lines = file($file);
    for ($i = 190; $i <= 230; $i++) {
        if (isset($lines[$i - 1])) {
            echo "$i: " . $lines[$i - 1];
        }
    }
} else {
    echo "File not found: $file\n";
}
