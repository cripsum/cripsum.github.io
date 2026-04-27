(() => {
    'use strict';

    if (window.__cripsumDownloadSingleLoaded) return;
    window.__cripsumDownloadSingleLoaded = true;

    let toastTimer = null;

    function getToast() {
        return document.querySelector('[data-download-toast]');
    }

    function showToast(message) {
        const toast = getToast();
        if (!toast) return;

        const text = toast.querySelector('span');
        if (text) text.textContent = message || 'Download avviato';

        toast.hidden = false;
        requestAnimationFrame(() => toast.classList.add('is-visible'));

        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => {
            toast.classList.remove('is-visible');

            window.setTimeout(() => {
                toast.hidden = true;
            }, 180);
        }, 2200);
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        try {
            document.execCommand('copy');
        } finally {
            textarea.remove();
        }

        return Promise.resolve();
    }

    function getDownloadUrl(downloadLink) {
        const href = downloadLink?.getAttribute('href') || '';
        return new URL(href, window.location.href).href;
    }

    function init() {
        const downloadLink = document.querySelector('[data-download-link]');
        const copyButton = document.querySelector('[data-copy-download]');
        const image = document.querySelector('[data-download-image]');

        if (downloadLink) {
            downloadLink.addEventListener('click', () => {
                const label = downloadLink.querySelector('span');
                const originalText = label?.textContent || 'Download';

                downloadLink.classList.add('is-loading');

                if (label) {
                    label.textContent = 'Avvio download...';
                }

                showToast('Download avviato');

                window.setTimeout(() => {
                    downloadLink.classList.remove('is-loading');

                    if (label) {
                        label.textContent = originalText;
                    }
                }, 900);
            });
        }

        if (copyButton && downloadLink) {
            copyButton.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();

                try {
                    await copyToClipboard(getDownloadUrl(downloadLink));
                    showToast('Link copiato');
                } catch (err) {
                    console.warn('[Download] Copia link fallita:', err);
                    showToast('Non riesco a copiare il link');
                }
            });
        }

        document.querySelectorAll('a.download-secondary-btn[href]').forEach((link) => {
            link.addEventListener('click', () => {
                showToast('Apro la pagina download');
            });
        });

        if (image) {
            image.addEventListener('error', () => {
                image.onerror = null;
                image.src = '/img/Susremaster.png';
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
