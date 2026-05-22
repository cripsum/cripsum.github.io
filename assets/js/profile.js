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
                showToast(ok ? 'Profile link copied.' : 'Failed to copy link.');
            });
        });

        document.querySelectorAll('.js-share-profile, [data-share-profile]').forEach((button) => {
            button.addEventListener('click', async () => {
                const shareData = {
                    title: button.dataset.title || document.title || 'Cripsum profile',
                    text: 'Public profile on cripsum.com',
                    url: button.dataset.url || body.dataset.profileUrl || window.location.href
                };
                if (navigator.share) {
                    try {
                        await navigator.share(shareData);
                        return;
                    } catch (error) {
                        if (error.name === 'AbortError') return;
                        console.error('Share error:', error);
                    }
                }
                const ok = await copyText(shareData.url);
                showToast(ok ? 'Share not available: link copied.' : 'Share not available.');
            });
        });

        document.querySelectorAll('.js-theme-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const nextTheme = body.dataset.theme === 'light' ? 'dark' : 'light';
                setTheme(nextTheme, true);
                showToast(nextTheme === 'light' ? 'Light theme activated.' : 'Dark theme activated.');
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
            console.error('Discord update error:', error);
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
                    showToast('Audio started.');
                } catch (error) {
                    showToast('The browser blocked the audio. Click again.');
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
        const layer = document.querySelector('.profile-effects-layer');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const needsPointer = ['cursor_glow', 'spotlight'].includes(effect);
        if (needsPointer) {
            window.addEventListener('pointermove', (event) => {
                document.documentElement.style.setProperty('--cursor-x', `${event.clientX}px`);
                document.documentElement.style.setProperty('--cursor-y', `${event.clientY}px`);
            }, { passive: true });
        }

        if (!layer || reduceMotion) return;

        layer.querySelectorAll('.profile-effect-dot').forEach((dot) => dot.remove());

        const particleMap = {
            soft_particles: 18,
            stars: 34,
            digital_noise: 44,
            glass_rain: 24,
            aurora: 10,
            gradient_waves: 12
        };

        const amount = particleMap[effect] || 0;
        if (!amount) return;

        const fragment = document.createDocumentFragment();
        for (let i = 0; i < amount; i += 1) {
            const dot = document.createElement('span');
            dot.className = `profile-effect-dot profile-effect-dot--${effect}`;
            dot.style.setProperty('--x', `${Math.random() * 100}%`);
            dot.style.setProperty('--y', `${Math.random() * 100}%`);
            dot.style.setProperty('--s', `${0.55 + Math.random() * 1.45}`);
            dot.style.setProperty('--d', `${Math.random() * -12}s`);
            dot.style.setProperty('--t', `${7 + Math.random() * 11}s`);
            fragment.appendChild(dot);
        }
        layer.appendChild(fragment);
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

/* Cripsum Custom Profiles 3.0 runtime */
(() => {
    'use strict';

    const $ = (selector, parent = document) => parent.querySelector(selector);
    const $$ = (selector, parent = document) => Array.from(parent.querySelectorAll(selector));

    const readJson = (id, fallback = {}) => {
        const node = document.getElementById(id);
        if (!node) return fallback;
        try {
            return JSON.parse(node.textContent || '');
        } catch (_) {
            return fallback;
        }
    };

    const runtime = readJson('profileV3Runtime', {});
    const body = document.body;
    const profileId = Number(runtime.profileId || body.dataset.profileId || 0);
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const toast = (message) => {
        if (typeof window.profileToast === 'function') window.profileToast(message);
    };

    const postJson = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.ok === false) throw new Error(data.message || 'Request failed.');
        return data;
    };

    class ProfileCanvasEngine {
        constructor(canvas, effect, config) {
            this.canvas = canvas;
            this.ctx = canvas ? canvas.getContext('2d', { alpha: true }) : null;
            this.effect = effect || 'none';
            this.config = {
                speed: Number(config.speed || 1),
                density: Number(config.density || 55),
                color: String(config.color || '#ffffff'),
                opacity: Number(config.opacity || 0.55),
                fps: Number(config.fps || 40),
            };
            this.items = [];
            this.running = false;
            this.frame = null;
            this.last = 0;
            this.w = 0;
            this.h = 0;
            this.resize = this.resize.bind(this);
            this.tick = this.tick.bind(this);
        }

        start() {
            if (!this.canvas || !this.ctx || this.effect === 'none' || reducedMotion || this.running) return;
            this.running = true;
            this.resize();
            window.addEventListener('resize', this.resize, { passive: true });
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) this.stop(false);
                else this.start();
            });
            this.frame = requestAnimationFrame(this.tick);
        }

        stop(clear = true) {
            this.running = false;
            cancelAnimationFrame(this.frame);
            window.removeEventListener('resize', this.resize);
            if (clear && this.ctx) this.ctx.clearRect(0, 0, this.w, this.h);
        }

        resize() {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            this.w = window.innerWidth;
            this.h = window.innerHeight;
            this.canvas.width = Math.floor(this.w * dpr);
            this.canvas.height = Math.floor(this.h * dpr);
            this.canvas.style.width = this.w + 'px';
            this.canvas.style.height = this.h + 'px';
            this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            this.seed();
        }

        seed() {
            const count = Math.max(5, Math.min(220, this.config.density));
            this.items = Array.from({ length: count }, (_, i) => this.makeItem(i));
        }

        makeItem(i) {
            const rand = (min, max) => min + Math.random() * (max - min);
            return {
                x: rand(0, this.w),
                y: rand(0, this.h),
                z: rand(0.4, 1.8),
                r: rand(1, 4.8),
                vx: rand(-0.35, 0.35),
                vy: rand(0.25, 1.4),
                a: rand(0.25, 1),
                rot: rand(0, Math.PI * 2),
                char: String.fromCharCode(0x30A0 + (i % 86)),
            };
        }

        wrap(p) {
            if (p.x < -40) p.x = this.w + 40;
            if (p.x > this.w + 40) p.x = -40;
            if (p.y < -60) p.y = this.h + 60;
            if (p.y > this.h + 60) p.y = -60;
        }

        color(alpha = 1) {
            const hex = this.config.color.replace('#', '');
            const value = /^[0-9a-fA-F]{6}$/.test(hex) ? parseInt(hex, 16) : 0xffffff;
            return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${Math.max(0, Math.min(1, alpha * this.config.opacity))})`;
        }

        tick(time) {
            if (!this.running || document.hidden) return;
            const minDelta = 1000 / Math.max(18, Math.min(60, this.config.fps));
            if (time - this.last < minDelta) {
                this.frame = requestAnimationFrame(this.tick);
                return;
            }
            const dt = Math.min(2.2, (time - this.last) / 16.67 || 1);
            this.last = time;
            this.draw(dt);
            this.frame = requestAnimationFrame(this.tick);
        }

        draw(dt) {
            const ctx = this.ctx;
            ctx.clearRect(0, 0, this.w, this.h);
            ctx.save();
            ctx.globalCompositeOperation = ['sparks', 'fireflies', 'orbs'].includes(this.effect) ? 'lighter' : 'source-over';
            for (const p of this.items) {
                this.drawItem(ctx, p, dt);
                this.wrap(p);
            }
            ctx.restore();
        }

        drawItem(ctx, p, dt) {
            const speed = this.config.speed;
            if (this.effect === 'matrix') {
                p.y += (10 + p.z * 18) * speed * dt;
                ctx.font = `${12 + p.z * 6}px monospace`;
                ctx.fillStyle = this.color(p.a);
                ctx.fillText(p.char, p.x, p.y);
                if (p.y > this.h + 24) {
                    p.y = -24;
                    p.x = Math.floor(Math.random() * this.w / 18) * 18;
                    p.char = String.fromCharCode(0x30A0 + Math.floor(Math.random() * 86));
                }
                return;
            }

            if (this.effect === 'rain') {
                p.y += (10 + p.z * 22) * speed * dt;
                p.x += p.vx * speed * dt;
                ctx.strokeStyle = this.color(0.52 * p.a);
                ctx.lineWidth = Math.max(1, p.z);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                ctx.lineTo(p.x - 8 * p.z, p.y + 18 * p.z);
                ctx.stroke();
                return;
            }

            if (this.effect === 'sparks') {
                p.y -= (0.8 + p.z * 1.8) * speed * dt;
                p.x += Math.sin(p.y * 0.02) * 0.55;
                ctx.strokeStyle = this.color(p.a);
                ctx.lineWidth = 1.2;
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                ctx.lineTo(p.x + p.vx * 22, p.y + 10);
                ctx.stroke();
                return;
            }

            if (this.effect === 'confetti') {
                p.y += (0.8 + p.z * 1.7) * speed * dt;
                p.x += Math.sin((p.y + p.rot) * 0.025) * 1.2;
                p.rot += 0.05 * speed * dt;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.fillStyle = this.color(p.a);
                ctx.fillRect(-p.r * 1.4, -p.r, p.r * 2.8, p.r * 1.7);
                ctx.restore();
                return;
            }

            if (this.effect === 'sakura') {
                p.y += (0.45 + p.z) * speed * dt;
                p.x += Math.sin((p.y + p.rot) * 0.018) * 1.4;
                p.rot += 0.025 * speed * dt;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.fillStyle = this.color(0.82 * p.a);
                ctx.beginPath();
                ctx.ellipse(0, 0, p.r * 1.7, p.r * 0.75, 0, 0, Math.PI * 2);
                ctx.fill();
                ctx.restore();
                return;
            }

            if (this.effect === 'smoke') {
                p.y -= (0.18 + p.z * 0.45) * speed * dt;
                p.x += Math.sin((p.y + p.rot) * 0.01) * 0.4;
                const gradient = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r * 6);
                gradient.addColorStop(0, this.color(0.18 * p.a));
                gradient.addColorStop(1, 'rgba(255,255,255,0)');
                ctx.fillStyle = gradient;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r * 6, 0, Math.PI * 2);
                ctx.fill();
                return;
            }

            if (this.effect === 'stars') {
                p.a += Math.sin(Date.now() * 0.001 + p.x) * 0.002;
                ctx.fillStyle = this.color(Math.max(0.18, Math.min(1, p.a)));
                ctx.beginPath();
                ctx.arc(p.x, p.y, Math.max(0.7, p.r * 0.58), 0, Math.PI * 2);
                ctx.fill();
                return;
            }

            if (this.effect === 'orbs' || this.effect === 'fireflies') {
                p.x += (p.vx * 1.8 + Math.sin(Date.now() * 0.001 + p.y) * 0.12) * speed * dt;
                p.y += (p.vy * 0.45 * (this.effect === 'orbs' ? -1 : 1)) * speed * dt;
                const radius = this.effect === 'orbs' ? p.r * 3.2 : p.r * 1.4;
                const gradient = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, radius * 5);
                gradient.addColorStop(0, this.color(0.75 * p.a));
                gradient.addColorStop(1, 'rgba(255,255,255,0)');
                ctx.fillStyle = gradient;
                ctx.beginPath();
                ctx.arc(p.x, p.y, radius * 5, 0, Math.PI * 2);
                ctx.fill();
                return;
            }

            p.y += (0.35 + p.z) * speed * dt;
            p.x += Math.sin((p.y + p.rot) * 0.02) * 0.55;
            ctx.fillStyle = this.color(p.a);
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    const initEnterOverlay = (canvasEngine) => {
        const overlay = $('#profileEnterOverlay');
        if (!overlay) {
            canvasEngine?.start();
            return;
        }

        const remember = runtime.enterRemember !== false && body.dataset.enterRemember !== '0';
        const key = `cripsum.profile.enter.${profileId}`;
        if (remember && sessionStorage.getItem(key) === '1') {
            overlay.remove();
            canvasEngine?.start();
            return;
        }

        body.classList.add('profile-enter-locked');
        const unlock = async () => {
            overlay.classList.add('is-hidden');
            body.classList.remove('profile-enter-locked');
            if (remember) sessionStorage.setItem(key, '1');
            $$('video').forEach((video) => {
                if (video.muted || video.classList.contains('bio-background__media')) video.play().catch(() => {});
            });
            const audio = $('#profileAudio');
            if (audio && audio.dataset.autoplay === '1') {
                audio.volume = Math.min(Math.max(Number(localStorage.getItem('cripsum.profile.audioVolume') || 0.18), 0), 1);
                audio.play().catch(() => toast('Audio blocked by browser. Tap play again.'));
            }
            canvasEngine?.start();
            setTimeout(() => overlay.remove(), 480);
        };

        $('#profileEnterButton', overlay)?.addEventListener('click', unlock);
        overlay.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') unlock();
        });
    };

    const initBackgroundParallax = () => {
        const bg = $('.bio-background-v3[data-parallax="1"]');
        if (!bg || reducedMotion) return;
        let frame = null;
        window.addEventListener('pointermove', (event) => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                const x = ((event.clientX / window.innerWidth) - 0.5) * -18;
                const y = ((event.clientY / window.innerHeight) - 0.5) * -18;
                bg.style.setProperty('--parallax-x', `${x}px`);
                bg.style.setProperty('--parallax-y', `${y}px`);
            });
        }, { passive: true });
    };

    const initLinkAnalytics = () => {
        $$('[data-profile-link-id]').forEach((link) => {
            link.addEventListener('click', () => {
                const linkId = Number(link.dataset.profileLinkId || 0);
                if (!profileId || !linkId) return;
                postJson('/api/profile_analytics.php', {
                    profile_id: profileId,
                    event_type: 'click',
                    link_id: linkId,
                    metadata: { title: link.dataset.profileLinkTitle || '' },
                }).catch(() => {});
            }, { passive: true });
        });

        $$('.js-share-profile').forEach((button) => {
            button.addEventListener('click', () => {
                if (profileId) postJson('/api/profile_analytics.php', { profile_id: profileId, event_type: 'share' }).catch(() => {});
            }, { passive: true });
        });

        $$('.js-open-qr').forEach((button) => {
            button.addEventListener('click', () => {
                if (profileId) postJson('/api/profile_analytics.php', { profile_id: profileId, event_type: 'qr' }).catch(() => {});
            }, { passive: true });
        });
    };

    const initReactions = async () => {
        const bar = $('[data-profile-reactions]');
        if (!bar || !profileId) return;
        const paint = (counts) => {
            $$('[data-reaction]', bar).forEach((button) => {
                const count = counts?.[button.dataset.reaction] ?? 0;
                const small = $('small', button);
                if (small) small.textContent = String(count);
            });
        };

        fetch(`/api/profile_reactions.php?profile_id=${encodeURIComponent(profileId)}`, { headers: { 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((data) => data.ok && paint(data.counts))
            .catch(() => {});

        $$('[data-reaction]', bar).forEach((button) => {
            button.addEventListener('click', async () => {
                try {
                    const data = await postJson('/api/profile_reactions.php', {
                        profile_id: profileId,
                        reaction: button.dataset.reaction,
                    });
                    paint(data.counts);
                    toast('Reaction saved.');
                } catch (error) {
                    toast(error.message || 'Reaction failed.');
                }
            });
        });
    };

    const initCountdowns = () => {
        const nodes = $$('[data-countdown]');
        if (!nodes.length) return;
        const update = () => {
            const now = Date.now();
            nodes.forEach((node) => {
                const target = Date.parse(node.dataset.countdown || '');
                const strong = $('strong', node);
                if (!strong || !Number.isFinite(target)) return;
                const diff = Math.max(0, target - now);
                const days = Math.floor(diff / 86400000);
                const hours = Math.floor((diff % 86400000) / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);
                strong.textContent = days > 0 ? `${days}d ${hours}h` : `${hours}h ${minutes}m`;
            });
        };
        update();
        setInterval(update, 30000);
    };

    const initContactBlocks = () => {
        $$('[data-profile-contact]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                try {
                    await postJson('/api/profile_analytics.php', { profile_id: profileId, event_type: 'contact' });
                    toast('Contact request noted.');
                    form.reset();
                } catch (error) {
                    toast(error.message || 'Could not send.');
                }
            });
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        const canvasEngine = new ProfileCanvasEngine(
            $('#profileCanvasEngine'),
            runtime.canvasEffect || body.dataset.canvasEffect || 'none',
            runtime.canvasConfig || {}
        );

        initEnterOverlay(canvasEngine);
        initBackgroundParallax();
        initLinkAnalytics();
        initReactions();
        initCountdowns();
        initContactBlocks();

        if (!$('#profileEnterOverlay')) canvasEngine.start();
    });
})();

/* V2.9.2 - autoplay hidden profile music when player is disabled */
(() => {
    'use strict';

    const showProfileToast = (message) => {
        if (typeof window.profileToast === 'function') {
            window.profileToast(message);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const audio = document.getElementById('profileAudio');
        if (!audio || audio.dataset.autoplay !== '1') return;

        const savedVolume = Number(localStorage.getItem('cripsum.profile.audioVolume') || 0.18);
        audio.volume = Math.min(Math.max(Number.isFinite(savedVolume) ? savedVolume : 0.18, 0), 1);
        audio.loop = true;
        audio.autoplay = true;

        const tryPlay = async (showMessage = false) => {
            try {
                await audio.play();
                return true;
            } catch (error) {
                if (showMessage) showProfileToast('Tap anywhere to enable audio.');
                return false;
            }
        };

        const unlockOnInteraction = () => {
            let armed = true;
            const events = ['pointerdown', 'click', 'keydown', 'touchstart'];
            const cleanup = () => events.forEach((eventName) => document.removeEventListener(eventName, unlock, true));
            const unlock = async () => {
                if (!armed || !audio.paused) return;
                armed = false;
                const ok = await tryPlay(false);
                if (ok) {
                    cleanup();
                    showProfileToast('Profile audio started.');
                } else {
                    armed = true;
                }
            };
            events.forEach((eventName) => document.addEventListener(eventName, unlock, true));
        };

        tryPlay(true).then((ok) => {
            if (!ok) unlockOnInteraction();
        });
    });
})();
