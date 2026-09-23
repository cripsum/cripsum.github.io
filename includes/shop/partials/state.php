<?php
/**
 * Pagina intera per uno stato senza contenuto: manutenzione (migrazione non
 * applicata) o collezione/download che non esiste.
 *
 * $stateIcon, $stateTitle, $stateText, $stateLink = ['href' => ..., 'label' => ...]
 */
?>
<main class="shop-shell">
    <section class="shop-state">
        <i class="<?php echo shop_h($stateIcon); ?>" aria-hidden="true"></i>
        <h1><?php echo shop_h($stateTitle); ?></h1>
        <p><?php echo shop_h($stateText); ?></p>
        <?php if (!empty($stateLink)): ?>
            <a class="shop-btn shop-btn--primary" href="<?php echo shop_h($stateLink['href']); ?>"><?php echo shop_h($stateLink['label']); ?></a>
        <?php endif; ?>
    </section>
</main>
