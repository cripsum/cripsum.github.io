<?php
// Plain-text rendering of the data export.
//
// The archive can be produced in two formats, like the mainstream platforms:
//   JSON  - structured, for importing the data somewhere else
//   TXT   - readable, for simply looking at what the account holds
// Both carry exactly the same content; only the presentation differs, and both
// follow the language the archive was requested in.

const ACCOUNT_TXT_WRAP = 78;        // wrap width, comfortable in any editor
const ACCOUNT_TXT_LABEL_WIDTH = 22; // label column before the value

/** Formats are validated against this list before anything is generated. */
function account_export_formats(): array
{
    return ['txt', 'json'];
}

/** Normalises whatever the request asked for. */
function account_export_normalize_format(?string $format): string
{
    $format = strtolower(trim((string)$format));

    return in_array($format, account_export_formats(), true) ? $format : 'txt';
}

/**
 * Every string the archive itself is built from.
 *
 * Memoised: this is consulted once per exported value, which for a busy account
 * means thousands of calls.
 */
function account_txt_strings(string $lang = 'it'): array
{
    static $cache = [];

    $lang = account_export_lang($lang);
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }

    return $cache[$lang] = $lang === 'en'
        ? [
            'readme_name' => 'README.txt',
            'data_dir' => 'data',
            'export_title' => 'Data export - Cripsum',
            'account_title' => 'Your account',
            'account' => 'Account',
            'generated_on' => 'Generated on',
            'format' => 'Format',
            'format_json' => 'JSON (to import your data elsewhere)',
            'format_txt' => 'Text (to read your data)',
            'contents' => 'Contents',
            'contents_account' => 'Your profile and account settings.',
            'contents_data' => 'One file per section.',
            'contents_media' => 'The files you uploaded.',
            'sections' => 'Sections included',
            'no_sections' => '(no additional data)',
            'media_files' => 'Media files',
            'notes' => 'Notes',
            'note_credentials' => [
                'Passwords, backup codes, 2FA secrets and session tokens are not',
                'included: they are credentials and never leave the server.',
            ],
            'note_messages' => [
                'Private messages include the ones you received too, because they',
                'are part of your inbox.',
            ],
            'note_privacy' => 'This archive holds personal data: keep it somewhere safe.',
            'entry' => 'entry',
            'entries' => 'entries',
            'empty' => '(empty)',
            'yes' => 'Yes',
            'no' => 'No',
            'json_section' => 'section',
            'json_rows' => 'rows',
            'json_data' => 'data',
            'json_export' => 'export',
            'json_generated' => 'generated_at',
            'json_site' => 'site',
            'json_account' => 'account',
            'binary' => 'binary data, %d bytes',
            'binary_media' => 'binary data, %d bytes - see the media/ folder',
        ]
        : [
            'readme_name' => 'LEGGIMI.txt',
            'data_dir' => 'dati',
            'export_title' => 'Esportazione dati - Cripsum',
            'account_title' => 'Il tuo account',
            'account' => 'Account',
            'generated_on' => 'Generato il',
            'format' => 'Formato',
            'format_json' => 'JSON (per importare i dati altrove)',
            'format_txt' => 'Testo (per leggere i dati)',
            'contents' => 'Contenuto',
            'contents_account' => 'Il tuo profilo e le impostazioni.',
            'contents_data' => 'Una sezione per file.',
            'contents_media' => 'I file che hai caricato.',
            'sections' => 'Sezioni incluse',
            'no_sections' => '(nessun dato aggiuntivo)',
            'media_files' => 'File multimediali',
            'notes' => 'Note',
            'note_credentials' => [
                'Password, codici di backup, segreti 2FA e token di sessione non sono',
                'inclusi: sono credenziali e non lasciano mai il server.',
            ],
            'note_messages' => [
                'I messaggi privati includono anche quelli ricevuti, perche fanno parte',
                'della tua casella.',
            ],
            'note_privacy' => 'Questo archivio contiene dati personali: conservalo in un posto sicuro.',
            'entry' => 'voce',
            'entries' => 'voci',
            'empty' => '(vuoto)',
            'yes' => 'Si',
            'no' => 'No',
            'json_section' => 'sezione',
            'json_rows' => 'righe',
            'json_data' => 'dati',
            'json_export' => 'esportazione',
            'json_generated' => 'generata_il',
            'json_site' => 'sito',
            'json_account' => 'account',
            'binary' => 'dati binari, %d byte',
            'binary_media' => 'dati binari, %d byte - vedi la cartella media/',
        ];
}

/** Windows Notepad still expects CRLF, and every other editor copes with it. */
function account_txt_lines(array $lines): string
{
    return implode("\r\n", $lines) . "\r\n";
}

/** Renders one value, ready to be laid out next to its label. */
function account_txt_value($value, array $s): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    if (is_bool($value)) {
        return $value ? $s['yes'] : $s['no'];
    }

    $text = trim((string)$value);
    if ($text === '') {
        return '-';
    }

    // Dates read better in the local format.
    if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $text)) {
        $stamp = strtotime($text);
        if ($stamp !== false) {
            return date('d/m/Y H:i', $stamp);
        }
    }

    return $text;
}

