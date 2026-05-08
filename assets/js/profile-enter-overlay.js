/* ============================================================
   Cripsum™ — Profile Enter Overlay
   /assets/js/profile-enter-overlay.js
   Incluso in profile.php solo se hasMusic && !showAudioPlayer
   Caricato con defer — dipende da nessun altro script.
   ============================================================ */
(() => {
    'use strict';

    const TRANSITION_MS   = 560;   // deve combaciare con la durata CSS
    const FALLBACK_MS     = 700;   // setTimeout fallback se transitionend non scatta

    const initEnterOverlay = () => {
        const overlay = document.getElementById('profileEnterOverlay');
        if (!overlay) return;

        const audio = document.getElementById('profileAudio');

        // ── Protezione doppio click ────────────────────────────
        let dismissed = false;

        // ── Cleanup transizione ────────────────────────────────
        const removeOverlay = () => {
            overlay.removeEventListener('transitionend', removeOverlay);
            overlay.remove();
            document.body.classList.remove('profile-enter-active');
        };

        // ── Dismiss: fade-out + avvio audio ───────────────────
        const dismiss = async () => {
            if (dismissed) return;
            dismissed = true;

            // Rimuovi listener subito per evitare secondi click
            overlay.removeEventListener('click',   dismiss);
            overlay.removeEventListener('keydown', onKeydown);
            document.removeEventListener('touchstart', onFirstTouch, { passive: true });

            // 1. Avvia audio — il click è un evento trusted del browser
            if (audio) {
                try {
                    if (audio.paused) {
                        const savedVolume = Number(
                            localStorage.getItem('cripsum.profile.audioVolume') || 0.18
                        );
                        audio.volume = Math.min(Math.max(
                            Number.isFinite(savedVolume) ? savedVolume : 0.18,
                            0
                        ), 1);
                        audio.loop = true;
                        await audio.play();
                    }
                    // Se audio.paused === false l'audio è già avviato: non fare nulla.
                } catch (_err) {
                    // Browser ha bloccato comunque — raro dopo un click diretto,
                    // ma se succede l'overlay sparisce comunque (non blocchiamo la UI)
                }
            }

            // 2. Fade-out overlay
            overlay.classList.add('is-leaving');

            // 3. Rimuovi dal DOM dopo la transizione
            let cleanupFired = false;
            const safeRemove = () => {
                if (cleanupFired) return;
                cleanupFired = true;
                removeOverlay();
            };
            overlay.addEventListener('transitionend', safeRemove, { once: true });
            setTimeout(safeRemove, FALLBACK_MS);
        };

        // ── Tastiera: Enter / Space / qualsiasi tasto ─────────
        const onKeydown = (event) => {
            if (['Enter', ' ', 'ArrowRight', 'Escape'].includes(event.key)) {
                event.preventDefault();
                dismiss();
            }
        };

        // ── Touch (primo touchstart, per Safari mobile) ────────
        // Registriamo sia touchstart che click per coprire tutti i casi.
        // Su mobile click può arrivare con 300ms di ritardo; touchstart è immediato.
        const onFirstTouch = () => {
            dismiss();
        };

        overlay.addEventListener('click',   dismiss);
        overlay.addEventListener('keydown', onKeydown);
        document.addEventListener('touchstart', onFirstTouch, { passive: true, once: true });

        // ── Blocca scroll body mentre l'overlay è attivo ──────
        document.body.classList.add('profile-enter-active');
    };

    // DOMContentLoaded: overlay è già nel DOM (renderizzato server-side)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEnterOverlay, { once: true });
    } else {
        initEnterOverlay();
    }

})();
