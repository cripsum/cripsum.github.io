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

    const setTheme = (theme, animate = false) => {
        let nextTheme = theme;
        if (nextTheme === 'auto') {
            nextTheme = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        }
        nextTheme = nextTheme === 'light' ? 'light' : 'dark';

        if (animate) {
            body.classList.remove('profile-theme-changing');
            void body.offsetWidth;
            body.classList.add('profile-theme-changing');
            window.setTimeout(() => body.classList.remove('profile-theme-changing'), 650);
        }

        body.dataset.theme = nextTheme;
        localStorage.setItem('cripsum.profile.viewerTheme', nextTheme);
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
                setTheme(nextTheme, true);
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


    const initQrModal = () => {
        const modal = document.getElementById('profileQrModal');
        if (!modal) return;
        const image = modal.querySelector('.profile-qr-image');
        const openButtons = document.querySelectorAll('.js-open-qr');
        const closeButtons = modal.querySelectorAll('.js-close-qr');

        const open = () => {
            if (image && image.dataset.qrSrc) {
                image.src = image.dataset.qrSrc + (image.dataset.qrSrc.includes('?') ? '&' : '?') + 't=' + Date.now();
            }
            modal.classList.add('is-visible');
            document.body.classList.add('profile-modal-open');
            modal.setAttribute('aria-hidden', 'false');
        };

        const close = () => {
            modal.classList.remove('is-visible');
            document.body.classList.remove('profile-modal-open');
            modal.setAttribute('aria-hidden', 'true');
        };

        openButtons.forEach((button) => button.addEventListener('click', open));
        closeButtons.forEach((button) => button.addEventListener('click', close));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') close();
        });
    };


    const formatTime = (seconds) => {
        if (!Number.isFinite(seconds)) return '0:00';
        const minutes = Math.floor(seconds / 60);
        const remaining = Math.floor(seconds % 60).toString().padStart(2, '0');
        return `${minutes}:${remaining}`;
    };

    const initProfileAudio = () => {
        const audio = document.getElementById('profileAudio');
        if (!audio) return;

        const playButton = document.querySelector('.js-profile-audio-toggle');
        const playIcon = document.getElementById('profileAudioIcon');
        const volumeButton = document.querySelector('.js-profile-volume-toggle');
        const volumeIcon = document.getElementById('profileVolumeIcon');
        const volumeSlider = document.getElementById('profileVolumeSlider');
        const progressSlider = document.getElementById('profileAudioProgress');
        const currentTime = document.getElementById('profileAudioCurrent');
        const totalTime = document.getElementById('profileAudioTotal');

        if (!playButton || !progressSlider || !volumeSlider) return;

        let dragging = false;
        const savedVolume = Number(localStorage.getItem('cripsum.profile.audioVolume') || volumeSlider.value || 0.18);
        audio.volume = Math.min(Math.max(savedVolume, 0), 1);
        volumeSlider.value = String(audio.volume);

        const syncIcons = () => {
            if (playIcon) playIcon.className = audio.paused ? 'fas fa-play' : 'fas fa-pause';
            if (volumeIcon) {
                volumeIcon.className = audio.muted || audio.volume === 0
                    ? 'fas fa-volume-mute'
                    : audio.volume < 0.5 ? 'fas fa-volume-down' : 'fas fa-volume-up';
            }
        };

        const syncProgress = () => {
            if (!dragging && Number.isFinite(audio.duration) && audio.duration > 0) {
                progressSlider.value = String((audio.currentTime / audio.duration) * 100);
                if (currentTime) currentTime.textContent = formatTime(audio.currentTime);
            }
        };

        audio.addEventListener('loadedmetadata', () => {
            if (totalTime) totalTime.textContent = formatTime(audio.duration);
        });
        audio.addEventListener('timeupdate', syncProgress);
        audio.addEventListener('play', syncIcons);
        audio.addEventListener('pause', syncIcons);

        playButton.addEventListener('click', async () => {
            if (audio.paused) {
                try {
                    await audio.play();
                    showToast('Audio avviato.');
                } catch (error) {
                    showToast('Il browser ha bloccato l’audio. Clicca di nuovo.');
                }
            } else {
                audio.pause();
            }
        });

        volumeButton?.addEventListener('click', () => {
            audio.muted = !audio.muted;
            syncIcons();
        });

        volumeSlider.addEventListener('input', () => {
            const value = Number(volumeSlider.value);
            audio.volume = value;
            audio.muted = value === 0;
            localStorage.setItem('cripsum.profile.audioVolume', String(value));
            syncIcons();
        });

        progressSlider.addEventListener('pointerdown', () => { dragging = true; });
        const seek = () => {
            if (Number.isFinite(audio.duration) && audio.duration > 0) {
                audio.currentTime = (Number(progressSlider.value) / 100) * audio.duration;
            }
            dragging = false;
            syncProgress();
        };
        progressSlider.addEventListener('pointerup', seek);
        progressSlider.addEventListener('change', seek);
        progressSlider.addEventListener('input', () => {
            if (!Number.isFinite(audio.duration) || audio.duration <= 0) return;
            if (currentTime) currentTime.textContent = formatTime((Number(progressSlider.value) / 100) * audio.duration);
        });

        syncIcons();
    };

    const initProfileEffects = () => {
        const effect = body.dataset.profileEffect || 'none';
        if (effect === 'cursor_glow') {
            window.addEventListener('pointermove', (event) => {
                document.documentElement.style.setProperty('--cursor-x', `${event.clientX}px`);
                document.documentElement.style.setProperty('--cursor-y', `${event.clientY}px`);
            }, { passive: true });
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




    const fitDropdownMenu = (menu) => {
        if (!menu) return;
        const padding = 10;

        menu.classList.add('profile-dropdown-safe');
        menu.style.maxWidth = `calc(100vw - ${padding * 2}px)`;

        requestAnimationFrame(() => {
            const rect = menu.getBoundingClientRect();

            if (rect.right > window.innerWidth - padding) {
                menu.classList.add('dropdown-menu-end', 'profile-navbar-menu-end');
                menu.style.right = '0';
                menu.style.left = 'auto';
            }

            if (rect.left < padding) {
                menu.style.left = `${padding - rect.left}px`;
                menu.style.right = 'auto';
            }
        });
    };

    const initNavbarDropdownAlignment = () => {
        const navbarMenus = document.querySelectorAll(
            '.navbar .dropdown:last-child > .dropdown-menu, ' +
            '.navbar-nav .dropdown:last-child > .dropdown-menu, ' +
            '.navbarutenti .dropdown:last-child > .dropdown-menu, ' +
            '.dropdownutenti:last-child > .dropdown-menu'
        );

        navbarMenus.forEach((menu) => {
            menu.classList.add('dropdown-menu-end', 'profile-navbar-menu-end');
        });

        document.querySelectorAll('.dropdown, .dropdownutenti, .nav-item').forEach((item) => {
            const menu = item.querySelector(':scope > .dropdown-menu, .dropdown-menu');
            if (!menu) return;

            item.addEventListener('shown.bs.dropdown', () => fitDropdownMenu(menu));
            item.addEventListener('click', () => {
                if (menu.classList.contains('show')) fitDropdownMenu(menu);
            }, true);
        });

        window.addEventListener('resize', () => {
            document.querySelectorAll('.dropdown-menu.show').forEach(fitDropdownMenu);
        }, { passive: true });
    };

    const initDropdownFallback = () => {
        if (window.bootstrap && window.bootstrap.Dropdown) return;

        const toggles = document.querySelectorAll('[data-bs-toggle="dropdown"], [data-toggle="dropdown"], .dropdown-toggle');
        if (!toggles.length) return;

        const closeAll = (except = null) => {
            document.querySelectorAll('.dropdown-menu.show').forEach((menu) => {
                if (menu === except) return;
                menu.classList.remove('show');
                menu.closest('.dropdown, .dropdownutenti')?.classList.remove('show');
            });
        };

        toggles.forEach((toggle) => {
            toggle.addEventListener('click', (event) => {
                const parent = toggle.closest('.dropdown, .dropdownutenti, li, .nav-item') || toggle.parentElement;
                const menu = parent ? parent.querySelector('.dropdown-menu') : null;
                if (!menu) return;

                event.preventDefault();
                event.stopPropagation();
                const willOpen = !menu.classList.contains('show');
                closeAll(menu);
                menu.classList.toggle('show', willOpen);
                parent.classList.toggle('show', willOpen);
                if (willOpen) fitDropdownMenu(menu);
            });
        });

        document.addEventListener('click', () => closeAll());
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeAll();
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        setAccent(body.dataset.accent || '#0f5bff');
        setTheme(localStorage.getItem('cripsum.profile.viewerTheme') || body.dataset.theme || 'dark');
        initActions();
        initNavbarDropdownAlignment();
        initDropdownFallback();
        initReveal();
        initTilt();
        initQrModal();
        initProfileAudio();
        initProfileEffects();
        initActivityCarousel();
        updateActivityTimestamps();
        persistDetailsState();
        setInterval(updateActivityTimestamps, 1000);
        setInterval(refreshDiscordStatus, 30000);
    });
})();
