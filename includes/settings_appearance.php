<?php

/**
 * Pannello impostazioni: aspetto del sito.
 *
 * Incluso da it/impostazioni.php e en/impostazioni.php, che preparano
 * $settingsLanguage. La gestione del POST sta in
 * cripsum_theme_settings_handle_post(), chiamata dalla pagina insieme alle
 * altre.
 *
 * Come gli altri pannelli riusa le classi di assets/auth/auth.css: questa
 * pagina non ha un foglio di stile proprio.
 */

require_once __DIR__ . '/theme.php';

$apIsEn = ($settingsLanguage ?? 'it') === 'en';
$apLang = $apIsEn ? 'en' : 'it';
$apNext = cripsum_theme_is_next();

$apCopy = $apIsEn
    ? [
        'title'  => 'Appearance',
        'desc'   => 'Choose how the site looks on this device.',
        'box'    => 'Experimental theme',
        'toggle' => 'Use the new theme',
        'hint'   => 'A cleaner, darker look with new animations. It is being rolled out one page at a time: for now it changes the homepage, and the other pages stay as they are until they are ready.',
        'device' => 'The choice is saved in this browser only. You can go back to the classic theme at any time.',
        'save'   => 'Save',
        'open'   => 'Open the homepage',
    ]
    : [
        'title'  => 'Aspetto',
        'desc'   => 'Scegli come vedere il sito su questo dispositivo.',
        'box'    => 'Tema sperimentale',
        'toggle' => 'Usa il tema nuovo',
        'hint'   => 'Un aspetto più pulito e scuro, con animazioni nuove. Arriva una pagina alla volta: per ora cambia la homepage, le altre pagine restano come sono finché non sono pronte.',
        'device' => 'La scelta resta salvata solo in questo browser. Puoi tornare al tema classico quando vuoi.',
        'save'   => 'Salva',
        'open'   => 'Apri la homepage',
    ];

/** Scorciatoia locale: auth_h() è definita in security_helpers.php. */
$apH = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<!-- Tab: Aspetto -->
<div class="settings-tab-content" id="tab-appearance">
    <article class="settings-panel auth-reveal">
        <div class="settings-panel__head">
            <h2><?php echo $apH($apCopy['title']); ?></h2>
            <p><?php echo $apH($apCopy['desc']); ?></p>
        </div>

        <form method="post" action="#appearance" class="auth-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_appearance">

            <div class="account-data-box">
                <h3><i class="fa-solid fa-flask"></i> <?php echo $apH($apCopy['box']); ?></h3>

                <label class="auth-check">
                    <input type="checkbox" name="theme_next" value="1" <?php echo $apNext ? 'checked' : ''; ?>>
                    <span><?php echo $apH($apCopy['toggle']); ?></span>
                </label>
                <p class="account-data-note account-data-note--muted">
                    <?php echo $apH($apCopy['hint']); ?>
                </p>
                <p class="account-data-note account-data-note--muted">
                    <?php echo $apH($apCopy['device']); ?>
                </p>
            </div>

            <button class="auth-btn auth-btn--primary" type="submit"
                    style="width:auto;padding:10px 22px;">
                <i class="fa-solid fa-floppy-disk"></i>
                <span><?php echo $apH($apCopy['save']); ?></span>
            </button>

            <?php if ($apNext): ?>
                <a class="auth-btn auth-btn--soft" href="/<?php echo $apLang; ?>/home"
                   style="width:auto;padding:10px 22px;margin-top:.6rem;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span><?php echo $apH($apCopy['open']); ?></span>
                </a>
            <?php endif; ?>
        </form>
    </article>
</div>
