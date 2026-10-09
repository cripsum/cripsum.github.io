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
$apClassic = !cripsum_theme_is_next();

$apCopy = $apIsEn
    ? [
        'title'  => 'Appearance',
        'desc'   => 'Choose how the site looks on this device.',
        'box'    => 'Classic theme',
        'toggle' => 'Use the classic theme',
        'hint'   => 'The site has a new look: cleaner and darker, with new animations. If you prefer the old one you can keep it for a while longer: the classic theme stays available for some time, then it will be removed.',
        'device' => 'The choice is saved in this browser only. Profiles, the profile editor and the Lootbox opening look the same in both themes.',
        'save'   => 'Save',
    ]
    : [
        'title'  => 'Aspetto',
        'desc'   => 'Scegli come vedere il sito su questo dispositivo.',
        'box'    => 'Tema classico',
        'toggle' => 'Usa il tema classico',
        'hint'   => 'Il sito ha un aspetto nuovo: più pulito e scuro, con animazioni nuove. Se preferisci quello di prima puoi tenerlo ancora per un po\': il tema classico resta disponibile per qualche tempo, poi verrà tolto.',
        'device' => 'La scelta resta salvata solo in questo browser. Profili, editor del profilo e apertura della Lootbox sono uguali nei due temi.',
        'save'   => 'Salva',
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
                <h3><i class="fa-solid fa-clock-rotate-left"></i> <?php echo $apH($apCopy['box']); ?></h3>

                <label class="auth-check">
                    <input type="checkbox" name="theme_classic" value="1" <?php echo $apClassic ? 'checked' : ''; ?>>
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
        </form>
    </article>
</div>
