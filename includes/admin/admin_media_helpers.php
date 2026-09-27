<?php
/**
 * Immagini caricate dal pannello: in quali cartelle di img/ vanno e quando
 * si possono cancellare.
 *
 * Il pannello scrive solo nelle cartelle di ADMIN_MEDIA_FOLDERS (vedi
 * upload_media.php) e cancella solo da li'. Le immagini storiche nella
 * radice di img/ non si toccano mai: le usano anche pagine e script scritti
 * a mano, e da qui non si puo' sapere quali.
 *
 * Un file si cancella solo se nessuna riga del database lo nomina piu': cosi'
 * una foto condivisa (un prodotto duplicato, un'immagine riusata altrove)
 * resta finche' serve a qualcuno.
 */

const ADMIN_MEDIA_FOLDERS = ['negozio', 'merch', 'download', 'gacha', 'personaggi', 'esports'];

/**
 * Cartelle di audio/ in cui carica il pannello (la musica dei player del
 * team). Gli audio storici nella radice di audio/ restano fuori, come le
 * immagini nella radice di img/.
 */
const ADMIN_AUDIO_FOLDERS = ['esports'];

/**
 * Da un valore salvato nel database (/img/merch/x.jpg, img/merch/x.jpg o
 * personaggi/x.jpg come salvano i personaggi) al percorso dentro img/.
 * Null se il file non e' in una cartella del pannello: link esterni,
 * immagini storiche, percorsi fuori da img/.
 */
function admin_media_relative(?string $value): ?string
{
    $value = trim(str_replace('\\', '/', (string)$value));

    if ($value === '' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $value) || str_starts_with($value, '//')) {
        return null;
    }

    if (str_starts_with($value, '/img/')) {
        $value = substr($value, 5);
    } elseif (str_starts_with($value, 'img/')) {
        $value = substr($value, 4);
    } elseif (str_starts_with($value, '/')) {
        return null;
    }

    $folders = implode('|', ADMIN_MEDIA_FOLDERS);
    $pattern = '~^(?:' . $folders . ')(?:/[a-z0-9](?:[a-z0-9-]{0,58}[a-z0-9])?)?/[A-Za-z0-9_.-]{1,160}\.(?:jpe?g|png|gif|webp)$~i';

    return preg_match($pattern, $value) && !str_contains($value, '..') ? $value : null;
}

/**
 * Da un valore salvato nel database (/audio/esports/x.mp3) al percorso
 * dentro audio/. Null per tutto il resto: link esterni, audio storici.
 */
function admin_media_audio_relative(?string $value): ?string
{
    $value = trim(str_replace('\\', '/', rawurldecode((string)$value)));

    if (!str_starts_with($value, '/audio/')) {
        return null;
    }

    $value = substr($value, 7);
    $folders = implode('|', ADMIN_AUDIO_FOLDERS);
    $pattern = '~^(?:' . $folders . ')/[A-Za-z0-9_.-]{1,160}\.(?:mp3|ogg|m4a|aac|wav|webm)$~i';

    return preg_match($pattern, $value) && !str_contains($value, '..') ? $value : null;
}

/**
 * Le colonne che possono contenere un'immagine del pannello. Ci sono anche
 * tabelle dove il pannello non carica (achievement, slide, badge, banner):
 * se qualcuno ci ha incollato un percorso, il file resta.
 */
function admin_media_reference_columns(mysqli $mysqli): array
{
    static $columns = null;
    if ($columns !== null) {
        return $columns;
    }

    $candidates = [
        'shop_prodotti' => ['immagine', 'galleria'],
        'shop_vetrine' => ['logo', 'copertina'],
        'download_items' => ['immagine'],
        'godos_shop_items' => ['image_url'],
        'personaggi' => ['img_url', 'immagine', 'image_url', 'img'],
        'achievement' => ['img_url', 'icona', 'icon_url', 'image_url'],
        'home_slides' => ['media'],
        'custom_badges' => ['image_url'],
        'banner_eventi' => ['banner_img_url', 'img_url', 'image_url', 'immagine'],
        'gacha_banner' => ['banner_img_url', 'thumb_url', 'arte_url'],
        'esports_team' => ['logo', 'copertina'],
        'esports_giocatori' => ['foto', 'sfondo', 'musica_cover', 'musica_audio'],
    ];

    $columns = [];
    foreach ($candidates as $table => $names) {
        if (!admin_table_exists($mysqli, $table)) {
            continue;
        }
        foreach ($names as $name) {
            if (admin_column_exists($mysqli, $table, $name)) {
                $columns[] = [$table, $name];
            }
        }
    }

    return $columns;
}

