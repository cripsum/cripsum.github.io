<?php
/**
 * Menu "Ordina" delle liste shop. $sortOptions = ['valore' => 'Etichetta'].
 *
 * Qui si stampa una <select> normale, che funziona anche senza JavaScript;
 * catalog.js la trasforma nel menu animato e la tiene sincronizzata, cosi'
 * filtri e ordinamento continuano ad ascoltare il suo evento "change".
 */
$sortId = 'shop-sort-' . substr(md5(json_encode($sortOptions)), 0, 6);
?>
<div class="shop-sort" data-shop-select>
    <label class="visually-hidden" for="<?php echo shop_h($sortId); ?>"><?php echo shop_h($S['sort']); ?></label>
    <i class="fa-solid fa-arrow-down-wide-short shop-sort__icon" aria-hidden="true"></i>
    <select id="<?php echo shop_h($sortId); ?>" data-shop-sort>
        <?php foreach ($sortOptions as $value => $label): ?>
            <option value="<?php echo shop_h($value); ?>"><?php echo shop_h($label); ?></option>
        <?php endforeach; ?>
    </select>
</div>
