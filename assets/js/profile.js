(() => {
    'use strict';

    const body = document.body;
    const toast = document.getElementById('bioToast') || document.getElementById('profileToast');
    let toastTimer = null;
    let activityInterval = null;

    const updateStickyBehavior = () => {
        const hero = document.querySelector('.public-profile-body .profile-smart-hero');
        if (!hero) return;

        if (window.innerWidth > 1080) {
            const viewportHeight = window.innerHeight;
            const heroHeight = hero.offsetHeight;
            const offset = 72; // offset top + safety margin

            if (heroHeight > (viewportHeight - offset)) {
                hero.classList.add('hero-no-sticky');
            } else {
                hero.classList.remove('hero-no-sticky');
            }
        } else {
            hero.classList.remove('hero-no-sticky');
        }
    };

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
        document.querySelectorAll('.js-theme-toggle').forEach(btn => {
            const icon = btn.querySelector('i');
            if (icon) icon.className = nextTheme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
            const labelText = btn.querySelector('.theme-label-text');
            if (labelText) {
                const isIt = document.documentElement.lang === 'it';
                labelText.textContent = nextTheme === 'light' 
                    ? (isIt ? 'Modalità chiara' : 'Light Mode')
                    : (isIt ? 'Modalità scura' : 'Dark Mode');
            }
        });
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
            updateStickyBehavior();
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

    const handlePointerMove = (event) => {
        document.documentElement.style.setProperty('--cursor-x', `${event.clientX}px`);
        document.documentElement.style.setProperty('--cursor-y', `${event.clientY}px`);
    };

    const handleRainResize = () => {
        if (window.currentRainInstance) {
            const canvas = document.querySelector('.profile-effects-layer canvas.raindrop-canvas');
            if (canvas) {
                const rect = canvas.getBoundingClientRect();
                window.currentRainInstance.resize(rect.width, rect.height);
            }
        }
    };

    const initProfileEffects = () => {
        const effect = body.dataset.profileEffect || 'none';
        const layer = document.querySelector('.profile-effects-layer');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Clean up previous WebGL rain if it exists
        if (window.currentRainInstance) {
            try {
                window.currentRainInstance.stop();
            } catch (e) {
                console.error('Error stopping RaindropFX:', e);
            }
            window.currentRainInstance = null;
        }
        if (layer) {
            const oldCanvas = layer.querySelector('canvas.raindrop-canvas');
            if (oldCanvas) oldCanvas.remove();
        }
        // Clean up previous CSS foreground drops
        const oldFgLayer = document.querySelector('.profile-effects-foreground-layer');
        if (oldFgLayer) oldFgLayer.remove();

        window.removeEventListener('resize', handleRainResize);

        const needsPointer = ['cursor_glow', 'spotlight'].includes(effect);
        window.removeEventListener('pointermove', handlePointerMove);
        if (needsPointer) {
            window.addEventListener('pointermove', handlePointerMove, { passive: true });
        }

        if (!layer || reduceMotion) return;

        layer.querySelectorAll('.profile-effect-dot').forEach((dot) => dot.remove());

        if (effect === 'glass_rain') {
            // Spawn CSS foreground drops (visible ON TOP of the card)
            // These are pure CSS — no WebGL conflicts.
            const fgLayer = document.createElement('div');
            fgLayer.className = 'profile-effects-foreground-layer';
            document.body.appendChild(fgLayer);

            const fgCount = 18;
            const fgFragment = document.createDocumentFragment();
            for (let i = 0; i < fgCount; i++) {
                const drop = document.createElement('span');
                drop.className = 'profile-fg-drop';
                const size = 18 + Math.random() * 22;
                drop.style.width = size + 'px';
                drop.style.height = (size * 1.3) + 'px';
                drop.style.left = (2 + Math.random() * 96) + '%';
                drop.style.top = (-5 - Math.random() * 12) + '%';
                drop.style.setProperty('--drop-t', (4 + Math.random() * 6) + 's');
                drop.style.setProperty('--drop-d', (Math.random() * 8) + 's');
                fgFragment.appendChild(drop);
            }
            fgLayer.appendChild(fgFragment);

            const hasWebGL2 = !!window.WebGL2RenderingContext && !!document.createElement('canvas').getContext('webgl2');
            if (hasWebGL2) {
                const loadRainLibrary = () => {
                    if (window.RaindropFX) return Promise.resolve();
                    return new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = '/assets/js/raindrop-fx.js?v=4.4.10';
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                };

                loadRainLibrary().then(() => {
                    if (body.dataset.profileEffect !== 'glass_rain') return;
                    
                    // Single canvas for background rain (droplets + sliding drops behind card)
                    // NOTE: Only ONE RaindropFX instance is used because the library shares static
                    // Shader objects internally. Running two instances causes WebGL INVALID_OPERATION
                    // errors ("uniform location is not from the associated program") since each
                    // instance creates its own GL context but the shared shaders mix up uniform locations.
                    // Foreground drops use pure CSS instead (see .profile-fg-drop).
                    let canvas = layer.querySelector('canvas.raindrop-canvas');
                    if (!canvas) {
                        canvas = document.createElement('canvas');
                        canvas.className = 'raindrop-canvas';
                        canvas.style.position = 'absolute';
                        canvas.style.top = '0';
                        canvas.style.left = '0';
                        canvas.style.width = '100%';
                        canvas.style.height = '100%';
                        canvas.style.pointerEvents = 'none';
                        canvas.style.zIndex = '1';
                        layer.appendChild(canvas);
                    }

                    const rect = canvas.getBoundingClientRect();
                    canvas.width = rect.width;
                    canvas.height = rect.height;

                    const bgMedia = document.querySelector('.bio-background__media');
                    let bgSource = '/img/banner_standard_bg.jpg';
                    if (bgMedia && bgMedia.tagName === 'IMG') {
                        bgSource = bgMedia.src;
                    }

                    try {
                        // transparentBackground=false so RaindropFX renders its native
                        // glass refraction effect (blurred background seen through wet glass).
                        // The library loads the background as a static WebGL texture, so
                        // videos/GIFs will freeze — only static image backgrounds are supported.
                        // The editor warns users about this limitation.
                        const raindropFx = new window.RaindropFX({
                            canvas: canvas,
                            background: bgSource,
                            transparentBackground: false,
                            spawnInterval: [0.03, 0.12],
                            spawnSize: [30, 65],
                            spawnLimit: 500,
                            dropletsPerSeconds: 500,
                            dropletSize: [6, 18],
                            mist: false,
                            backgroundBlurSteps: 2,
                            raindropShadowOffset: 0.75,
                            raindropLightBump: 0.6
                        });
                        raindropFx.start();
                        window.currentRainInstance = raindropFx;

                        window.addEventListener('resize', handleRainResize, { passive: true });
                    } catch (err) {
                        console.error('Error starting RaindropFX:', err);
                    }
                }).catch(err => {
                    console.error('Failed to load raindrop-fx library:', err);
                });

                return;
            }
        }

        const particleMap = {
            soft_particles: 25,
            stars: 40,
            glass_rain: 50,
            sakura_falling: 20,
            cyber_grid: 1
        };

        const amount = particleMap[effect] || 0;
        if (!amount) return;

        const fragment = document.createDocumentFragment();
        for (let i = 0; i < amount; i += 1) {
            const dot = document.createElement('span');
            let extraClass = '';
            if (effect === 'glass_rain') {
                extraClass = Math.random() > 0.5 ? ' profile-effect-dot--glass_rain-static' : ' profile-effect-dot--glass_rain-trickle';
            }
            dot.className = `profile-effect-dot profile-effect-dot--${effect}${extraClass}`;
            dot.style.setProperty('--x', `${Math.random() * 100}%`);
            dot.style.setProperty('--y', `${Math.random() * 100}%`);
            dot.style.setProperty('--s', `${0.55 + Math.random() * 1.45}`);
            dot.style.setProperty('--d', `${Math.random() * -12}s`);
            dot.style.setProperty('--t', `${7 + Math.random() * 11}s`);
            if (effect === 'stars') {
                const rand = Math.random();
                const starColor = rand < 0.25 ? 'var(--accent)' : (rand < 0.40 ? 'var(--accent-2, #8b5cf6)' : '#ffffff');
                dot.style.setProperty('--star-color', starColor);
            }
            fragment.appendChild(dot);
        }
        layer.appendChild(fragment);
    };
    window.initProfileEffects = initProfileEffects;

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

    const initProfileThreeDotsDropdown = () => {
        const wrap = document.querySelector('.profile-dropdown-wrap');
        if (!wrap) return;
        const trigger = wrap.querySelector('.js-profile-dropdown-trigger');
        const menu = wrap.querySelector('.profile-dropdown-menu');
        if (!trigger || !menu) return;

        const toggleMenu = (event) => {
            event.stopPropagation();
            const isOpen = wrap.classList.contains('active');
            if (isOpen) {
                closeMenu();
            } else {
                wrap.classList.add('active');
                trigger.setAttribute('aria-expanded', 'true');
            }
        };

        const closeMenu = () => {
            wrap.classList.remove('active');
            trigger.setAttribute('aria-expanded', 'false');
        };

        trigger.addEventListener('click', toggleMenu);

        document.addEventListener('click', (event) => {
            if (!wrap.contains(event.target)) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        menu.querySelectorAll('.profile-dropdown-item:not(.js-theme-toggle)').forEach(item => {
            item.addEventListener('click', () => {
                setTimeout(closeMenu, 150);
            });
        });
    };

    const initProfileNavRedesign = () => {
        const navOverlay = document.getElementById('profileNavOverlay');
        const searchOverlay = document.getElementById('profileSearchOverlay');
        const reportModal = document.getElementById('profileReportModal');

        if (!navOverlay && !searchOverlay && !reportModal) return;

        let activeTrigger = null;

        const openOverlay = (overlay, trigger = null) => {
            if (!overlay) return;
            activeTrigger = trigger;
            overlay.classList.add('is-visible');
            overlay.setAttribute('aria-hidden', 'false');
            body.classList.add('profile-overlay-open');

            // Accessibility: Focus on overlay container or input
            const input = overlay.querySelector('input');
            if (input) {
                setTimeout(() => input.focus(), 100);
            } else {
                const closeBtn = overlay.querySelector('.profile-nav-overlay-close-btn') || overlay.querySelector('.js-close-report') || overlay.querySelector('button');
                if (closeBtn) setTimeout(() => closeBtn.focus(), 100);
            }
        };

        const closeOverlay = (overlay) => {
            if (!overlay) return;
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-hidden', 'true');
            
            // Check if any other overlay is still visible
            const anyVisible = document.querySelector('.profile-nav-overlay.is-visible, .profile-report-modal.is-visible');
            if (!anyVisible) {
                body.classList.remove('profile-overlay-open');
            }

            // Accessibility: Restore focus to trigger
            if (activeTrigger) {
                setTimeout(() => activeTrigger.focus(), 50);
                activeTrigger = null;
            }
        };

        // Navigation Overlay Event Listeners
        const navTriggers = document.querySelectorAll('.js-open-navigation');
        const closeNavBtn = navOverlay?.querySelector('.js-close-navigation');
        const navBackdrop = navOverlay?.querySelector('.profile-nav-overlay-backdrop');

        navTriggers.forEach(btn => {
            btn.addEventListener('click', (e) => {
                openOverlay(navOverlay, btn);
            });
        });

        if (closeNavBtn) {
            closeNavBtn.addEventListener('click', () => closeOverlay(navOverlay));
        }
        if (navBackdrop) {
            navBackdrop.addEventListener('click', () => closeOverlay(navOverlay));
        }

        // Search Overlay Event Listeners
        const searchTriggers = document.querySelectorAll('.js-open-search');
        const closeSearchBtn = searchOverlay?.querySelector('.js-close-search');
        const searchBackdrop = searchOverlay?.querySelector('.profile-nav-overlay-backdrop');
        const searchInput = document.getElementById('profileSearchInput');
        const searchClear = document.getElementById('profileSearchClear');
        const searchResults = document.getElementById('profileSearchResults');

        searchTriggers.forEach(btn => {
            btn.addEventListener('click', (e) => {
                openOverlay(searchOverlay, btn);
            });
        });

        if (closeSearchBtn) {
            closeSearchBtn.addEventListener('click', () => {
                closeOverlay(searchOverlay);
                if (searchInput) searchInput.value = '';
                if (searchClear) searchClear.style.display = 'none';
                if (searchResults) {
                    const isIt = document.documentElement.lang === 'it';
                    searchResults.innerHTML = `<div class="profile-search-status">${isIt ? 'Digita almeno 2 caratteri per iniziare...' : 'Type at least 2 characters to start...'}</div>`;
                }
            });
        }
        if (searchBackdrop) {
            searchBackdrop.addEventListener('click', () => {
                closeOverlay(searchOverlay);
                if (searchInput) searchInput.value = '';
                if (searchClear) searchClear.style.display = 'none';
            });
        }

        // Report Modal Event Listeners
        const reportTriggers = document.querySelectorAll('.js-open-report');
        const closeReportBtn = reportModal?.querySelector('.js-close-report');
        const reportBackdrop = reportModal?.querySelector('.profile-report-backdrop');
        const reportForm = document.getElementById('profileReportForm');
        const reportDetail = document.getElementById('profileReportDetail');

        reportTriggers.forEach(btn => {
            btn.addEventListener('click', (e) => {
                openOverlay(reportModal, btn);
            });
        });

        if (closeReportBtn) {
            closeReportBtn.addEventListener('click', () => closeOverlay(reportModal));
        }
        if (reportBackdrop) {
            reportBackdrop.addEventListener('click', () => closeOverlay(reportModal));
        }

        if (reportForm) {
            reportForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const reasonEl = reportForm.querySelector('input[name="report_reason"]:checked');
                const reason = reasonEl ? reasonEl.value : 'spam';
                const detail = reportDetail ? reportDetail.value.trim() : '';

                // Client-side report simulation
                const isIt = document.documentElement.lang === 'it';
                showToast(isIt ? 'Segnalazione inviata con successo ai moderatori.' : 'Report submitted successfully to moderators.');
                
                // Reset form
                reportForm.reset();
                closeOverlay(reportModal);
            });
        }

        // Search live query logic (AJAX / Fetch)
        if (searchInput && searchResults) {
            let debounceTimer = null;
            let currentQuery = '';

            const performSearch = async (query) => {
                const isIt = document.documentElement.lang === 'it';
                searchResults.innerHTML = `<div class="profile-search-status">${isIt ? 'Ricerca in corso...' : 'Searching...'}</div>`;

                try {
                    const response = await fetch(`/includes/search_users.php?q=${encodeURIComponent(query)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    const data = await response.json();

                    if (searchInput.value.trim() !== query) return; // ignore outdated request

                    if (data.error) {
                        searchResults.innerHTML = `<div class="profile-search-status text-danger">${data.error}</div>`;
                        return;
                    }

                    if (!data.length) {
                        searchResults.innerHTML = `<div class="profile-search-status">${isIt ? 'Nessun utente trovato.' : 'No users found.'}</div>`;
                        return;
                    }

                    // Render results in a nice scrollable list
                    searchResults.innerHTML = data.map((user) => {
                        let roleLabel = user.ruolo === 'owner' ? 'Owner' : (user.ruolo === 'admin' ? 'Admin' : (isIt ? 'Utente' : 'User'));
                        return `
                            <a href="/u/${encodeURIComponent(user.username)}" class="profile-search-item">
                                <img src="${user.pfp}" alt="${user.username}" class="profile-search-avatar" onerror="this.src='/img/default_pfp.png'">
                                <div class="profile-search-info">
                                    <span class="profile-search-username">${user.username}</span>
                                    <span class="profile-search-role ${user.ruolo}">${roleLabel}</span>
                                </div>
                                <i class="fas fa-arrow-up-right-from-square profile-search-arrow"></i>
                            </a>
                        `;
                    }).join('');

                } catch (err) {
                    console.error('Search error:', err);
                    searchResults.innerHTML = `<div class="profile-search-status text-danger">${isIt ? 'Errore nella ricerca, riprova.' : 'Search error, try again.'}</div>`;
                }
            };

            searchInput.addEventListener('input', () => {
                const q = searchInput.value.trim();
                if (searchClear) searchClear.style.display = q.length ? 'block' : 'none';

                clearTimeout(debounceTimer);

                if (q.length < 2) {
                    currentQuery = '';
                    const isIt = document.documentElement.lang === 'it';
                    searchResults.innerHTML = `<div class="profile-search-status">${isIt ? 'Digita almeno 2 caratteri per iniziare...' : 'Type at least 2 characters to start...'}</div>`;
                    return;
                }

                if (q === currentQuery) return;
                currentQuery = q;

                debounceTimer = setTimeout(() => performSearch(q), 300);
            });

            searchClear?.addEventListener('click', () => {
                searchInput.value = '';
                searchClear.style.display = 'none';
                currentQuery = '';
                const isIt = document.documentElement.lang === 'it';
                searchResults.innerHTML = `<div class="profile-search-status">${isIt ? 'Digita almeno 2 caratteri per iniziare...' : 'Type at least 2 characters to start...'}</div>`;
                searchInput.focus();
            });
        }

        // Accessibility: Keyboard trap focus and ESC close
        document.addEventListener('keydown', (e) => {
            const openOverlayEl = document.querySelector('.profile-nav-overlay.is-visible, .profile-report-modal.is-visible');
            if (!openOverlayEl) return;

            if (e.key === 'Escape') {
                closeOverlay(openOverlayEl);
                return;
            }

            if (e.key === 'Tab') {
                // Keep focus inside overlay
                const focusables = openOverlayEl.querySelectorAll('a, button, input, textarea, select, [tabindex="0"]');
                if (focusables.length === 0) return;
                const first = focusables[0];
                const last = focusables[focusables.length - 1];

                if (e.shiftKey) {
                    if (document.activeElement === first) {
                        last.focus();
                        e.preventDefault();
                    }
                } else {
                    if (document.activeElement === last) {
                        first.focus();
                        e.preventDefault();
                    }
                }
            }
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
        initProfileThreeDotsDropdown();
        initProfileAudio();
        initProfileEffects();
        initActivityCarousel();
        updateActivityTimestamps();
        persistDetailsState();
        initProfileNavRedesign();

        updateStickyBehavior();
        window.addEventListener('resize', updateStickyBehavior);
        window.addEventListener('load', updateStickyBehavior);
        setInterval(updateActivityTimestamps, 1000);
        setInterval(refreshDiscordStatus, 30000);

        // Click to Enter Overlay
        const overlay = document.getElementById('clickToEnterOverlay');
        if (overlay) {
            overlay.addEventListener('click', async () => {
                const audio = document.getElementById('profileAudio');
                if (audio) {
                    try {
                        const savedVolume = Number(localStorage.getItem('cripsum.profile.audioVolume') || 0.18);
                        audio.volume = Math.min(Math.max(savedVolume, 0), 1);
                        await audio.play();
                        const playIcon = document.getElementById('profileAudioIcon');
                        if (playIcon) playIcon.className = 'fas fa-pause';
                    } catch (e) {
                        console.error('Autoplay via click to enter failed:', e);
                    }
                }
                overlay.classList.add('is-hidden');
                overlay.addEventListener('transitionend', () => overlay.remove(), { once: true });
            });
        }
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
        if (document.getElementById('clickToEnterOverlay')) return;

        const savedVolume = Number(localStorage.getItem('cripsum.profile.audioVolume') || 0.18);
        audio.volume = Math.min(Math.max(Number.isFinite(savedVolume) ? savedVolume : 0.18, 0), 1);
        audio.loop = true;
        audio.autoplay = true;

        const tryPlay = async (showMessage = false) => {
            try {
                await audio.play();
                return true;
            } catch (error) {
                if (showMessage) showProfileToast('Tocca la pagina per avviare l’audio.');
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
                    showProfileToast('Audio profilo avviato.');
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

/* Premium Custom Tooltips System */
(() => {
    'use strict';

    let activeTooltip = null;
    let tooltipEl = null;
    let showTimeout = null;

    const createTooltip = () => {
        if (tooltipEl) return tooltipEl;
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'profile-custom-tooltip';
        tooltipEl.setAttribute('aria-hidden', 'true');
        
        // Set fallback inline styles to ensure visibility transitions work regardless of cached CSS
        tooltipEl.style.position = 'fixed';
        tooltipEl.style.zIndex = '999999';
        tooltipEl.style.pointerEvents = 'none';
        tooltipEl.style.opacity = '0';
        tooltipEl.style.visibility = 'hidden';
        tooltipEl.style.transform = 'translateY(5px) scale(0.96)';
        tooltipEl.style.transition = 'opacity 0.26s cubic-bezier(0.16, 1, 0.3, 1), transform 0.26s cubic-bezier(0.16, 1, 0.3, 1)';
        
        document.body.appendChild(tooltipEl);
        return tooltipEl;
    };

    const showTooltip = (target, text) => {
        const tooltip = createTooltip();
        tooltip.textContent = text;
        
        tooltip.classList.remove('pos-top', 'pos-bottom');
        
        // Temporarily display block to calculate offset dimensions
        tooltip.style.display = 'block';
        const tWidth = tooltip.offsetWidth;
        const tHeight = tooltip.offsetHeight;
        
        // Position
        const targetRect = target.getBoundingClientRect();
        
        // Position horizontally (center relative to target in viewport coordinates)
        let left = targetRect.left + (targetRect.width - tWidth) / 2;
        const padding = 8;
        if (left < padding) left = padding;
        if (left + tWidth > window.innerWidth - padding) {
            left = window.innerWidth - tWidth - padding;
        }
        
        // Position vertically: try above first
        let top = targetRect.top - tHeight - 8;
        let posClass = 'pos-top';
        
        // If it goes off-screen vertically, put it below
        if (targetRect.top - tHeight - 8 < 0) {
            top = targetRect.bottom + 8;
            posClass = 'pos-bottom';
        }
        
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
        tooltip.classList.add(posClass);
        
        // Ensure visibility is shown before setting transitions
        tooltip.style.visibility = 'visible';
        
        // Force reflow
        tooltip.offsetHeight;
        
        // Animate in
        tooltip.style.opacity = '1';
        tooltip.style.transform = 'translateY(0) scale(1)';
        
        activeTooltip = target;
    };

    const hideTooltip = () => {
        if (tooltipEl) {
            tooltipEl.style.opacity = '0';
            tooltipEl.style.transform = 'translateY(5px) scale(0.96)';
            
            // Wait for transition to complete before setting visibility hidden
            setTimeout(() => {
                if (activeTooltip === null && tooltipEl.style.opacity === '0') {
                    tooltipEl.style.visibility = 'hidden';
                }
            }, 260);
        }
        activeTooltip = null;
    };

    const cancelShow = () => {
        if (showTimeout) {
            clearTimeout(showTimeout);
            showTimeout = null;
        }
    };

    document.addEventListener('mouseover', (e) => {
        const target = e.target.closest('[title], [data-tooltip]');
        if (!target) return;
        
        if (target === activeTooltip) return;
        
        if (target.hasAttribute('title')) {
            const titleVal = target.getAttribute('title');
            if (titleVal && titleVal.trim() !== '') {
                target.setAttribute('data-tooltip', titleVal);
                target.removeAttribute('title');
            } else {
                return;
            }
        }
        
        const tooltipText = target.getAttribute('data-tooltip');
        if (!tooltipText) return;
        
        cancelShow();
        
        // Premium 500ms hover-delay to prevent tooltip spamming while moving mous
        showTimeout = setTimeout(() => {
            showTooltip(target, tooltipText);
            showTimeout = null;
        }, 500);
    });

    document.addEventListener('mouseout', (e) => {
        const related = e.relatedTarget;
        
        if (activeTooltip) {
            if (related && (related === activeTooltip || activeTooltip.contains(related))) {
                return;
            }
            hideTooltip();
        }
        
        cancelShow();
    });

    document.addEventListener('click', () => {
        cancelShow();
        hideTooltip();
    });
    
    window.addEventListener('scroll', () => {
        cancelShow();
        hideTooltip();
    }, { passive: true });

    // Name Sparkles Generator
    let nameSparklesInterval = null;
    const initNameSparkles = () => {
        if (nameSparklesInterval) {
            clearInterval(nameSparklesInterval);
            nameSparklesInterval = null;
        }
        const nameEl = document.querySelector('.profile-display-name[data-name-anim="sparkles"]');
        if (!nameEl) return;

        nameSparklesInterval = setInterval(() => {
            const rect = nameEl.getBoundingClientRect();
            if (rect.width === 0) return;

            const sparkle = document.createElement('div');
            sparkle.className = 'name-sparkle';
            
            const x = Math.random() * rect.width;
            const y = Math.random() * rect.height;
            
            sparkle.style.left = `${x}px`;
            sparkle.style.top = `${y}px`;
            
            const colors = ['#ffffff', 'var(--accent)', 'var(--accent-2, #8b5cf6)'];
            sparkle.style.background = colors[Math.floor(Math.random() * colors.length)];
            
            nameEl.appendChild(sparkle);
            setTimeout(() => sparkle.remove(), 800);
        }, 180);
    };

    initNameSparkles();
    window.initNameSparkles = initNameSparkles;
})();