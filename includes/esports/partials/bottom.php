<?php
/**
 * Chiusura della pagina del team: avviso a comparsa, footer e script di
 * Bootstrap (serve alla navbar).
 */
?>
    <div class="es-toast" data-es-toast role="status" aria-live="polite"></div>

    <?php include __DIR__ . '/../../scroll_indicator.php'; ?>
    <?php include __DIR__ . '/../../' . ($esLang === 'en' ? 'footer-en.php' : 'footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
