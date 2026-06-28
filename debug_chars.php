<?php
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "--- PERSONAGGI CON 'ADO' NEL NOME ---\n";
$q1 = $mysqli->query("SELECT id, nome, ruolo, rarita FROM personaggi WHERE nome LIKE '%ado%'");
while ($r = $q1->fetch_assoc()) {
    print_r($r);
}

echo "\n--- TUTTI I PERSONAGGI HEALER ---\n";
$q2 = $mysqli->query("SELECT id, nome, ruolo, rarita FROM personaggi WHERE ruolo LIKE '%healer%' OR ruolo LIKE '%Healer%'");
while ($r = $q2->fetch_assoc()) {
    print_r($r);
}

echo "\n--- CONTEGGIO TOTALE RUOLI ---\n";
$q3 = $mysqli->query("SELECT ruolo, COUNT(*) as qty FROM personaggi GROUP BY ruolo");
while ($r = $q3->fetch_assoc()) {
    print_r($r);
}
unlink(__FILE__); // Autodistruzione dopo l'esecuzione per sicurezza
