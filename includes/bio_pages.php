<?php
declare(strict_types=1);

/**
 * Le pagine di bio.cripsum.com: l'ingresso, «La tua pagina» ed Esplora
 * (bio/index.php, bio/esplora.php). Le tavole approvate sono in
 * proposte-home/bio-tavola-1..4.png, fuori dal repo.
 *
 * E' lo stesso sito: navbar e footer veri, tema del sito, stesse sessioni
 * (l'accesso passa da includes/bio_sso.php). Qui stanno le scritte nelle due
 * lingue, il guscio delle pagine e le letture dal database. Lo stile sta in
 * assets/bio/bio.css, i comportamenti in assets/bio/bio.js.
 */

require_once __DIR__ . '/bio_sso.php';
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/profile_helpers.php';

/**
 * La lingua delle pagine di bio. Non c'e' /it/ o /en/ nell'indirizzo: vale
 * ?lang= (il cambio lingua della navbar), poi quella ricordata dal sito.
 */
function bio_lang(): string
{
    $asked = (string)($_GET['lang'] ?? '');
    if (in_array($asked, ['it', 'en'], true)) {
        if (($_COOKIE['cripsum_lang'] ?? '') !== $asked && !headers_sent()) {
            setcookie('cripsum_lang', $asked, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => !empty(session_get_cookie_params()['secure']),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['cripsum_lang'] = $asked;

        return $asked;
    }

    return cripsum_preferred_lang();
}

function bio_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Le scritte delle pagine di bio. */
function bio_t(string $key, string $lang): string
{
    static $dict = [
        'it' => [
            'meta_title' => 'bio.cripsum.com — il tuo link in bio',
            'meta_desc' => 'Una pagina con la tua musica, i tuoi colori e tutti i tuoi link, a un indirizzo corto da mettere ovunque.',
            'hero_1' => 'Il tuo link in bio,',
            'hero_2' => 'fatto come vuoi tu.',
            'hero_lead' => "Una pagina con la tua musica, i tuoi colori e tutti i tuoi link, a un indirizzo corto da mettere ovunque. Basta l'account di Cripsum.",
            'claim_label' => 'Il nome che vuoi',
            'claim_ph' => 'tuonome',
            'claim_btn' => 'Prendilo',
            'name_free' => '%s è libero',
            'name_taken' => '%s è già preso.',
            'name_taken_link' => 'Guarda la sua pagina',
            'name_reserved' => "%s è una parola del sito, scegline un'altra.",
            'name_invalid' => 'Da 3 a 20 caratteri: lettere, numeri e _.',
            'name_yours' => '%s è già tuo.',
            'name_checking' => 'Controllo…',
            'have_account' => 'Hai già un account?',
            'sign_in' => 'Accedi',
            'showcase' => 'In vetrina',
            'explore_all' => 'Esplora tutti',
            'features' => 'Cosa ci puoi mettere',
            'f_bg' => 'Sfondo e colori', 'f_bg_t' => "Un'immagine, una gif o un video, e i colori che vuoi tu.",
            'f_music' => 'La tua canzone', 'f_music_t' => 'Parte quando qualcuno apre la tua pagina.',
            'f_fx' => 'Effetti', 'f_fx_t' => 'Il cursore, il nome animato, la scheda che si inclina.',
            'f_links' => 'Link e social', 'f_links_t' => "Tutti i tuoi posti, nell'ordine che vuoi.",
            'f_discord' => 'Discord', 'f_discord_t' => 'Stato, cosa stai ascoltando e il tuo server, sempre aggiornati.',
            'f_badges' => 'Badge e personaggi', 'f_badges_t' => 'Gli achievement e i pull migliori che hai fatto su Cripsum.',
            'your_page' => 'La tua pagina',
            'copy_link' => 'Copia link',
            'copied' => 'Link copiato.',
            'qr' => 'QR',
            'qr_title' => 'Il QR della tua pagina',
            'close' => 'Chiudi',
            'open' => 'Apri',
            'edit' => 'Modifica',
            'welcome' => 'bio.cripsum.com/%s adesso è tuo.',
            'claim_also' => 'Vuoi anche bio.cripsum.com/%s?',
            'claim_also_t' => "L'hai scelto prima di entrare ed è ancora libero: diventa il tuo link breve.",
            'claim_done' => 'Fatto: bio.cripsum.com/%s è tuo.',
            'who_sees' => 'Chi può vederla',
            'vis_public' => 'Tutti', 'vis_logged_in' => 'Registrati', 'vis_friends' => 'Amici', 'vis_private' => 'Solo io',
            'vis_now_public' => 'Adesso la vede chiunque abbia il link, anche senza account.',
            'vis_now_logged_in' => 'Adesso la vede solo chi ha un account su Cripsum.',
            'vis_now_friends' => 'Adesso la vedono solo i tuoi amici.',
            'vis_now_private' => 'Adesso la vedi solo tu.',
            'explore_switch' => 'Mostrami in Esplora',
            'explore_switch_t' => "La tua pagina compare nell'elenco dei profili della community.",
            'explore_switch_off' => 'Esplora mostra solo le pagine che vedono tutti.',
            'saved' => 'Salvato.',
            'save_failed' => 'Non sono riuscito a salvare. Riprova.',
            'todo' => 'Da completare',
            'todo_of' => '%d di %d',
            'todo_address' => 'Scegli il tuo indirizzo',
            'todo_photo' => 'Metti una foto profilo',
            'todo_bio' => 'Scrivi una bio',
            'todo_links' => 'Aggiungi un social o un link',
            'todo_bg' => 'Scegli uno sfondo',
            'todo_go' => 'Fai',
            'areas' => 'Modifica',
            'a_profile' => 'Profilo', 'a_profile_t' => 'Foto, nome, bio, tag e social.',
            'a_appearance' => 'Aspetto', 'a_appearance_t' => 'Tema, colori, layout e sfondo.',
            'a_effects' => 'Effetti', 'a_effects_t' => 'Nome, anello della foto, cursore.',
            'a_music' => 'Musica', 'a_music_t' => 'La canzone che parte quando ti aprono.',
            'a_sections' => 'Sezioni', 'a_sections_t' => 'Link, progetti, preferiti, in ordine.',
            'a_settings' => 'Impostazioni', 'a_settings_t' => 'Indirizzo, Discord, scheda del browser.',
            'preview' => 'Anteprima della tua pagina',
            'views' => 'Visite',
            'last_edit' => 'Ultima modifica',
            'today' => 'oggi',
            'yesterday' => 'ieri',
            'days_ago' => 'giorni fa',
            'never' => 'mai',
            'requests_1' => "1 richiesta d'amicizia",
            'requests_n' => "%d richieste d'amicizia",
            'requests_t' => 'Rispondi su Cripsum',
            'explore_row' => 'Esplora',
            'explore_row_t' => 'Le pagine della community',
            'premium' => 'Premium',
            'premium_t' => 'Più sezioni, effetti e caratteri',
            'account_note' => 'Password, email e dispositivi stanno nelle %s.',
            'account_link' => 'Impostazioni di Cripsum',
            'explore' => 'Esplora',
            'explore_lead' => 'Le pagine della community di Cripsum.',
            'search_ph' => 'Cerca un nome…',
            'search_label' => 'Cerca un nome',
            'tab_vetrina' => 'In vetrina', 'tab_nuove' => 'Nuove', 'tab_viste' => 'Più viste', 'tab_amici' => 'Amici',
            'more' => 'Mostra altre',
            'hide_note' => 'Non vuoi comparire qui? Lo togli da %s.',
            'empty_vetrina' => 'La vetrina è vuota, per ora.',
            'empty_vetrina_staff' => 'Metti una pagina in vetrina con la stellina sulla sua scheda, in «Nuove».',
            'empty_amici' => 'I tuoi amici non hanno ancora una pagina che si vede qui.',
            'empty_search' => 'Nessuna pagina con questo nome.',
            'empty_all' => 'Ancora nessuna pagina da mostrare.',
            'feature_on' => 'Metti in vetrina',
            'feature_off' => 'Togli dalla vetrina',
            'from_community' => 'Dalla community',
            'err_login' => 'Devi accedere per farlo.',
            'err_csrf' => 'Sessione scaduta. Ricarica la pagina.',
            'err_slow' => 'Troppe richieste: aspetta un attimo.',
            'err_generic' => 'Qualcosa non è andato. Riprova.',
            'err_taken' => 'Quel nome non è più libero.',
            'install' => 'Per Esplora e la vetrina manca un passo nel database: migrations/2026_10_18_bio_esplora.sql.',
        ],
        'en' => [
            'meta_title' => 'bio.cripsum.com — your link in bio',
            'meta_desc' => 'A page with your music, your colours and all your links, at a short address you can put anywhere.',
            'hero_1' => 'Your link in bio,',
            'hero_2' => 'made your way.',
            'hero_lead' => 'A page with your music, your colours and all your links, at a short address you can put anywhere. All you need is a Cripsum account.',
            'claim_label' => 'The name you want',
            'claim_ph' => 'yourname',
            'claim_btn' => 'Take it',
            'name_free' => '%s is free',
            'name_taken' => '%s is already taken.',
            'name_taken_link' => 'See their page',
            'name_reserved' => '%s is a word the site uses, pick another one.',
            'name_invalid' => '3 to 20 characters: letters, numbers and _.',
            'name_yours' => '%s is already yours.',
            'name_checking' => 'Checking…',
            'have_account' => 'Already have an account?',
            'sign_in' => 'Sign in',
            'showcase' => 'Featured',
            'explore_all' => 'Explore all',
            'features' => 'What you can add',
            'f_bg' => 'Background and colours', 'f_bg_t' => 'An image, a gif or a video, and the colours you like.',
            'f_music' => 'Your song', 'f_music_t' => 'It plays when someone opens your page.',
            'f_fx' => 'Effects', 'f_fx_t' => 'The cursor, an animated name, a card that tilts.',
            'f_links' => 'Links and socials', 'f_links_t' => 'All your places, in the order you want.',
            'f_discord' => 'Discord', 'f_discord_t' => 'Status, what you are listening to and your server, always up to date.',
            'f_badges' => 'Badges and characters', 'f_badges_t' => 'Your achievements and best pulls on Cripsum.',
            'your_page' => 'Your page',
            'copy_link' => 'Copy link',
            'copied' => 'Link copied.',
            'qr' => 'QR',
            'qr_title' => "Your page's QR code",
            'close' => 'Close',
            'open' => 'Open',
            'edit' => 'Edit',
            'welcome' => 'bio.cripsum.com/%s is yours now.',
            'claim_also' => 'Do you also want bio.cripsum.com/%s?',
            'claim_also_t' => 'You picked it before signing in and it is still free: it becomes your short link.',
            'claim_done' => 'Done: bio.cripsum.com/%s is yours.',
            'who_sees' => 'Who can see it',
            'vis_public' => 'Everyone', 'vis_logged_in' => 'Members', 'vis_friends' => 'Friends', 'vis_private' => 'Only me',
            'vis_now_public' => 'Right now anyone with the link can see it, even without an account.',
            'vis_now_logged_in' => 'Right now only people with a Cripsum account can see it.',
            'vis_now_friends' => 'Right now only your friends can see it.',
            'vis_now_private' => 'Right now only you can see it.',
            'explore_switch' => 'Show me in Explore',
            'explore_switch_t' => "Your page shows up in the list of the community's profiles.",
            'explore_switch_off' => 'Explore only shows pages everyone can see.',
            'saved' => 'Saved.',
            'save_failed' => 'Could not save. Try again.',
            'todo' => 'To do',
            'todo_of' => '%d of %d',
            'todo_address' => 'Pick your address',
            'todo_photo' => 'Add a profile photo',
            'todo_bio' => 'Write a bio',
            'todo_links' => 'Add a social or a link',
            'todo_bg' => 'Pick a background',
            'todo_go' => 'Do it',
            'areas' => 'Edit',
            'a_profile' => 'Profile', 'a_profile_t' => 'Photo, name, bio, tags and socials.',
            'a_appearance' => 'Style', 'a_appearance_t' => 'Theme, colours, layout and background.',
            'a_effects' => 'Effects', 'a_effects_t' => 'Name, photo ring, cursor.',
            'a_music' => 'Music', 'a_music_t' => 'The song that plays when they open you.',
            'a_sections' => 'Sections', 'a_sections_t' => 'Links, projects, favourites, in order.',
            'a_settings' => 'Settings', 'a_settings_t' => 'Address, Discord, browser tab.',
            'preview' => 'Preview of your page',
            'views' => 'Views',
            'last_edit' => 'Last edit',
            'today' => 'today',
            'yesterday' => 'yesterday',
            'days_ago' => 'days ago',
            'never' => 'never',
            'requests_1' => '1 friend request',
            'requests_n' => '%d friend requests',
            'requests_t' => 'Answer on Cripsum',
            'explore_row' => 'Explore',
            'explore_row_t' => "The community's pages",
            'premium' => 'Premium',
            'premium_t' => 'More sections, effects and fonts',
            'account_note' => 'Password, email and devices are in %s.',
            'account_link' => 'Cripsum settings',
            'explore' => 'Explore',
            'explore_lead' => "The pages of Cripsum's community.",
            'search_ph' => 'Search a name…',
            'search_label' => 'Search a name',
            'tab_vetrina' => 'Featured', 'tab_nuove' => 'New', 'tab_viste' => 'Most viewed', 'tab_amici' => 'Friends',
            'more' => 'Show more',
            'hide_note' => "Don't want to show up here? Turn it off in %s.",
            'empty_vetrina' => 'Nothing featured yet.',
            'empty_vetrina_staff' => 'Feature a page with the star on its card, under "New".',
            'empty_amici' => 'Your friends do not have a page that shows up here yet.',
            'empty_search' => 'No page with this name.',
            'empty_all' => 'No pages to show yet.',
            'feature_on' => 'Feature',
            'feature_off' => 'Remove from featured',
            'from_community' => 'From the community',
            'err_login' => 'You need to sign in to do this.',
            'err_csrf' => 'Session expired. Reload the page.',
            'err_slow' => 'Too many requests: wait a moment.',
            'err_generic' => 'Something went wrong. Try again.',
            'err_taken' => 'That name is no longer free.',
            'install' => 'Explore and featured pages need one database step: migrations/2026_10_18_bio_esplora.sql.',
        ],
    ];

    return $dict[$lang][$key] ?? $dict['it'][$key] ?? $key;
}

/**
 * Apre la pagina: testa (head-import, tema, bio.css), body, navbar del sito.
 * Dopo si stampa il contenuto e si chiude con bio_page_close().
 */
function bio_page_open(string $title, string $description, string $lang, string $bodyClass): void
{
    global $mysqli;

    // head-import stampa l'anteprima dei link da questi valori.
    $ogTitle = $title;
    $ogDescription = $description;
    $ogUrl = cripsum_bio_url(ltrim(strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/', '/'));
    ?>
<!DOCTYPE html>
<html lang="<?= bio_h($lang) ?>"<?= cripsum_theme_html_attr() ?>>
<head>
    <?php include __DIR__ . '/head-import.php'; ?>
    <meta name="theme-color" content="#0a0a0b">
    <title><?= bio_h($title) ?></title>
    <?php cripsum_theme_head(); ?>
    <link rel="stylesheet" href="<?= bio_h(cripsum_theme_asset('/assets/bio/bio.css')) ?>">
</head>
<body class="bh-body <?= bio_h($bodyClass) ?>">
    <?php
    $nsfw = (int)($_SESSION['nsfw'] ?? 0);
    $ruolo = (string)($_SESSION['ruolo'] ?? '');
    include __DIR__ . '/navbar.php';
    ?>
    <main class="bh-shell">
    <?php
}

/** Chiude la pagina: footer del sito e gli script; $config arriva a bio.js. */
function bio_page_close(string $lang, array $config = []): void
{
    ?>
    </main>
    <?php include __DIR__ . '/footer.php'; ?>
    <div class="bh-toast" data-bh-toast role="status" aria-live="polite" hidden></div>
    <script>window.BioHub = <?= json_encode($config + ['lang' => $lang], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="<?= bio_h(cripsum_theme_asset('/assets/bio/bio.js')) ?>" defer></script>
</body>
</html>
    <?php
}

/** Le colonne di Esplora (migration 2026_10_18_bio_esplora.sql). */
function bio_explore_ready(mysqli $mysqli): bool
{
    return auth_column_exists($mysqli, 'utenti', 'profile_discoverable')
        && auth_column_exists($mysqli, 'utenti', 'profile_featured_at');
}

/**
 * Un nome per bio.cripsum.com, come lo vede il campo dell'ingresso. Le regole
 * sono quelle degli username, perche' chi lo prende da ospite lo usa come
 * username registrandosi: 'free', 'taken', 'reserved', 'invalid' o 'yours'.
 */
function bio_name_state(mysqli $mysqli, string $name, int $viewerId = 0): array
{
    $name = trim($name);
    if (!auth_is_valid_username($name)) {
        return ['state' => 'invalid'];
    }
    if (cripsum_is_reserved_name($name)) {
        return ['state' => 'reserved'];
    }

    $stmt = $mysqli->prepare("
        SELECT id, username, custom_alias
        FROM utenti
        WHERE LOWER(username) = LOWER(?) OR LOWER(custom_alias) = LOWER(?)
        LIMIT 1
    ");
    if (!$stmt) {
        return ['state' => 'invalid'];
    }
    $stmt->bind_param('ss', $name, $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['state' => 'free'];
    }
    if ($viewerId > 0 && (int)$row['id'] === $viewerId) {
        return ['state' => 'yours'];
    }

    return ['state' => 'taken', 'url' => '/' . rawurlencode($name)];
}

/** Il profilo di chi e' dentro, con quello che serve a «La tua pagina». */
function bio_owner(mysqli $mysqli, int $userId): ?array
{
    $extra = bio_explore_ready($mysqli) ? ', profile_discoverable' : ', 1 AS profile_discoverable';
    $stmt = $mysqli->prepare("
        SELECT id, username, display_name, custom_alias, bio, discord_use_avatar,
               (profile_pic IS NOT NULL AND profile_pic <> '') AS has_pic,
               profile_banner_type, profile_visibility, profile_views, profile_updated_at,
               data_creazione, is_premium, accent_color{$extra}
        FROM utenti
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Cosa manca alla pagina: la stessa lista dell'editor
 * (assets/js/profile-editor/editor.js, «Primo accesso: cosa manca»), con
 * l'indirizzo in cima. Ogni voce: [chiave, fatta, area dell'editor].
 */
function bio_owner_todo(mysqli $mysqli, array $owner): array
{
    $userId = (int)$owner['id'];
    $links = 0;
    foreach (['utenti_social', 'utenti_links'] as $table) {
        if (!auth_table_exists($mysqli, $table)) {
            continue;
        }
        $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM {$table} WHERE utente_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $links += (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);
            $stmt->close();
        }
    }

    return [
        ['todo_address', true, 'settings'],
        ['todo_photo', (int)($owner['discord_use_avatar'] ?? 0) === 1 || (int)($owner['has_pic'] ?? 0) === 1, 'profile'],
        ['todo_bio', trim((string)($owner['bio'] ?? '')) !== '', 'profile'],
        ['todo_links', $links > 0, 'profile'],
        ['todo_bg', !empty($owner['profile_banner_type']), 'appearance'],
    ];
}

function bio_pending_friend_requests(mysqli $mysqli, int $userId): int
{
    if (!auth_table_exists($mysqli, 'friendship_requests')) {
        return 0;
    }
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM friendship_requests WHERE receiver_id = ? AND status = 'pending'");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $n = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);
    $stmt->close();

    return $n;
}

/** «oggi», «ieri», «3 giorni fa». */
function bio_days_ago(?string $datetime, string $lang): array
{
    $ts = $datetime ? strtotime($datetime) : false;
    if (!$ts) {
        return [bio_t('never', $lang), ''];
    }
    $days = (int)floor((strtotime('today') - strtotime(date('Y-m-d', $ts))) / 86400);
    if ($days <= 0) {
        return [bio_t('today', $lang), ''];
    }
    if ($days === 1) {
        return [bio_t('yesterday', $lang), ''];
    }

    return [(string)$days, bio_t('days_ago', $lang)];
}

/** Un colore #rrggbb sicuro da mettere in uno style, o null. */
function bio_color(?string $value): ?string
{
    $value = trim((string)$value);

    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;
}

/** Le viste di Esplora. */
const BIO_EXPLORE_VIEWS = ['vetrina', 'nuove', 'viste', 'amici'];

/**
 * Le schede di Esplora. Solo profili pubblici (per «Amici» anche quelli che
 * gli amici possono vedere), mai privati, bannati o in cancellazione, e mai chi
 * ha spento «Mostrami in Esplora». Restituisce [righe, ce ne sono altre].
 */
function bio_explore_cards(mysqli $mysqli, string $view, string $q, int $viewerId, int $page, int $perPage = 24): array
{
    $ready = bio_explore_ready($mysqli);
    $where = ['u.isBannato = 0'];
    $types = '';
    $params = [];
    $join = '';

    if (auth_column_exists($mysqli, 'utenti', 'deletion_requested_at')) {
        $where[] = 'u.deletion_requested_at IS NULL';
    }
    if ($ready) {
        $where[] = 'u.profile_discoverable = 1';
    }

    if ($view === 'amici' && $viewerId > 0 && auth_table_exists($mysqli, 'friendships')) {
        $join = 'JOIN friendships f ON f.user_one_id = LEAST(?, u.id) AND f.user_two_id = GREATEST(?, u.id)';
        $types .= 'ii';
        $params[] = $viewerId;
        $params[] = $viewerId;
        $where[] = "u.profile_visibility IN ('public', 'logged_in', 'friends')";
        $where[] = 'u.id <> ' . (int)$viewerId;
    } else {
        $where[] = "u.profile_visibility = 'public'";
    }

    if ($view === 'vetrina') {
        if (!$ready) {
            return [[], false];
        }
        $where[] = 'u.profile_featured_at IS NOT NULL';
    }

    $q = trim($q);
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(u.username LIKE ? OR u.display_name LIKE ? OR u.custom_alias LIKE ?)';
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }

    $order = match ($view) {
        'vetrina' => 'u.profile_featured_at DESC',
        'viste' => 'u.profile_views DESC, u.id DESC',
        'amici' => 'u.username ASC',
        default => 'COALESCE(u.profile_updated_at, u.data_creazione) DESC, u.id DESC',
    };

    $featured = $ready ? 'u.profile_featured_at' : 'NULL AS profile_featured_at';
    $limit = $perPage + 1;
    $offset = max(0, ($page - 1) * $perPage);
    $sql = "
        SELECT u.id, u.username, u.display_name, u.custom_alias, u.accent_color,
               u.profile_banner_type, u.profile_updated_at, {$featured}
        FROM utenti u
        {$join}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$order}
        LIMIT {$limit} OFFSET {$offset}
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [[], false];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $more = count($rows) > $perPage;

    return [array_slice($rows, 0, $perPage), $more];
}

/** La scheda di un profilo (Esplora e vetrina dell'ingresso). */
function bio_render_card(array $row, bool $staff, string $lang): void
{
    $name = cripsum_bio_profile_name($row);
    $display = trim((string)($row['display_name'] ?? '')) ?: (string)$row['username'];
    $accent = bio_color($row['accent_color'] ?? null);
    $stamp = !empty($row['profile_updated_at']) ? (int)strtotime((string)$row['profile_updated_at']) : 0;
    $mime = strtolower((string)($row['profile_banner_type'] ?? ''));
    $isImage = str_starts_with($mime, 'image/');
    $featured = !empty($row['profile_featured_at']);
    ?>
    <li class="bh-card"<?= $accent ? ' style="--acc:' . bio_h($accent) . '"' : '' ?>>
        <a href="/<?= bio_h(rawurlencode($name)) ?>">
            <?php if ($isImage): ?>
                <div class="bh-card__bg"><img src="/includes/get_profile_banner.php?id=<?= (int)$row['id'] ?>&amp;t=<?= $stamp ?>" alt="" loading="lazy" decoding="async"></div>
            <?php else: ?>
                <div class="bh-card__bg bh-card__bg--plain"></div>
            <?php endif; ?>
            <div class="bh-card__body">
                <img class="bh-card__pfp" src="<?= bio_h(profile_avatar_url($row, 128)) ?>" alt="" loading="lazy" decoding="async">
                <span class="bh-card__text">
                    <span class="bh-card__name"><?= bio_h($display) ?></span>
                    <span class="bh-card__url"><?= bio_h((string)parse_url(cripsum_bio_url(), PHP_URL_HOST)) ?>/<?= bio_h($name) ?></span>
                </span>
            </div>
        </a>
        <?php if ($staff): ?>
            <button type="button" class="bh-star<?= $featured ? ' is-on' : '' ?>" data-bh-feature="<?= (int)$row['id'] ?>" aria-pressed="<?= $featured ? 'true' : 'false' ?>"
                aria-label="<?= bio_h(bio_t($featured ? 'feature_off' : 'feature_on', $lang)) ?>" data-on="<?= bio_h(bio_t('feature_off', $lang)) ?>" data-off="<?= bio_h(bio_t('feature_on', $lang)) ?>">
                <i class="fa-<?= $featured ? 'solid' : 'regular' ?> fa-star" aria-hidden="true"></i>
            </button>
        <?php endif; ?>
    </li>
    <?php
}