/**
 * Qualche riga usa ancora il file? Si cerca il percorso dentro img/ come
 * sottostringa, cosi' vale per ogni forma in cui e' salvato (con o senza
 * /img/ davanti, dentro il JSON della galleria). Se una query fallisce la
 * risposta e' "si'": nel dubbio non si cancella.
 */
function admin_media_in_use(mysqli $mysqli, string $relative): bool
{
    $like = '%' . addcslashes($relative, '%_\\') . '%';

    foreach (admin_media_reference_columns($mysqli) as [$table, $column]) {
        $stmt = $mysqli->prepare("SELECT 1 FROM `$table` WHERE `$column` LIKE ? LIMIT 1");
        if (!$stmt) {
            return true;
        }
        $stmt->bind_param('s', $like);
        if (!$stmt->execute()) {
            $stmt->close();
            return true;
        }
        $found = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        if ($found) {
            return true;
        }
    }

    return false;
}

/**
 * Cancella le immagini di $values che non servono piu' a nessuno. Si chiama
 * DOPO aver salvato o eliminato, con i valori che il record aveva prima:
 * quelle ancora in uso restano da sole. Accetta anche il JSON della galleria.
 * Non lancia mai: una pulizia fallita non deve far fallire il salvataggio.
 *
 * @return string[] i percorsi cancellati, relativi a img/
 */
function admin_media_cleanup(mysqli $mysqli, iterable $values, ?int $adminId = null): array
{
    $deleted = [];

    try {
        $paths = [];
        $audioPaths = [];
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $list = str_starts_with(ltrim($value), '[') ? json_decode($value, true) : [$value];
            foreach (is_array($list) ? $list : [] as $item) {
                if (!is_string($item)) {
                    continue;
                }
                $relative = admin_media_relative($item);
                if ($relative !== null) {
                    $paths[$relative] = true;
                    continue;
                }
                $audio = admin_media_audio_relative($item);
                if ($audio !== null) {
                    $audioPaths[$audio] = true;
                }
            }
        }

        $root = realpath(__DIR__ . '/../../img');
        $rootPrefix = $root !== false ? rtrim($root, '/\\') . DIRECTORY_SEPARATOR : '';

        foreach ($root !== false ? array_keys($paths) : [] as $relative) {
            $file = realpath($root . '/' . $relative);
            if ($file === false || !is_file($file) || !str_starts_with($file, $rootPrefix)) {
                continue;
            }
            if (admin_media_in_use($mysqli, $relative)) {
                continue;
            }
            if (!@unlink($file)) {
                continue;
            }
            $deleted[] = $relative;

            // La cartella di una collezione rimasta vuota se ne va con
            // l'ultima foto; le cartelle di primo livello restano.
            $dir = dirname($file);
            if (dirname($dir) !== $root && strcasecmp(dirname(dirname($dir)), $root) === 0 && count(scandir($dir) ?: []) === 2) {
                @rmdir($dir);
            }
        }

        $audioRoot = $audioPaths ? realpath(__DIR__ . '/../../audio') : false;
        $audioPrefix = $audioRoot !== false ? rtrim($audioRoot, '/\\') . DIRECTORY_SEPARATOR : '';

        foreach ($audioRoot !== false ? array_keys($audioPaths) : [] as $relative) {
            $file = realpath($audioRoot . '/' . $relative);
            if ($file === false || !is_file($file) || !str_starts_with($file, $audioPrefix)) {
                continue;
            }
            // Nel database l'audio e' salvato con /audio/ davanti: si cerca
            // cosi', e non puo' confondersi con un'immagine.
            if (admin_media_in_use($mysqli, 'audio/' . $relative)) {
                continue;
            }
            if (@unlink($file)) {
                $deleted[] = 'audio/' . $relative;
            }
        }

        if ($deleted && $adminId) {
            admin_log($mysqli, $adminId, 'delete_media', null, ['files' => $deleted]);
        }
    } catch (Throwable $e) {
        error_log('[admin media] ' . $e->getMessage());
    }

    return $deleted;
}

/** L'immagine salvata di un personaggio, per ripulirla dopo una modifica. */
function admin_character_image(mysqli $mysqli, int $id): ?string
{
    $column = admin_character_columns($mysqli)['image'] ?? null;
    if (!$column) {
        return null;
    }

    $stmt = $mysqli->prepare('SELECT ' . admin_qcol($column) . ' FROM personaggi WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();

    return $row && $row[0] !== null ? (string)$row[0] : null;
}
