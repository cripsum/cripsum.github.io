<?php
/**
 * Controlli dell'editor del profilo.
 *
 * Ogni impostazione passa da qui, cosi' ha sempre lo stesso aspetto e lo
 * stesso comportamento: etichetta, aiuto, blocco Premium, chiavi per la
 * ricerca. I nomi dei campi (`name`) sono quelli che si aspetta
 * api/update_profile.php.
 *
 * Il contesto (lingua, Premium) sta in $GLOBALS['pe'], impostato da page.php.
 */

function pe_h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Testo nella lingua dell'editor. */
function pe_t(string $it, string $en): string
{
    return ($GLOBALS['pe']['lang'] ?? 'it') === 'it' ? $it : $en;
}

function pe_is_premium(): bool
{
    return !empty($GLOBALS['pe']['premium']);
}

/** Id stabile per un campo, per collegare label e controllo. */
function pe_id(string $name): string
{
    return 'pe-' . preg_replace('/[^a-z0-9]+/i', '-', $name);
}

function pe_premium_chip(): string
{
    return '<span class="pe-chip-premium" title="Premium"><i class="fa-solid fa-crown" aria-hidden="true"></i><span>Premium</span></span>';
}

/**
 * Attributi comuni del contenitore di un campo: chiavi di ricerca e blocco.
 * Un campo Premium per chi non lo e' resta visibile ma bloccato: si capisce
 * cosa si sblocca, e il clic apre la proposta invece di non fare niente.
 */
function pe_wrap_attrs(array $o, string $class): string
{
    $locked = !empty($o['premium']) && !pe_is_premium();
    $classes = trim($class . ' ' . ($o['class'] ?? '') . ($locked ? ' is-locked' : ''));
    $attrs = ' class="' . pe_h($classes) . '"';
    $search = trim(($o['label'] ?? '') . ' ' . ($o['keywords'] ?? ''));
    if ($search !== '') {
        $attrs .= ' data-search="' . pe_h($search) . '"';
    }
    if (!empty($o['show_if'])) {
        // show_if: "campo=valore1|valore2" oppure "campo" (checkbox attiva).
        $attrs .= ' data-show-if="' . pe_h($o['show_if']) . '"';
    }
    if ($locked) {
        $attrs .= ' data-premium-lock="1"';
    }
    if (!empty($o['id'])) {
        $attrs .= ' id="' . pe_h($o['id']) . '"';
    }
    return $attrs;
}

function pe_disabled(array $o): string
{
    return (!empty($o['premium']) && !pe_is_premium()) || !empty($o['disabled']) ? ' disabled' : '';
}

function pe_label_row(array $o, ?string $for = null): string
{
    $html = '<div class="pe-field-label">';
    $html .= $for
        ? '<label for="' . pe_h($for) . '">' . pe_h($o['label'] ?? '') . '</label>'
        : '<span class="pe-field-title">' . pe_h($o['label'] ?? '') . '</span>';
    if (!empty($o['premium'])) {
        $html .= pe_premium_chip();
    }
    if (isset($o['value_output'])) {
        $html .= '<output class="pe-value" for="' . pe_h($for) . '">' . pe_h($o['value_output']) . '</output>';
    }
    $html .= '</div>';
    return $html;
}

function pe_help(array $o): string
{
    return !empty($o['help']) ? '<p class="pe-help">' . $o['help'] . '</p>' : '';
}

/** Apertura di un gruppo di impostazioni dentro una vista. */
function pe_group(string $id, string $title, ?string $description = null, array $o = []): void
{
    $o['label'] = $title;
    echo '<section' . pe_wrap_attrs($o, 'pe-group') . ' id="' . pe_h($id) . '" data-group="' . pe_h($id) . '">';
    echo '<header class="pe-group-head"><div><h3>' . pe_h($title) . '</h3>';
    if ($description) {
        echo '<p>' . pe_h($description) . '</p>';
    }
    echo '</div>';
    if (!empty($o['premium'])) {
        echo pe_premium_chip();
    }
    if (!empty($o['action'])) {
        echo $o['action'];
    }
    echo '</header><div class="pe-group-body">';
}

function pe_group_end(): void
{
    echo '</div></section>';
}

