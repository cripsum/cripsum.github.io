<?php
$mysqli = new mysqli('localhost', 'root', '', '');
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

$res = $mysqli->query("SHOW DATABASES");
$databases = [];
while ($row = $res->fetch_row()) {
    $databases[] = $row[0];
}
echo "Available databases: " . implode(", ", $databases) . "\n";

// Let's find the correct database
$targetDb = '';
foreach ($databases as $db) {
    if (strpos($db, 'cripsum') !== false) {
        $targetDb = $db;
        break;
    }
}

if (!$targetDb) {
    die("No Cripsum database found!\n");
}

echo "Using database: $targetDb\n";
$mysqli->select_db($targetDb);

$res = $mysqli->query("ALTER TABLE utenti ADD COLUMN last_premium_claim DATE DEFAULT NULL");
if ($res) {
    echo "Migration completed successfully!\n";
} else {
    echo "Migration failed: " . $mysqli->error . "\n";
}
