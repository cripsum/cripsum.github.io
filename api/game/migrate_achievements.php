<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$uid = $_SESSION['user_id'] ?? 0;
if ($uid <= 0 && php_sapi_name() !== 'cli') {
    die("Errore: Devi essere loggato per eseguire la migrazione.");
}

echo "Avvio migrazione per i nuovi achievement di potenziamento...\n";

$new_achievements = [
    [
        'nome' => 'Massimo Splendore I',
        'nome_en' => 'Max Splendor I',
        'descrizione' => 'Porta 1 personaggio al livello MAX',
        'descrizione_en' => 'Upgrade 1 character to MAX level',
        'img_url' => '/img/achievements/max_lvl_1.png',
        'punti' => 1000
    ],
    [
        'nome' => 'Massimo Splendore V',
        'nome_en' => 'Max Splendor V',
        'descrizione' => 'Porta 5 personaggi al livello MAX',
        'descrizione_en' => 'Upgrade 5 characters to MAX level',
        'img_url' => '/img/achievements/max_lvl_5.png',
        'punti' => 5000
    ],
    [
        'nome' => 'Massimo Splendore X',
        'nome_en' => 'Max Splendor X',
        'descrizione' => 'Porta 10 personaggi al livello MAX',
        'descrizione_en' => 'Upgrade 10 characters to MAX level',
        'img_url' => '/img/achievements/max_lvl_10.png',
        'punti' => 10000
    ],
    [
        'nome' => 'Esercito Dorato',
        'nome_en' => 'Golden Army',
        'descrizione' => 'Porta 50 personaggi al livello MAX',
        'descrizione_en' => 'Upgrade 50 characters to MAX level',
        'img_url' => '/img/achievements/max_lvl_50.png',
        'punti' => 50000
    ]
];

foreach ($new_achievements as $ach) {
    // Controlla se esiste già
    $stmt = $mysqli->prepare("SELECT id FROM achievement WHERE nome = ? OR nome_en = ?");
    $stmt->bind_param("ss", $ach['nome'], $ach['nome_en']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo "L'achievement '{$ach['nome']}' esiste già con ID {$row['id']}.\n";
    } else {
        $stmt_insert = $mysqli->prepare("INSERT INTO achievement (nome, nome_en, descrizione, descrizione_en, img_url, punti) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("sssssi", $ach['nome'], $ach['nome_en'], $ach['descrizione'], $ach['descrizione_en'], $ach['img_url'], $ach['punti']);
        if ($stmt_insert->execute()) {
            echo "Inserito con successo: '{$ach['nome']}' con ID " . $mysqli->insert_id . "\n";
        } else {
            echo "Errore durante l'inserimento di '{$ach['nome']}': " . $mysqli->error . "\n";
        }
        $stmt_insert->close();
    }
    $stmt->close();
}

echo "Migrazione completata con successo.\n";
