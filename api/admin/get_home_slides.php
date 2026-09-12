<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Slide della homepage, tutte: anche quelle spente, che nel pannello servono
 * proprio per essere accese.
 */
try {
    if (!admin_table_exists($mysqli, 'home_slides')) {
        admin_ok([
            'slides' => [],
            'available' => false,
            'message' => 'Applica la migrazione 2026_09_12_home_slides.sql.',
        ]);
    }

    $result = $mysqli->query(
        'SELECT id, posizione, attiva, media, link,
                titolo, titolo_en, descrizione, descrizione_en,
                testo_bottone, testo_bottone_en, updated_at
         FROM home_slides
         ORDER BY posizione ASC, id ASC'
    );

    if (!$result) {
        admin_fail('Non riesco a leggere le slide.', 500);
    }

    $slides = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['posizione'] = (int)$row['posizione'];
        $row['attiva'] = (int)$row['attiva'] === 1;
        $slides[] = $row;
    }
    $result->free();

    admin_ok(['slides' => $slides, 'available' => true]);
} catch (Throwable $e) {
    admin_fail('Errore lettura slide.', 500);
}
