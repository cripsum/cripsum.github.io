<?php
declare(strict_types=1);

/**
 * Messaggi del bot scritti dal pannello: benvenuto, addio, boost, embed
 * salvati. Qui si controlla quello che arriva prima di salvarlo.
 *
 * Il testo e' libero, ma dentro i limiti di Discord e senza indirizzi
 * strani: un'immagine o un link sono https, oppure un segnaposto come
 * {user.avatar}. Il canale deve essere di quel server e il bot deve poterci
 * scrivere.
 */

/** Tipi di messaggio. `premium` esiste solo nel server di Cripsum. */
const BOTPANEL_TEMPLATE_KINDS = ['welcome', 'welcome_dm', 'goodbye', 'boost', 'premium', 'custom'];

/** Quanti messaggi salvati per nome puo' avere un server. */
const BOTPANEL_TEMPLATE_CUSTOM_MAX = 50;

/**
 * Testo pulito: niente caratteri di controllo, a capo si'.
 */
function botpanel_template_text($value, int $max, string $path, array &$errors): string
{
    $text = is_scalar($value) ? (string)$value : '';
    $text = trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text));

    if (mb_strlen($text) > $max) {
        $errors[$path] = 'too_long';
        return '';
    }

    return $text;
}

/**
 * Un indirizzo di immagine o di link: vuoto, https, oppure un segnaposto.
 */
function botpanel_template_url($value, string $path, array &$errors): string
{
    $url = is_string($value) ? trim($value) : '';

    if ($url === '' || preg_match('/^\{[a-z.]{1,30}\}$/', $url)) {
        return $url;
    }

    $parts = parse_url($url);

    if (strlen($url) > 400
        || !is_array($parts)
        || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host'])
        || filter_var($url, FILTER_VALIDATE_URL) === false
    ) {
        $errors[$path] = 'url';
        return '';
    }

    return $url;
}

/**
 * Controlla un messaggio. `$ctx` e' lo stesso dei moduli (canali del server).
 *
 * @param mixed $input
 * @param array<string, mixed> $ctx
 * @return array{ok: bool, errors: array<string, string>, enabled: bool, channel_id: string, content: string, embed: ?array<string, mixed>}
 */
function botpanel_validate_template(string $kind, $input, array $ctx): array
{
    $input = is_array($input) ? $input : [];
    $errors = [];

    $enabled = !array_key_exists('enabled', $input) || !empty($input['enabled']);
    $content = botpanel_template_text($input['content'] ?? '', 2000, 'content', $errors);

    $source = is_array($input['embed'] ?? null) ? $input['embed'] : [];
    $embed = [
        'title' => botpanel_template_text($source['title'] ?? '', 256, 'embed.title', $errors),
        'description' => botpanel_template_text($source['description'] ?? '', 4000, 'embed.description', $errors),
        'url' => botpanel_template_url($source['url'] ?? '', 'embed.url', $errors),
        'color' => '',
        'image' => botpanel_template_url($source['image'] ?? '', 'embed.image', $errors),
        'thumbnail' => botpanel_template_url($source['thumbnail'] ?? '', 'embed.thumbnail', $errors),
        'author_name' => botpanel_template_text($source['author_name'] ?? '', 256, 'embed.author_name', $errors),
        'author_icon' => botpanel_template_url($source['author_icon'] ?? '', 'embed.author_icon', $errors),
        'footer_text' => botpanel_template_text($source['footer_text'] ?? '', 2048, 'embed.footer_text', $errors),
        'footer_icon' => botpanel_template_url($source['footer_icon'] ?? '', 'embed.footer_icon', $errors),
        'timestamp' => !empty($source['timestamp']),
    ];

    $color = is_string($source['color'] ?? null) ? strtolower(trim($source['color'])) : '';
    if ($color !== '' && !preg_match('/^#[0-9a-f]{6}$/', $color)) {
        $errors['embed.color'] = 'color';
    } else {
        $embed['color'] = $color;
    }

    // I campi dell'embed si scrivono dall'editor su Discord: il pannello non
    // li modifica, ma non deve nemmeno perderli quando salva.
    $fields = [];
    foreach (array_slice(is_array($source['fields'] ?? null) ? $source['fields'] : [], 0, 25) as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $name = botpanel_template_text($field['name'] ?? '', 256, "embed.fields.$index.name", $errors);
        $value = botpanel_template_text($field['value'] ?? '', 1024, "embed.fields.$index.value", $errors);

        if ($name !== '' && $value !== '') {
            $fields[] = ['name' => $name, 'value' => $value, 'inline' => !empty($field['inline'])];
        }
    }
    if ($fields !== []) {
        $embed['fields'] = $fields;
    }

    // Discord rifiuta un embed che in tutto supera i 6000 caratteri.
    $total = mb_strlen($embed['title'] . $embed['description'] . $embed['author_name'] . $embed['footer_text']);
    foreach ($fields as $field) {
        $total += mb_strlen($field['name'] . $field['value']);
    }
    if ($total > 6000) {
        $errors['embed.description'] = 'too_long';
    }

    $hasEmbed = $embed['title'] !== '' || $embed['description'] !== '' || $embed['image'] !== ''
        || $embed['thumbnail'] !== '' || $embed['author_name'] !== '' || $embed['footer_text'] !== '' || $fields !== [];

    if ($content === '' && !$hasEmbed) {
        $errors['content'] = 'required';
    }

    // Il canale: obbligatorio per i messaggi che partono da soli in un
    // canale, facoltativo per quelli salvati (si sceglie quando si pubblica).
    $channelId = is_string($input['channel_id'] ?? null) ? trim($input['channel_id']) : '';
    $needsChannel = $enabled && !in_array($kind, ['custom', 'welcome_dm'], true);

    if ($kind === 'welcome_dm') {
        $channelId = '';
    } elseif ($channelId === '') {
        if ($needsChannel) {
            $errors['channel_id'] = 'required';
        }
    } else {
        $channel = $ctx['channels'][$channelId] ?? null;

        if (!botpanel_is_snowflake($channelId) || !$channel || !in_array($channel['kind'] ?? '', ['text', 'announcement'], true)) {
            $errors['channel_id'] = 'channel';
        } elseif (empty($channel['bot']['view']) || empty($channel['bot']['send'])) {
            $errors['channel_id'] = 'bot_cannot:send';
        } elseif (empty($channel['bot']['embed'])) {
            $errors['channel_id'] = 'bot_cannot:embed';
        }
    }

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'enabled' => $enabled,
        'channel_id' => $channelId,
        'content' => $content,
        'embed' => $hasEmbed ? $embed : null,
    ];
}

/**
 * Nome di un messaggio salvato: lettere, cifre, spazi, trattini.
 */
function botpanel_template_name($value): ?string
{
    $name = is_string($value) ? trim($value) : '';

    return preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _-]{0,59}$/u', $name) ? $name : null;
}
