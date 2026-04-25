(() => {
    'use strict';

    const body = document.body;
    const toast = document.getElementById('bioToast');
    const storageKeys = {
        theme: 'cripsum.bio.theme',
        accent: 'cripsum.bio.accent',
        volume: 'cripsum.bio.volume'
    };

    let toastTimer = null;
    let activityInterval = null;

    const showToast = (message) => {
        if (!toast) return;

        toast.textContent = message;
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2200);
    };

    const hexToRgb = (hex) => {
        const clean = hex.replace('#', '').trim();
        if (!/^[0-9a-fA-F]{6}$/.test(clean)) return '15, 91, 255';

        const value = parseInt(clean, 16);
        const r = (value >> 16) & 255;
        const g = (value >> 8) & 255;
        const b = value & 255;
        return `${r}, ${g}, ${b}`;
    };

    const setAccent = (accent) => {
        document.documentElement.style.setProperty('--accent', accent);
        document.documentElement.style.setProperty('--accent-rgb', hexToRgb(accent));
        localStorage.setItem(storageKeys.accent, accent);

        document.querySelectorAll('.bio-accent').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.accent === accent);
        });
    };

    const setTheme = (theme) => {
        const nextTheme = theme === 'light' ? 'light' : 'dark';
        body.dataset.theme = nextTheme;
        localStorage.setItem(storageKeys.theme, nextTheme);

        const icon = document.querySelector('.js-theme-toggle i');
        if (icon) {
            icon.className = nextTheme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
        }
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
        document.querySelector('.js-copy-profile')?.addEventListener('click', async () => {
            const ok = await copyText(body.dataset.profileUrl || window.location.href);
            showToast(ok ? 'Link profilo copiato.' : 'Non sono riuscito a copiare il link.');
        });

        document.querySelector('.js-share-profile')?.addEventListener('click', async () => {
            const shareData = {
                title: 'Cripsum — Bio',
                text: 'Bio personale su cripsum.com',
                url: body.dataset.profileUrl || window.location.href
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

        document.querySelector('.js-theme-toggle')?.addEventListener('click', () => {
            const nextTheme = body.dataset.theme === 'light' ? 'dark' : 'light';
            setTheme(nextTheme);
            showToast(nextTheme === 'light' ? 'Tema chiaro attivo.' : 'Tema scuro attivo.');
        });

        document.querySelectorAll('.bio-accent').forEach((button) => {
            button.addEventListener('click', () => {
                const accent = button.dataset.accent || '#0f5bff';
                setAccent(accent);
                showToast('Colore aggiornato.');
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
        const card = document.querySelector('.js-tilt-card');
        const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (!card || !canHover) return;

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
    };

    const formatTime = (seconds) => {
        if (!Number.isFinite(seconds)) return '0:00';
        const minutes = Math.floor(seconds / 60);
        const remaining = Math.floor(seconds % 60).toString().padStart(2, '0');
        return `${minutes}:${remaining}`;
    };

    const initAudio = () => {
        const audio = document.getElementById('backgroundAudio');
        const playButton = document.querySelector('.js-audio-toggle');
        const playIcon = document.getElementById('playPauseIcon');
        const volumeButton = document.querySelector('.js-volume-toggle');
        const volumeIcon = document.getElementById('volumeIcon');
        const volumeSlider = document.getElementById('volumeSlider');
        const progressSlider = document.getElementById('progressSlider');
        const currentTime = document.getElementById('currentTime');
        const totalTime = document.getElementById('totalTime');

        if (!audio || !playButton || !playIcon || !volumeButton || !volumeIcon || !volumeSlider || !progressSlider) return;

        let isDragging = false;
        let lastVolume = Number(localStorage.getItem(storageKeys.volume) || volumeSlider.value || 0.12);
        lastVolume = Math.min(Math.max(lastVolume, 0), 1);
        audio.volume = lastVolume;
        volumeSlider.value = String(lastVolume);

        const syncVolumeIcon = () => {
            if (audio.muted || audio.volume === 0) {
                volumeIcon.className = 'fas fa-volume-mute';
            } else if (audio.volume < 0.5) {
                volumeIcon.className = 'fas fa-volume-down';
            } else {
                volumeIcon.className = 'fas fa-volume-up';
            }
        };

        const syncPlayIcon = () => {
            playIcon.className = audio.paused ? 'fas fa-play' : 'fas fa-pause';
        };

        const updateProgress = () => {
            if (!isDragging && Number.isFinite(audio.duration) && audio.duration > 0) {
                progressSlider.value = String((audio.currentTime / audio.duration) * 100);
                if (currentTime) currentTime.textContent = formatTime(audio.currentTime);
            }
        };

        audio.addEventListener('loadedmetadata', () => {
            if (totalTime) totalTime.textContent = formatTime(audio.duration);
        });

        audio.addEventListener('timeupdate', updateProgress);
        audio.addEventListener('play', syncPlayIcon);
        audio.addEventListener('pause', syncPlayIcon);
        audio.addEventListener('ended', syncPlayIcon);

        playButton.addEventListener('click', async () => {
            if (audio.paused) {
                try {
                    await audio.play();
                    showToast('Audio avviato.');
                } catch (error) {
                    console.error('Audio bloccato:', error);
                    showToast('Il browser ha bloccato l’audio. Riprova con un click.');
                }
            } else {
                audio.pause();
            }
        });

        volumeButton.addEventListener('click', () => {
            audio.muted = !audio.muted;
            syncVolumeIcon();
        });

        volumeSlider.addEventListener('input', () => {
            const value = Number(volumeSlider.value);
            audio.volume = value;
            audio.muted = value === 0;
            lastVolume = value;
            localStorage.setItem(storageKeys.volume, String(value));
            syncVolumeIcon();
        });

        const startDrag = () => { isDragging = true; };
        const stopDrag = () => {
            if (!Number.isFinite(audio.duration) || audio.duration <= 0) {
                isDragging = false;
                return;
            }

            audio.currentTime = (Number(progressSlider.value) / 100) * audio.duration;
            isDragging = false;
            updateProgress();
        };

        progressSlider.addEventListener('pointerdown', startDrag);
        progressSlider.addEventListener('pointerup', stopDrag);
        progressSlider.addEventListener('change', stopDrag);
        progressSlider.addEventListener('input', () => {
            if (!Number.isFinite(audio.duration) || audio.duration <= 0) return;
            const previewTime = (Number(progressSlider.value) / 100) * audio.duration;
            if (currentTime) currentTime.textContent = formatTime(previewTime);
        });

        syncVolumeIcon();
        syncPlayIcon();
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
            const response = await fetch(`includes/discord_status.php?discordId=${encodeURIComponent(discordId)}`, {
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
            const key = `cripsum.bio.details.${index}`;
            const saved = localStorage.getItem(key);
            if (saved !== null) detail.open = saved === 'open';

            detail.addEventListener('toggle', () => {
                localStorage.setItem(key, detail.open ? 'open' : 'closed');
            });
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        setTheme(localStorage.getItem(storageKeys.theme) || body.dataset.theme || 'dark');
        setAccent(localStorage.getItem(storageKeys.accent) || '#0f5bff');
        initActions();
        initReveal();
        initTilt();
        initAudio();
        initActivityCarousel();
        updateActivityTimestamps();
        persistDetailsState();

        setInterval(updateActivityTimestamps, 1000);
        setInterval(refreshDiscordStatus, 30000);
    });
})();