function pe_text(string $name, $value, array $o = []): void
{
    $id = $o['input_id'] ?? pe_id($name);
    $type = $o['type'] ?? 'text';
    echo '<div' . pe_wrap_attrs($o, 'pe-field') . '>';
    echo pe_label_row($o, $id);
    $input = '<input class="pe-input" type="' . pe_h($type) . '" name="' . pe_h($name) . '" id="' . pe_h($id) . '" value="' . pe_h($value) . '"'
        . (isset($o['maxlength']) ? ' maxlength="' . (int)$o['maxlength'] . '"' : '')
        . (isset($o['placeholder']) ? ' placeholder="' . pe_h($o['placeholder']) . '"' : '')
        . (!empty($o['required']) ? ' required' : '')
        . (!empty($o['readonly']) ? ' readonly' : '')
        . (!empty($o['autocomplete']) ? ' autocomplete="' . pe_h($o['autocomplete']) . '"' : ' autocomplete="off"')
        . (!empty($o['counter']) ? ' data-counter' : '')
        . pe_disabled($o) . '>';
    if (!empty($o['prefix'])) {
        echo '<div class="pe-input-group"><span class="pe-input-prefix">' . pe_h($o['prefix']) . '</span>' . $input;
        echo !empty($o['suffix_html']) ? $o['suffix_html'] : '';
        echo '</div>';
    } else {
        echo $input;
    }
    if (!empty($o['counter'])) {
        echo '<span class="pe-counter" data-counter-for="' . pe_h($id) . '"></span>';
    }
    echo pe_help($o);
    echo '</div>';
}

function pe_textarea(string $name, $value, array $o = []): void
{
    $id = $o['input_id'] ?? pe_id($name);
    echo '<div' . pe_wrap_attrs($o, 'pe-field') . '>';
    echo pe_label_row($o, $id);
    echo '<textarea class="pe-input pe-textarea" name="' . pe_h($name) . '" id="' . pe_h($id) . '" rows="' . (int)($o['rows'] ?? 4) . '"'
        . (isset($o['maxlength']) ? ' maxlength="' . (int)$o['maxlength'] . '"' : '')
        . (isset($o['placeholder']) ? ' placeholder="' . pe_h($o['placeholder']) . '"' : '')
        . ' data-counter' . pe_disabled($o) . '>' . pe_h($value) . '</textarea>';
    echo '<span class="pe-counter" data-counter-for="' . pe_h($id) . '"></span>';
    echo pe_help($o);
    echo '</div>';
}

/** Interruttore. Il campo nascosto manda 0 quando e' spento. */
function pe_toggle(string $name, bool $checked, array $o = []): void
{
    $id = $o['input_id'] ?? pe_id($name);
    echo '<label' . pe_wrap_attrs($o, 'pe-toggle') . ' for="' . pe_h($id) . '">';
    echo '<span class="pe-toggle-text"><span class="pe-toggle-title">' . pe_h($o['label'] ?? '');
    if (!empty($o['premium'])) {
        echo ' ' . pe_premium_chip();
    }
    echo '</span>';
    if (!empty($o['description'])) {
        echo '<small>' . pe_h($o['description']) . '</small>';
    }
    echo '</span>';
    echo '<input type="hidden" name="' . pe_h($name) . '" value="0">';
    echo '<input class="pe-switch-input" type="checkbox" role="switch" name="' . pe_h($name) . '" id="' . pe_h($id) . '" value="1"' . ($checked ? ' checked' : '') . pe_disabled($o) . '>';
    echo '<span class="pe-switch" aria-hidden="true"></span>';
    echo '</label>';
}

/**
 * Slider con il valore scritto accanto. `format`: px, %, ms, deg, x, ratio
 * (0-1 mostrato come percentuale) o vuoto.
 */
function pe_slider(string $name, $value, array $o): void
{
    $id = $o['input_id'] ?? pe_id($name);
    $format = $o['format'] ?? '';
    $o['value_output'] = pe_slider_label($value, $format, $o['zero_label'] ?? null);
    echo '<div' . pe_wrap_attrs($o, 'pe-field pe-field--slider') . '>';
    echo pe_label_row($o, $id);
    echo '<input class="pe-range" type="range" name="' . pe_h($name) . '" id="' . pe_h($id) . '" min="' . pe_h($o['min']) . '" max="' . pe_h($o['max']) . '" step="' . pe_h($o['step'] ?? 1) . '" value="' . pe_h($value) . '" data-format="' . pe_h($format) . '"'
        . (isset($o['zero_label']) ? ' data-zero-label="' . pe_h($o['zero_label']) . '"' : '')
        . (isset($o['default']) ? ' data-default="' . pe_h($o['default']) . '"' : '')
        . pe_disabled($o) . '>';
    echo pe_help($o);
    echo '</div>';
}

function pe_slider_label($value, string $format, ?string $zeroLabel = null): string
{
    if ($zeroLabel !== null && (float)$value == 0.0) {
        return $zeroLabel;
    }
    return match ($format) {
        'px' => (int)$value . 'px',
        '%' => (int)$value . '%',
        'ms' => (int)$value . 'ms',
        'deg' => (int)$value . '°',
        'x' => rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.') . '×',
        'ratio' => (int)round((float)$value * 100) . '%',
        default => (string)$value,
    };
}

/**
 * Scelta fra poche opzioni, tutte visibili. `$options` come nei cataloghi:
 * [['value' => ..., 'label' => ..., 'premium' => bool, 'icon' => ..., 'art' => html]].
 * `variant`: seg (pulsanti in riga) oppure tiles (riquadri con anteprima).
 */