/** "Titolo               : valore", wrapped under the label column. */
function account_txt_pair(string $label, $value, array $s): array
{
    $text = account_txt_value($value, $s);
    $prefix = str_pad(mb_substr($label, 0, ACCOUNT_TXT_LABEL_WIDTH, 'UTF-8'), ACCOUNT_TXT_LABEL_WIDTH, ' ') . ' : ';
    $indent = str_repeat(' ', ACCOUNT_TXT_LABEL_WIDTH + 3);
    $available = max(20, ACCOUNT_TXT_WRAP - ACCOUNT_TXT_LABEL_WIDTH - 3);

    // The author's own paragraph breaks are kept; only over-long lines wrap.
    $paragraphs = preg_split('/\R/u', $text) ?: [$text];
    $out = [];
    foreach ($paragraphs as $paragraph) {
        $chunks = explode("\n", wordwrap($paragraph, $available, "\n", true));
        foreach ($chunks as $chunk) {
            $out[] = (empty($out) ? $prefix : $indent) . $chunk;
        }
    }

    return empty($out) ? [$prefix . '-'] : $out;
}

/** Underlined heading. */
function account_txt_heading(string $title, string $char = '='): array
{
    $title = mb_strtoupper($title, 'UTF-8');

    return [$title, str_repeat($char, min(ACCOUNT_TXT_WRAP, max(8, mb_strlen($title, 'UTF-8'))))];
}

/** The account profile as a text document. */
function account_txt_account(array $account, string $lang = 'it'): string
{
    $s = account_txt_strings($lang);

    $lines = account_txt_heading($s['account_title']);
    $lines[] = '';
    foreach ($account as $label => $value) {
        foreach (account_txt_pair((string)$label, $value, $s) as $line) {
            $lines[] = $line;
        }
    }
    $lines[] = '';

    return account_txt_lines($lines);
}

/** One section (a former table) as a text document. */
function account_txt_section(string $label, array $rows, string $lang = 'it'): string
{
    $s = account_txt_strings($lang);
    $count = count($rows);

    $lines = account_txt_heading($label);
    $lines[] = $count . ' ' . ($count === 1 ? $s['entry'] : $s['entries']);
    $lines[] = '';

    if ($count === 0) {
        $lines[] = $s['empty'];
        return account_txt_lines($lines);
    }

    $index = 0;
    foreach ($rows as $row) {
        $index++;
        $lines[] = '--- ' . $index . ' ' . str_repeat('-', max(0, ACCOUNT_TXT_WRAP - 5 - mb_strlen((string)$index, 'UTF-8')));
        foreach ($row as $field => $value) {
            foreach (account_txt_pair((string)$field, $value, $s) as $line) {
                $lines[] = $line;
            }
        }
        $lines[] = '';
    }

    return account_txt_lines($lines);
}

/** Index file shipped at the root of the archive. */
function account_txt_readme(array $account, array $sectionCounts, int $mediaCount, string $format, string $lang = 'it'): string
{
    $s = account_txt_strings($lang);
    $isJson = $format === 'json';
    $extension = $isJson ? '.json' : '.txt';

    $lines = account_txt_heading($s['export_title']);
    $lines[] = '';
    foreach (account_txt_pair($s['account'], $account['username'] ?? '-', $s) as $line) {
        $lines[] = $line;
    }
    foreach (account_txt_pair($s['generated_on'], date('d/m/Y H:i'), $s) as $line) {
        $lines[] = $line;
    }
    foreach (account_txt_pair($s['format'], $isJson ? $s['format_json'] : $s['format_txt'], $s) as $line) {
        $lines[] = $line;
    }
    $lines[] = '';

    $lines = array_merge($lines, account_txt_heading($s['contents'], '-'));
    $lines[] = str_pad('account' . $extension, 15) . $s['contents_account'];
    $lines[] = str_pad($s['data_dir'] . '/', 15) . $s['contents_data'];
    $lines[] = str_pad('media/', 15) . $s['contents_media'];
    $lines[] = '';

    $lines = array_merge($lines, account_txt_heading($s['sections'], '-'));
    if (empty($sectionCounts)) {
        $lines[] = $s['no_sections'];
    } else {
        ksort($sectionCounts);
        foreach ($sectionCounts as $section => $count) {
            $lines[] = '  ' . str_pad(mb_substr((string)$section, 0, 40, 'UTF-8'), 42, '.')
                . ' ' . $count . ' ' . ($count === 1 ? $s['entry'] : $s['entries']);
        }
    }
    $lines[] = '';
    foreach (account_txt_pair($s['media_files'], (string)$mediaCount, $s) as $line) {
        $lines[] = $line;
    }
    $lines[] = '';

    $lines = array_merge($lines, account_txt_heading($s['notes'], '-'));
    foreach ($s['note_credentials'] as $line) {
        $lines[] = $line;
    }
    $lines[] = '';
    foreach ($s['note_messages'] as $line) {
        $lines[] = $line;
    }
    $lines[] = '';
    $lines[] = $s['note_privacy'];
    $lines[] = '';

    return account_txt_lines($lines);
}

/** Reads the format back out of a generated archive's file name. */
function account_export_format_of(string $fileName): string
{
    return preg_match('/-(txt|json)-[a-f0-9]{32}\.zip$/i', $fileName, $m) === 1
        ? strtolower($m[1])
        : 'txt';
}
