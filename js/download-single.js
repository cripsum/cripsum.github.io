(() => {
    'use strict';

    if (window.__cripsumDownloadSingleLoaded) return;
    window.__cripsumDownloadSingleLoaded = true;

    const toast = document.querySelector('[data-download-toast]');
    let toastTimer = null;

    function showToast(message) {
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
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
        } finally {
            textarea.remove();
        }

        return Promise.resolve();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const downloadLink = document.querySelector('[data-download-link]');
        const copyButton = document.querySelector('[data-copy-download]');
        const image = document.querySelector('[data-download-image]');

        if (downloadLink) {
            downloadLink.addEventListener('click', () => {
                const originalText = downloadLink.querySelector('span')?.textContent || 'Download';
                const label = downloadLink.querySelector('span');

                downloadLink.classList.add('is-loading');
                if (label) label.textContent = 'Avvio download...';

                showToast('Download avviato');

                window.setTimeout(() => {
                    downloadLink.classList.remove('is-loading');
                    if (label) label.textContent = originalText;
                }, 1200);
            });
        }

        if (copyButton && downloadLink) {
            copyButton.addEventListener('click', async () => {
                try {
                    const url = new URL(downloadLink.getAttribute('href'), window.location.href).href;
                    await copyToClipboard(url);
                    showToast('Link copiato');
                } catch (err) {
                    console.warn('[Download] Copia link fallita:', err);
                    showToast('Non riesco a copiare il link');
                }
            });
        }

        if (image) {
            image.addEventListener('error', () => {
                image.onerror = null;
                image.src = '/img/Susremaster.png';
            });
        }
    });
})();
