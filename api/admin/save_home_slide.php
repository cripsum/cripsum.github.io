<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Crea o aggiorna una slide della homepage.
 *
 * Stesso endpoint per le due cose: con `id` modifica, senza crea. I testi
 * inglesi possono restare vuoti — la homepage in quel caso mostra l'italiano,
 * cosi' una slide nuova si vede subito in entrambe le lingue.
 */

/**
 * Destinazione accettabile per un bottone della homepage.
 *
 * Si accettano i percorsi interni e gli indirizzi http(s). Tutto il resto —
 * `javascript:`, `data:` e compagnia — viene rifiutato: questo campo finisce
 * dritto in un href sulla pagina piu' visitata del sito.
 */
function home_slide_url(string $value, string $label, bool $required): string
{
    $value = trim($value);

    if ($value === '') {
        if ($required) {
            admin_fail($label . ': campo obbligatorio.');
        }
        return '';
    }

    if (mb_strlen($value) > 255) {
        admin_fail($label . ': indirizzo troppo lungo (max 255).');
    }

    if (str_starts_with($value, '/')) {
        // Niente `//host`: sembra un percorso interno ma porta fuori dal sito.
        if (str_starts_with($value, '//')) {
            admin_fail($label . ': usa un percorso interno (/it/...) o un indirizzo https completo.');
        }
        return $value;
    }

    if (preg_match('~^https?://~i', $value)) {
        return $value;
    }

    admin_fail($label . ': usa un percorso interno (/it/...) o un indirizzo https completo.');
}

function home_slide_text(array $input, string $key, int $max, bool $required = false): ?string
{
    $value = trim((string)($input[$key] ?? ''));

    if ($value === '') {
        if ($required) {
            admin_fail('Il campo "' . $key . '" è obbligatorio.');
        }
        return null;
    }

    if (mb_strlen($value) > $max) {
        admin_fail('Il campo "' . $key . '" supera i ' . $max . ' caratteri.');
    }

    return $value;
}

try {
    if (!admin_table_exists($mysqli, 'home_slides')) {
        admin_fail('Tabella home_slides mancante: applica la migrazione.', 500);
    }

    $input = admin_input();
    $id = (int)($input['id'] ?? 0);

    $titolo = home_slide_text($input, 'titolo', 120, true);
    $titoloEn = home_slide_text($input, 'titolo_en', 120);
    $descrizione = home_slide_text($input, 'descrizione', 400);
    $descrizioneEn = home_slide_text($input, 'descrizione_en', 400);
    $bottone = home_slide_text($input, 'testo_bottone', 60);
    $bottoneEn = home_slide_text($input, 'testo_bottone_en', 60);

    $media = home_slide_url((string)($input['media'] ?? ''), 'Immagine', false);
    $link = home_slide_url((string)($input['link'] ?? ''), 'Link', false);

    $attiva = !empty($input['attiva']) && $input['attiva'] !== '0' ? 1 : 0;

    // Una slide accesa senza immagine lascerebbe un buco al posto della figura:
    // la si puo' salvare, ma spenta.
    if ($attiva === 1 && $media === '') {
        admin_fail('Per accendere una slide serve l\'immagine.');
    }

    if ($id > 0) {
        $stmt = $mysqli->prepare(
            'UPDATE home_slides
             SET media = ?, link = ?, titolo = ?, titolo_en = ?, descrizione = ?,
                 descrizione_en = ?, testo_bottone = ?, testo_bottone_en = ?, attiva = ?
             WHERE id = ? LIMIT 1'
        );

        if (!$stmt) {
            admin_fail('Query di aggiornamento non valida.', 500);
        }

        $stmt->bind_param(
            'ssssssssii',
            $media, $link, $titolo, $titoloEn, $descrizione,
            $descrizioneEn, $bottone, $bottoneEn, $attiva, $id
        );

        if (!$stmt->execute()) {
            $stmt->close();
            admin_fail('Non sono riuscito a salvare la slide.', 500);
        }

        $stmt->close();
        admin_log($mysqli, (int)$adminUser['id'], 'update_home_slide', null, ['slide_id' => $id, 'titolo' => $titolo]);
        admin_ok(['message' => 'Slide salvata.', 'id' => $id]);
    }

    // Le nuove vanno in fondo: chi la crea la sposta poi dove vuole.
    $posizione = (int)($mysqli->query('SELECT COALESCE(MAX(posizione), 0) + 10 AS p FROM home_slides')
        ?->fetch_assoc()['p'] ?? 10);

    $stmt = $mysqli->prepare(
        'INSERT INTO home_slides
            (posizione, attiva, media, link, titolo, titolo_en, descrizione,
             descrizione_en, testo_bottone, testo_bottone_en, creata_da)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        admin_fail('Query di creazione non valida.', 500);
    }

    $adminId = (int)$adminUser['id'];
    $stmt->bind_param(
        'iissssssssi',
        $posizione, $attiva, $media, $link, $titolo, $titoloEn,
        $descrizione, $descrizioneEn, $bottone, $bottoneEn, $adminId
    );

    if (!$stmt->execute()) {
        $stmt->close();
        admin_fail('Non sono riuscito a creare la slide.', 500);
    }

    $newId = $stmt->insert_id;
    $stmt->close();

    admin_log($mysqli, $adminId, 'create_home_slide', null, ['slide_id' => $newId, 'titolo' => $titolo]);
    admin_ok(['message' => 'Slide creata.', 'id' => $newId]);
} catch (Throwable $e) {
    admin_fail('Errore salvataggio slide.', 500);
}
