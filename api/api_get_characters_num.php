<?php
/**
 * Quanti personaggi ci sono nel catalogo, senza quelli nascosti (non ancora
 * usciti): e' il totale su cui si calcola il completamento.
 */
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gacha/banners.php';

$total = 0;
foreach (gacha_characters($mysqli) as $c) {
    if ($c['catalogo'] !== 'nascosto') {
        $total++;
    }
}

header('Content-Type: application/json');
echo json_encode($total);
