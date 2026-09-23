<?php
/**
 * FAQ di una pagina shop. $faq = [['q' => ..., 'a' => ...], ...]
 * Le risposte sono testo semplice: gli indirizzi diventano link da soli.
 */
if (empty($faq)) {
    return;
}
?>
<section class="shop-faq" aria-labelledby="shop-faq-title">
    <h2 id="shop-faq-title"><?php echo shop_h($S['faq']); ?></h2>
    <?php foreach ($faq as $entry): ?>
        <details>
            <summary><?php echo shop_h($entry['q']); ?></summary>
            <p><?php echo shop_linkify($entry['a']); ?></p>
        </details>
    <?php endforeach; ?>
</section>
