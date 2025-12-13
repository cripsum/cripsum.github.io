<?php
// File di test per verificare l'API dei commenti
// Accedi a questo file direttamente dal browser: /api/test_comments.php?shitpost_id=1

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST API COMMENTI ===<br><br>";

// Test 1: Verifica connessione database
echo "1. Test connessione database...<br>";
try {
    require_once '../config/database.php';
    echo "✅ Connessione database OK<br><br>";
} catch (Exception $e) {
    echo "❌ Errore connessione: " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 2: Verifica tabella commenti_shitpost
echo "2. Test struttura tabella...<br>";
$result = $mysqli->query("DESCRIBE commenti_shitpost");
if ($result) {
    echo "✅ Tabella commenti_shitpost esiste<br>";
    echo "Colonne:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})<br>";
    }
    echo "<br>";
} else {
    echo "❌ Errore: " . $mysqli->error . "<br><br>";
}

// Test 3: Conta commenti
echo "3. Test conteggio commenti...<br>";
$result = $mysqli->query("SELECT COUNT(*) as total FROM commenti_shitpost");
if ($result) {
    $row = $result->fetch_assoc();
    echo "✅ Totale commenti nel database: " . $row['total'] . "<br><br>";
} else {
    echo "❌ Errore: " . $mysqli->error . "<br><br>";
}

// Test 4: Se viene passato shitpost_id, prova a recuperare i commenti
if (isset($_GET['shitpost_id'])) {
    $shitpost_id = intval($_GET['shitpost_id']);
    echo "4. Test recupero commenti per shitpost ID: $shitpost_id<br>";

    $stmt = $mysqli->prepare("
        SELECT 
            c.id,
            c.commento,
            c.data_commento,
            c.id_utente,
            u.username,
            u.profile_pic
        FROM commenti_shitpost c
        JOIN utenti u ON c.id_utente = u.id
        WHERE c.id_shitpost = ?
        ORDER BY c.data_commento DESC
    ");

    if (!$stmt) {
        echo "❌ Errore preparazione query: " . $mysqli->error . "<br>";
    } else {
        $stmt->bind_param("i", $shitpost_id);
        if (!$stmt->execute()) {
            echo "❌ Errore esecuzione query: " . $stmt->error . "<br>";
        } else {
            $result = $stmt->get_result();
            $comments = [];
            while ($row = $result->fetch_assoc()) {
                $comments[] = $row;
            }
            echo "✅ Trovati " . count($comments) . " commenti<br>";
            if (count($comments) > 0) {
                echo "<pre>";
                print_r($comments);
                echo "</pre>";
            }
        }
        $stmt->close();
    }
} else {
    echo "4. Aggiungi ?shitpost_id=X all'URL per testare il recupero commenti<br>";
}

echo "<br>=== FINE TEST ===";
$mysqli->close();
