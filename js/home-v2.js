document.addEventListener('DOMContentLoaded', () => {
    const animatedItems = document.querySelectorAll('.fadeup');

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.12,
            rootMargin: '0px 0px -40px 0px'
        });

        animatedItems.forEach((item) => observer.observe(item));
    } else {
        animatedItems.forEach((item) => item.classList.add('is-visible'));
    }

    document.querySelectorAll('img[data-fallback]').forEach((image) => {
        image.addEventListener('error', () => {
            image.src = image.dataset.fallback;
            image.classList.add('image-fallback');
        }, { once: true });
    });

    const easterEgg = document.querySelector('[data-achievement-id]');
    if (easterEgg) {
        easterEgg.addEventListener('click', () => {
            const achievementId = Number(easterEgg.dataset.achievementId);
            if (Number.isInteger(achievementId) && typeof window.unlockAchievement === 'function') {
                window.unlockAchievement(achievementId);
            }
        });
    }
});
