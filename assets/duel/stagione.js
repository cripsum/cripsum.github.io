/**
 * Duelli 2.0 — la pagina Stagione: gli emblemi dei ranghi e le icone (la
 * pagina la scrive il server, includes/duel/pages/stagione.php).
 */
(function () {
    'use strict';
    if (!window.D2Icons) return;
    document.querySelectorAll('[data-emb]').forEach((el) => { el.innerHTML = window.D2Icons.emblem(el.dataset.emb); });
    window.D2Icons.hydrate(document.getElementById('d2p-main'));
})();
