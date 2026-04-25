(() => {
    'use strict';

    const body = document.body;
    const toast = document.getElementById('bioToast') || document.getElementById('profileToast');
    let toastTimer = null;
    let activityInterval = null;

    const showToast = (message) => {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2200);
    };

    window.profileToast = showToast;

    const hexToRgb = (hex) => {
        const clean = String(hex || '').replace('#', '').trim();
        if (!/^[0-9a-fA-F]{6}$/.test(clean)) return '15, 91, 255';
        const value = parseInt(clean, 16);
        const r = (value >> 16) & 255;
        const g = (value >> 8) & 255;
        const b = value & 255;
        return `${r}, ${g}, ${b}`;
    };

    const setAccent = (accent) => {
        const safeAccent = /^#[0-9a-fA-F]{6}$/.test(accent || '') ? accent : '#0f5bff';
        document.documentElement.style.setProperty('--accent', safeAccent);
        document.documentElement.style.setProperty('--accent-rgb', hexToRgb(safeAccent));
        document.documentElement.style.setProperty('--profile-accent', safeAccent);
    };

    const setTheme = (theme) => {
        let nextTheme = theme;
        if (nextTheme === 'auto') {
            nextTheme = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        }
        nextTheme = nextTheme === 'light' ? 'light' : 'dark';
        body.dataset.theme = nextTheme;
        const icon = document.querySelector('.js-theme-toggle i');
        if (icon) icon.className = nextTheme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
    };

    const copyText = async (text) => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return true;
            }
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            const ok = document.execCommand('copy');
            textarea.remove();
            return ok;
        } catch (error) {
            console.error('Errore copia:', error);
            return false;
        }
    };

    const initActions = () => {
        document.querySelectorAll('.js-copy-profile, [data-copy-profile]').forEach((button) => {
            button.addEventListener('click', async () => {
                const text = button.dataset.copyProfile || body.dataset.profileUrl || window.location.href;
                const ok = await copyText(text);
                showToast(ok ? 'Link profilo copiato.' : 'Non sono riuscito a copiare il link.');
            });
        });

        document.querySelectorAll('.js-share-profile, [data-share-profile]').forEach((button) => {
            button.addEventListener('click', async () => {
                const shareData = {
                    title: button.dataset.title || document.title || 'Cripsum profile',
                    text: 'Profilo pubblico su cripsum.com',
                    url: button.dataset.url || body.dataset.profileUrl || window.location.href
                };
                if (navigator.share) {
                    try {
                        await navigator.share(shareData);
                        return;
                    } catch (error) {
                        if (error.name === 'AbortError') return;
                        console.error('Errore share:', error);
                    }
                }
                const ok = await copyText(shareData.url);
                showToast(ok ? 'Share non disponibile: link copiato.' : 'Share non disponibile.');
            });
        });

        document.querySelectorAll('.js-theme-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const nextTheme = body.dataset.theme === 'light' ? 'dark' : 'light';
                setTheme(nextTheme);
                showToast(nextTheme === 'light' ? 'Tema chiaro attivo.' : 'Tema scuro attivo.');
            });
        });
    };

    const initReveal = () => {
        const revealItems = document.querySelectorAll('.js-reveal');
        if (!('IntersectionObserver' in window)) {
            revealItems.forEach((item) => item.classList.add('is-visible'));
            return;
        }
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12 });
        revealItems.forEach((item) => observer.observe(item));
    };

    const initTilt = () => {
        const cards = document.querySelectorAll('.js-tilt-card');
        const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (!cards.length || !canHover) return;

        cards.forEach((card) => {
            let frame = null;
            const handleMove = (event) => {
                const rect = card.getBoundingClientRect();
                const x = event.clientX - rect.left;
                const y = event.clientY - rect.top;
                const rotateX = ((y / rect.height) - 0.5) * -3;
                const rotateY = ((x / rect.width) - 0.5) * 3;
                cancelAnimationFrame(frame);
                frame = requestAnimationFrame(() => {
                    card.style.transform = `perspective(1200px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
                });
            };
            card.addEventListener('mousemove', handleMove);
            card.addEventListener('mouseleave', () => {
                cancelAnimationFrame(frame);
                card.style.transform = 'perspective(1200px) rotateX(0deg) rotateY(0deg)';
            });
        });
    };

    const initActivityCarousel = () => {
        const activities = document.querySelectorAll('.js-activity-item');
        clearInterval(activityInterval);
        if (!activities.length) return;
        let current = 0;
        activities.forEach((item, index) => item.classList.toggle('is-active', index === 0));
        if (activities.length <= 1) return;
        activityInterval = setInterval(() => {
            activities[current].classList.remove('is-active');
            current = (current + 1) % activities.length;
            activities[current].classList.add('is-active');
        }, 4300);
    };

    const updateActivityTimestamps = () => {
        const elements = document.querySelectorAll('.js-activity-timestamp');
        const now = Math.floor(Date.now() / 1000);
        elements.forEach((element) => {
            const startRaw = element.dataset.start;
            const endRaw = element.dataset.end;
            const start = startRaw ? Math.floor(Number(startRaw) / 1000) : null;
            const end = endRaw ? Math.floor(Number(endRaw) / 1000) : null;
            if (start) {
                const elapsed = Math.max(0, now - start);
                const hours = Math.floor(elapsed / 3600);
                const minutes = Math.floor((elapsed % 3600) / 60);
                const seconds = elapsed % 60;
                element.textContent = hours > 0
                    ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')} elapsed`
                    : `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')} elapsed`;
            } else if (end) {
                const remaining = Math.max(0, end - now);
                const hours = Math.floor(remaining / 3600);
                const minutes = Math.floor((remaining % 3600) / 60);
                const seconds = remaining % 60;
                element.textContent = hours > 0
                    ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')} left`
                    : `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')} left`;
            }
        });
    };

    const refreshDiscordStatus = async () => {
        const box = document.getElementById('discordBox');
        const discordId = body.dataset.discordId;
        if (!box || !discordId) return;
        try {
            const response = await fetch(`/includes/discord_status.php?discordId=${encodeURIComponent(discordId)}`, {
                headers: { 'X-Requested-With': 'fetch' },
                cache: 'no-store'
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            box.innerHTML = await response.text();
            initActivityCarousel();
            updateActivityTimestamps();
        } catch (error) {
            console.error('Errore aggiornamento Discord:', error);
        }
    };

    const persistDetailsState = () => {
        const details = document.querySelectorAll('.bio-details');
        details.forEach((detail, index) => {
            const key = `cripsum.profile.details.${index}`;
            const saved = sessionStorage.getItem(key);
            if (saved !== null) detail.open = saved === 'open';
            detail.addEventListener('toggle', () => sessionStorage.setItem(key, detail.open ? 'open' : 'closed'));
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        setAccent(body.dataset.accent || '#0f5bff');
        setTheme(body.dataset.theme || 'dark');
        initActions();
        initReveal();
        initTilt();
        initActivityCarousel();
        updateActivityTimestamps();
        persistDetailsState();
        setInterval(updateActivityTimestamps, 1000);
        setInterval(refreshDiscordStatus, 30000);
    });
})();
