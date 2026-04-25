(function () {
    const toast = document.getElementById('profileToast');
    let toastTimer = null;

    window.profileToast = function profileToast(message) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2600);
    };

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        const area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.focus();
        area.select();
        document.execCommand('copy');
        area.remove();
        return Promise.resolve();
    }

    document.querySelectorAll('[data-copy-profile]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await copyText(button.dataset.copyProfile || window.location.href);
                window.profileToast('Link profilo copiato.');
            } catch (error) {
                window.profileToast('Non riesco a copiare il link.');
            }
        });
    });

    document.querySelectorAll('[data-share-profile]').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = button.dataset.url || window.location.href;
            const title = button.dataset.title || document.title;
            if (navigator.share) {
                try {
                    await navigator.share({ title, url });
                    return;
                } catch (error) {
                    if (error && error.name === 'AbortError') return;
                }
            }
            try {
                await copyText(url);
                window.profileToast('Share non supportato. Link copiato.');
            } catch (error) {
                window.profileToast('Share non supportato.');
            }
        });
    });

    document.querySelectorAll('[data-profile-collapse]').forEach((section) => {
        const button = section.querySelector('.profile-collapse-title');
        if (!button) return;
        button.addEventListener('click', () => section.classList.toggle('is-open'));
    });
})();
