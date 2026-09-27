<?php
/**
 * Pagina senza contenuto: team non ancora pronto (migrazione non
 * applicata) o player che non esiste.
 *
 * $stateIcon, $stateTitle, $stateText, $stateLink = ['href' => ..., 'label' => ...]
 */
?>
<main class="es-shell">
    <section class="es-panel es-empty es-empty--page">
        <i class="<?php echo shop_h($stateIcon); ?>" aria-hidden="true"></i>
        <h1><?php echo shop_h($stateTitle); ?></h1>
        <p><?php echo shop_h($stateText); ?></p>
        <?php if (!empty($stateLink)): ?>
            <a class="es-btn es-btn--primary" href="<?php echo shop_h($stateLink['href']); ?>"><?php echo shop_h($stateLink['label']); ?></a>
        <?php endif; ?>
    </section>
</main>
