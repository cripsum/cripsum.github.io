<?php
/**
 * Chiusura comune delle pagine shop: toast, footer e script di Bootstrap
 * (serve alla navbar).
 */
?>
    <div class="shop-toast" data-shop-toast role="status" aria-live="polite"></div>

    <?php include __DIR__ . '/../../scroll_indicator.php'; ?>
    <?php include __DIR__ . '/../../' . ($shopLang === 'en' ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