function pe_choice(string $name, $value, array $options, array $o = []): void
{
    $variant = $o['variant'] ?? 'seg';
    $id = pe_id($name);
    echo '<div' . pe_wrap_attrs($o, 'pe-field pe-field--choice') . '>';
    if (!empty($o['label'])) {
        echo pe_label_row($o);
    }
    $cols = isset($o['columns']) ? ' style="--pe-cols: ' . (int)$o['columns'] . '"' : '';
    echo '<div class="pe-' . ($variant === 'tiles' ? 'tiles' : 'seg') . '" role="radiogroup" aria-label="' . pe_h($o['label'] ?? $name) . '"' . $cols . '>';
    foreach ($options as $index => $opt) {
        $optLocked = !empty($opt['premium']) && !pe_is_premium();
        $optId = $id . '-' . $index;
        $checked = (string)$value === (string)$opt['value'];
        echo '<label class="pe-choice' . ($optLocked ? ' is-locked' : '') . '" for="' . pe_h($optId) . '"' . ($optLocked ? ' data-premium-lock="1"' : '') . (!empty($opt['desc']) ? ' title="' . pe_h($opt['desc']) . '"' : '') . '>';
        echo '<input type="radio" name="' . pe_h($name) . '" id="' . pe_h($optId) . '" value="' . pe_h($opt['value']) . '"' . ($checked ? ' checked' : '') . ($optLocked || pe_disabled($o) ? ' disabled' : '') . '>';
        echo '<span class="pe-choice-body">';
        if (!empty($opt['art'])) {
            echo '<span class="pe-choice-art" aria-hidden="true">' . $opt['art'] . '</span>';
        } elseif (!empty($opt['icon'])) {
            echo '<i class="' . pe_h($opt['icon']) . '" aria-hidden="true"></i>';
        }
        echo '<span class="pe-choice-label">' . pe_h($opt['label']) . '</span>';
        if ($optLocked) {
            echo '<i class="fa-solid fa-crown pe-choice-lock" aria-hidden="true"></i>';
        }
        echo '</span></label>';
    }
    echo '</div>';
    echo pe_help($o);
    echo '</div>';
}

/**
 * Colore. Il valore vive in un campo nascosto (hex, oppure vuoto = automatico
 * quando `auto` e' permesso); il selettore lo costruisce il JS.
 */
function pe_color(string $name, ?string $value, array $o = []): void
{
    $id = $o['input_id'] ?? pe_id($name);
    echo '<div' . pe_wrap_attrs($o, 'pe-field pe-field--color') . '>';
    echo pe_label_row($o);
    echo '<div class="pe-color" data-color-field'
        . (!empty($o['auto']) ? ' data-allow-auto="1" data-auto-label="' . pe_h($o['auto_label'] ?? pe_t('Automatico', 'Automatic')) . '"' : '')
        . (!empty($o['auto_preview']) ? ' data-auto-preview="' . pe_h($o['auto_preview']) . '"' : '') . '>';
    echo '<input type="hidden" name="' . pe_h($name) . '" id="' . pe_h($id) . '" value="' . pe_h($value ?? '') . '"' . pe_disabled($o) . '>';
    echo '</div>';
    echo pe_help($o);
    echo '</div>';
}

/**
 * Tendina. Con opzioni Premium il JS la trasforma in un menu che le mostra
 * con la corona; senza JS resta una select normale.
 */
function pe_select(string $name, $value, array $options, array $o = []): void
{
    $id = $o['input_id'] ?? pe_id($name);
    echo '<div' . pe_wrap_attrs($o, 'pe-field') . '>';
    echo pe_label_row($o, $id);
    echo '<select class="pe-select" name="' . pe_h($name) . '" id="' . pe_h($id) . '"' . (!empty($o['font_preview']) ? ' data-font-preview' : '') . pe_disabled($o) . '>';
    foreach ($options as $opt) {
        $locked = !empty($opt['premium']) && !pe_is_premium();
        echo '<option value="' . pe_h($opt['value']) . '"' . ((string)$value === (string)$opt['value'] ? ' selected' : '') . (!empty($opt['premium']) ? ' data-premium="1"' : '') . ($locked ? ' data-locked="1"' : '') . '>' . pe_h($opt['label']) . '</option>';
    }
    echo '</select>';
    echo pe_help($o);
    echo '</div>';
}

/**
 * Icona: Font Awesome, file caricato o URL. Il valore sta in un campo nascosto
 * (`name` per i campi del form, `data-field` per le righe ripetibili).
 */
function pe_icon_input(string $attr, string $value, string $placeholderIcon = 'fa-solid fa-icons'): string
{
    return '<div class="pe-icon-field" data-icon-field data-placeholder-icon="' . pe_h($placeholderIcon) . '">'
        . '<input type="hidden" ' . $attr . ' value="' . pe_h($value) . '">'
        . '</div>';
}
