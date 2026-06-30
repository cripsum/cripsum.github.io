document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-copy-target]').forEach(button => {
        button.addEventListener('click', async () => {
            const target = document.querySelector(button.dataset.copyTarget);
            const text = target?.innerText?.trim();
            if (!text) return;

            const original = button.textContent;

            try {
                await navigator.clipboard.writeText(text);
                button.textContent = 'Copied';
                setTimeout(() => {
                    button.textContent = original;
                }, 1400);
            } catch {
                button.textContent = 'Select';
                target.focus?.();
                setTimeout(() => {
                    button.textContent = original;
                }, 1400);
            }
        });
    });
});
