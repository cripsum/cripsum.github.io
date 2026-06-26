(() => {
    'use strict';

    const body = document.body;
    
    const getActiveAudioElement = () => {
        const useBgVideoAudio = body.dataset.bgUseVideoAudio === '1';
        if (useBgVideoAudio) {
            const video = document.getElementById('profileBgVideo');
            if (video) return video;
        }
        return document.getElementById('profileAudio');
    };
    window.getActiveAudioElement = getActiveAudioElement;

    const toast = document.getElementById('bioToast') || document.getElementById('profileToast');
    let toastTimer = null;
    let activityInterval = null;

    const getResolvedVolume = (profileUrl, defaultVol) => {
        const volumeKey = 'cripsum.profile.audioVolume.' + profileUrl;
        const lastDefaultVolumeKey = 'cripsum.profile.lastDefaultVolume.' + profileUrl;
        
        const lastDefaultVolume = localStorage.getItem(lastDefaultVolumeKey) !== null
            ? Number(localStorage.getItem(lastDefaultVolumeKey))
            : null;
            
        let targetVolume;
        if (lastDefaultVolume === null || lastDefaultVolume !== defaultVol) {
            targetVolume = defaultVol;
            localStorage.setItem(lastDefaultVolumeKey, String(defaultVol));
            localStorage.setItem(volumeKey, String(defaultVol));
        } else {
            targetVolume = localStorage.getItem(volumeKey) !== null
                ? Number(localStorage.getItem(volumeKey))
                : defaultVol;
        }
        return Math.min(Math.max(targetVolume, 0), 1);
    };

    const updateStickyBehavior = () => {
        const hero = document.querySelector('.public-profile-body .profile-smart-hero');
        const wrapper = document.querySelector('.public-profile-body .profile-smart-hero-wrapper');
        if (!hero || !wrapper) return;

        if (window.innerWidth > 1080) {
            const viewportHeight = window.innerHeight;
            const heroHeight = hero.offsetHeight;
            const offset = 72; // offset top + safety margin

            if (heroHeight > (viewportHeight - offset)) {
                wrapper.classList.add('hero-no-sticky');
            } else {
                wrapper.classList.remove('hero-no-sticky');
            }
        } else {
            wrapper.classList.remove('hero-no-sticky');
        }
    };

    const updateProfileViewportFit = () => {
        if (!body.classList.contains('public-profile-body')) return;

        const page = document.getElementById('bioPage') || document.querySelector('.public-profile-body .bio-page');
        if (!page) return;

        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        const pageHeight = Math.ceil(page.getBoundingClientRect().height);
        body.classList.toggle('profile-fits-viewport', pageHeight <= viewportHeight);
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
            if (icon) icon.className = nextTheme === 'light' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            const labelText = btn.querySelector('.theme-label-text');
            if (labelText) {
                labelText.textContent = nextTheme === 'light' ? 'Light Mode' : 'Dark Mode';
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
                showToast(ok ? 'Profile link copied.' : 'Could not copy link.');
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
            const getSpeed = () => parseInt(card.dataset.tiltSpeed ?? 400, 10);
            const getGlare = () => parseFloat(card.dataset.tiltGlare ?? 0);

            let glareInner = null;
            
            const checkAndCreateGlare = () => {
                const glareVal = getGlare();
                if (glareVal <= 0) {
                    const existing = card.querySelector('.js-tilt-glare');
                    if (existing) existing.style.display = 'none';
                    glareInner = null;
                    return;
                }
                card.style.position = 'relative';
                let glareContainer = card.querySelector('.js-tilt-glare');
                if (!glareContainer) {
                    glareContainer = document.createElement('div');
                    glareContainer.className = 'js-tilt-glare';
                    glareContainer.style.cssText = 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden; pointer-events: none; border-radius: inherit; z-index: 10; -webkit-mask-image: -webkit-radial-gradient(white, black);';
                    
                    glareInner = document.createElement('div');
                    glareInner.className = 'js-tilt-glare-inner';
                    glareInner.style.cssText = `position: absolute; top: 50%; left: 50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,${glareVal}) 0%, rgba(255,255,255,0) 60%); border-radius: 50%; transform: translate(-50%, -50%); pointer-events: none; opacity: 0; transition: opacity ${getSpeed()}ms cubic-bezier(.03,.98,.52,.99), transform ${getSpeed()}ms cubic-bezier(.03,.98,.52,.99);`;
                    
                    glareContainer.appendChild(glareInner);
                    card.appendChild(glareContainer);
                } else {
                    glareContainer.style.display = 'block';
                    glareInner = glareContainer.querySelector('.js-tilt-glare-inner');
                    if (glareInner) {
                        glareInner.style.background = `radial-gradient(circle, rgba(255,255,255,${glareVal}) 0%, rgba(255,255,255,0) 60%)`;
                    }
                }
            };

            let frame = null;

            card.addEventListener('mouseenter', () => {
                const enabled = card.dataset.tiltEnabled !== '0';
                if (!enabled) return;
                checkAndCreateGlare();

                card.style.transition = 'none';
                if (glareInner) {
                    glareInner.style.transition = 'none';
                    glareInner.style.opacity = '1';
                }
            });

            const handleMove = (event) => {
                const enabled = card.dataset.tiltEnabled !== '0';
                if (!enabled) return;

                const maxTilt = parseFloat(card.dataset.tiltMax ?? 15);
                const zoom = parseFloat(card.dataset.tiltZoom ?? 1.05);

                const rect = card.getBoundingClientRect();
                const x = event.clientX - rect.left;
                const y = event.clientY - rect.top;
                
                const rotateX = ((y / rect.height) - 0.5) * -maxTilt;
                const rotateY = ((x / rect.width) - 0.5) * maxTilt;

                const glareX = ((x / rect.width) - 0.5) * -100;
                const glareY = ((y / rect.height) - 0.5) * -100;

                cancelAnimationFrame(frame);
                frame = requestAnimationFrame(() => {
                    card.style.transform = `perspective(1200px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(${zoom}, ${zoom}, ${zoom})`;
                    if (glareInner) {
                        glareInner.style.transform = `translate(-50%, -50%) translate(${glareX}%, ${glareY}%)`;
                    }
                });
            };

            card.addEventListener('mousemove', handleMove);
            card.addEventListener('mouseleave', () => {
                const enabled = card.dataset.tiltEnabled !== '0';
                if (!enabled) {
                    card.style.transform = 'none';
                    return;
                }
                const speed = getSpeed();
                cancelAnimationFrame(frame);
                card.style.transition = `transform ${speed}ms cubic-bezier(.03,.98,.52,.99), box-shadow ${speed}ms cubic-bezier(.03,.98,.52,.99)`;
                card.style.transform = 'perspective(1200px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
                if (glareInner) {
                    glareInner.style.transition = `opacity ${speed}ms cubic-bezier(.03,.98,.52,.99), transform ${speed}ms cubic-bezier(.03,.98,.52,.99)`;
                    glareInner.style.opacity = '0';
                    glareInner.style.transform = 'translate(-50%, -50%) translate(0%, 0%)';
                }
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

    const initFloatingAudioBtn = () => {
        const container = document.querySelector('[data-floating-audio]');
        const audio = document.getElementById('profileAudio');
        const video = document.getElementById('profileBgVideo');
        if (!container) return;

        // Force correct positioning inline to override cached CSS position issues
        const btnPos = [...container.classList].find(c => c.startsWith('position-'))?.replace('position-', '') || 'bottom-right';
        container.style.setProperty('position', 'fixed', 'important');
        container.style.setProperty('z-index', '999999', 'important');
        container.style.setProperty('transform', 'none', 'important');
        container.style.setProperty('flex-direction', btnPos.includes('left') ? 'row' : 'row-reverse', 'important');
        container.style.setProperty('display', 'flex', 'important');
        container.style.setProperty('align-items', 'center', 'important');
        
        container.style.setProperty('top', btnPos.startsWith('top') ? '24px' : 'auto', 'important');
        container.style.setProperty('bottom', btnPos.startsWith('bottom') ? '24px' : 'auto', 'important');
        container.style.setProperty('left', btnPos.includes('left') ? '24px' : 'auto', 'important');
        container.style.setProperty('right', btnPos.includes('right') ? '24px' : 'auto', 'important');

        const btn = container.querySelector('.profile-floating-audio-btn');
        const icon = btn.querySelector('i');
        const slider = container.querySelector('.profile-floating-audio-slider');

        // Set initial volume based on localStorage or default
        const defaultVolume = Number(container.dataset.defaultVolume || 0.18);
        const profileUrl = body.dataset.profileUrl || 'global';
        const resolvedVolume = getResolvedVolume(profileUrl, defaultVolume);
        
        if (audio) audio.volume = resolvedVolume;
        if (video) video.volume = resolvedVolume;
        slider.value = String(resolvedVolume);

        const updateUI = () => {
            const activeAudio = window.getActiveAudioElement();
            if (!activeAudio) return;
            const isMuted = activeAudio.muted || activeAudio.volume === 0;
            if (isMuted) {
                icon.className = 'fa-solid fa-volume-xmark';
                slider.value = '0';
            } else {
                icon.className = activeAudio.volume < 0.5 ? 'fa-solid fa-volume-low' : 'fa-solid fa-volume-high';
                slider.value = String(activeAudio.volume);
            }
        };

        // Initialize state
        updateUI();

        // Sync on audio events (in case volume changes elsewhere or muted by browser)
        if (audio) audio.addEventListener('volumechange', updateUI);
        if (video) video.addEventListener('volumechange', updateUI);

        // Click to mute/unmute or play if paused
        const toggleMute = async () => {
            const activeAudio = window.getActiveAudioElement();
            if (!activeAudio) return;
            if (activeAudio.paused) {
                activeAudio.muted = false;
                if (activeAudio.volume === 0) {
                    activeAudio.volume = defaultVolume;
                }
                try {
                    const otherAudio = activeAudio === video ? audio : video;
                    if (otherAudio && typeof otherAudio.pause === 'function') {
                        otherAudio.pause();
                    }
                    await activeAudio.play();
                } catch (err) {
                    console.warn('Playback failed:', err);
                }
            } else {
                activeAudio.muted = !activeAudio.muted;
                if (!activeAudio.muted && activeAudio.volume === 0) {
                    activeAudio.volume = defaultVolume;
                }
            }
            updateUI();
        };

        // Mobile touch variables
        let pressTimeout = null;
        let longPressTriggered = false;

        btn.addEventListener('click', (e) => {
            if (longPressTriggered) {
                longPressTriggered = false;
                return;
            }
            toggleMute();
        });

        // Mobile Long Press Handling
        btn.addEventListener('touchstart', (e) => {
            longPressTriggered = false;
            pressTimeout = setTimeout(() => {
                longPressTriggered = true;
                container.classList.add('show-slider');
            }, 600); // 600ms hold
        }, { passive: true });

        btn.addEventListener('touchend', () => {
            if (pressTimeout) {
                clearTimeout(pressTimeout);
            }
        });

        btn.addEventListener('touchmove', () => {
            if (pressTimeout) {
                clearTimeout(pressTimeout);
            }
        }, { passive: true });

        // Hide slider when clicking outside container
        document.addEventListener('click', (e) => {
            if (!container.contains(e.target)) {
                container.classList.remove('show-slider');
            }
        });

        // Volume slider input
        slider.addEventListener('input', () => {
            const activeAudio = window.getActiveAudioElement();
            if (!activeAudio) return;
            const val = Number(slider.value);
            activeAudio.volume = val;
            activeAudio.muted = val === 0;
            const volumeKey = 'cripsum.profile.audioVolume.' + (body.dataset.profileUrl || 'global');
            localStorage.setItem(volumeKey, String(val));
            updateUI();
        });
    };

    const initProfileAudio = () => {
        const audio = document.getElementById('profileAudio');
        const video = document.getElementById('profileBgVideo');
        const elements = [audio, video].filter(Boolean);
        if (elements.length === 0) return;

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
        const defaultVol = Number((audio || video).dataset.defaultVolume || 0.18);
        const profileUrl = body.dataset.profileUrl || 'global';
        const resolvedVolume = getResolvedVolume(profileUrl, defaultVol);
        
        elements.forEach(el => {
            el.volume = resolvedVolume;
        });
        volumeSlider.value = String(resolvedVolume);

        const syncIcons = () => {
            const activeAudio = window.getActiveAudioElement();
            if (!activeAudio) return;
            const isPaused = activeAudio.paused;
            const container = document.querySelector('.profile-audio-player');
            if (container) {
                container.classList.toggle('audio-playing', !isPaused);
            }
            if (playIcon) playIcon.className = isPaused ? 'fa-solid fa-play' : 'fa-solid fa-pause';
            if (volumeIcon) {
                volumeIcon.className = activeAudio.muted || activeAudio.volume === 0
                    ? 'fa-solid fa-volume-xmark'
                    : activeAudio.volume < 0.5 ? 'fa-solid fa-volume-low' : 'fa-solid fa-volume-high';
            }
        };

        const syncProgress = () => {
            const activeAudio = window.getActiveAudioElement();
            if (!activeAudio) return;
            if (!dragging && Number.isFinite(activeAudio.duration) && activeAudio.duration > 0) {
                progressSlider.value = String((activeAudio.currentTime / activeAudio.duration) * 100);
                if (currentTime) currentTime.textContent = formatTime(activeAudio.currentTime);
            }
        };

        elements.forEach(el => {
            el.addEventListener('loadedmetadata', () => {
                if (el === window.getActiveAudioElement() && totalTime) {
                    totalTime.textContent = formatTime(el.duration);
                }
            });
            el.addEventListener('timeupdate', () => {
                if (el === window.getActiveAudioElement()) {
                    syncProgress();
                }
            });
            el.addEventListener('play', () => {
                if (el === window.getActiveAudioElement()) {
                    syncIcons();
                }
            });
            el.addEventListener('pause', () => {
                if (el === window.getActiveAudioElement()) {
                    syncIcons();
                }
            });
        });

        playButton.addEventListener('click', async () => {
            const activeAudio = window.getActiveAudioElement();
            if (!activeAudio) return;
            if (activeAudio.paused) {
                try {
                    const otherAudio = activeAudio === video ? audio : video;
                    if (otherAudio && typeof otherAudio.pause === 'function') {
                        otherAudio.pause();
                    }
                    if (activeAudio === video) {
                        activeAudio.muted = false;
                    }
                    await activeAudio.play();
                    showToast('Audio started.');
                } catch (error) {
                    showToast('Browser blocked audio. Click again.');
                }
            } else {
                activeAudio.pause();
            }
        });

        volumeButton?.addEventListener('click', () => {
            const activeAudio = window.getActiveAudioElement();
            if (!activeAudio) return;
            activeAudio.muted = !activeAudio.muted;
            syncIcons();
        });

        volumeSlider.addEventListener('input', () => {
            const activeAudio = window.getActiveAudioElement();
            if (!activeAudio) return;
            const value = Number(volumeSlider.value);
            activeAudio.volume = value;
            activeAudio.muted = value === 0;
            const volumeKey = 'cripsum.profile.audioVolume.' + (body.dataset.profileUrl || 'global');
            localStorage.setItem(volumeKey, String(value));
            syncIcons();
        });

        progressSlider.addEventListener('pointerdown', () => { dragging = true; });
        const seek = () => {
            const activeAudio = window.getActiveAudioElement();
            if (!activeAudio) return;
            if (Number.isFinite(activeAudio.duration) && activeAudio.duration > 0) {
                activeAudio.currentTime = (Number(progressSlider.value) / 100) * activeAudio.duration;
            }
            dragging = false;
            syncProgress();
        };
        progressSlider.addEventListener('pointerup', seek);
        progressSlider.addEventListener('change', seek);
        progressSlider.addEventListener('input', () => {
            const activeAudio = window.getActiveAudioElement();
            if (!activeAudio) return;
            if (!Number.isFinite(activeAudio.duration) || activeAudio.duration <= 0) return;
            if (currentTime) currentTime.textContent = formatTime((Number(progressSlider.value) / 100) * activeAudio.duration);
        });

        // Initialize duration if metadata is already loaded
        const currentActive = window.getActiveAudioElement();
        if (currentActive && Number.isFinite(currentActive.duration) && totalTime) {
            totalTime.textContent = formatTime(currentActive.duration);
        }

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

    const initCursorEffects = () => {
        // Clean up previous elements and animation frames
        if (window.currentFollowerEl) {
            window.currentFollowerEl.remove();
            window.currentFollowerEl = null;
        }
        if (window.followerRafId) {
            cancelAnimationFrame(window.followerRafId);
            window.followerRafId = null;
        }
        if (window.currentTrailCanvas) {
            window.currentTrailCanvas.remove();
            window.currentTrailCanvas = null;
        }
        if (window.trailRafId) {
            cancelAnimationFrame(window.trailRafId);
            window.trailRafId = null;
        }
        if (window.trailMoveHandler) {
            window.removeEventListener('pointermove', window.trailMoveHandler);
            window.trailMoveHandler = null;
        }
        if (window.followerMoveHandler) {
            window.removeEventListener('pointermove', window.followerMoveHandler);
            window.followerMoveHandler = null;
        }

        const cursorEffect = body.dataset.cursorEffect || 'none';
        if (cursorEffect === 'none') return;

        if (cursorEffect === 'follower') {
            const follower = document.createElement('div');
            follower.className = 'cursor-follower-dot';
            follower.style.position = 'fixed';
            follower.style.width = '20px';
            follower.style.height = '20px';
            follower.style.border = '2px solid var(--accent)';
            follower.style.borderRadius = '50%';
            follower.style.backgroundColor = 'rgba(var(--accent-rgb), 0.1)';
            follower.style.pointerEvents = 'none';
            follower.style.zIndex = '99999';
            follower.style.transform = 'translate(-50%, -50%)';
            follower.style.left = '-100px';
            follower.style.top = '-100px';
            document.body.appendChild(follower);
            window.currentFollowerEl = follower;

            let mouseX = -100, mouseY = -100;
            let currentX = -100, currentY = -100;

            window.followerMoveHandler = (e) => {
                mouseX = e.clientX;
                mouseY = e.clientY;
            };
            window.addEventListener('pointermove', window.followerMoveHandler, { passive: true });

            function tickFollower() {
                if (currentX === -100 && currentY === -100) {
                    currentX = mouseX;
                    currentY = mouseY;
                } else {
                    currentX += (mouseX - currentX) * 0.15;
                    currentY += (mouseY - currentY) * 0.15;
                }
                follower.style.left = `${currentX}px`;
                follower.style.top = `${currentY}px`;
                window.followerRafId = requestAnimationFrame(tickFollower);
            }
            tickFollower();
        } else if (['trail', 'trail_stars', 'trail_hearts'].includes(cursorEffect)) {
            const canvas = document.createElement('canvas');
            canvas.className = 'cursor-trail-canvas';
            canvas.style.position = 'fixed';
            canvas.style.top = '0';
            canvas.style.left = '0';
            canvas.style.width = '100vw';
            canvas.style.height = '100vh';
            canvas.style.pointerEvents = 'none';
            canvas.style.zIndex = '99998';
            document.body.appendChild(canvas);
            window.currentTrailCanvas = canvas;

            const ctx = canvas.getContext('2d');
            let width = window.innerWidth;
            let height = window.innerHeight;
            canvas.width = width;
            canvas.height = height;

            const handleResize = () => {
                width = window.innerWidth;
                height = window.innerHeight;
                canvas.width = width;
                canvas.height = height;
            };
            window.addEventListener('resize', handleResize, { passive: true });

            const particles = [];
            window.trailMoveHandler = (e) => {
                const accentColor = getComputedStyle(document.body).getPropertyValue('--accent').trim() || '#0f5bff';
                if (cursorEffect === 'trail') {
                    particles.push({
                        x: e.clientX,
                        y: e.clientY,
                        size: Math.random() * 5 + 3,
                        color: accentColor,
                        alpha: 1,
                        vx: (Math.random() - 0.5) * 1,
                        vy: (Math.random() - 0.5) * 1
                    });
                } else if (cursorEffect === 'trail_stars') {
                    const starColor = Math.random() > 0.4 ? accentColor : '#fbbf24';
                    particles.push({
                        x: e.clientX,
                        y: e.clientY,
                        size: Math.random() * 8 + 6,
                        color: starColor,
                        alpha: 1,
                        vx: (Math.random() - 0.5) * 2,
                        vy: Math.random() * 1.5 + 0.5,
                        rotation: Math.random() * Math.PI * 2,
                        vRotation: (Math.random() - 0.5) * 0.1
                    });
                } else if (cursorEffect === 'trail_hearts') {
                    const heartColors = ['#ef4444', '#f43f5e', '#ec4899', accentColor];
                    const randColor = heartColors[Math.floor(Math.random() * heartColors.length)];
                    particles.push({
                        x: e.clientX,
                        y: e.clientY,
                        size: Math.random() * 8 + 6,
                        color: randColor,
                        alpha: 1,
                        vx: (Math.random() - 0.5) * 1.5,
                        vy: -(Math.random() * 1.5 + 0.5)
                    });
                }
            };
            window.addEventListener('pointermove', window.trailMoveHandler, { passive: true });

            function drawStar(ctx, cx, cy, spikes, outerRadius, innerRadius, color) {
                let rot = Math.PI / 2 * 3;
                let x = cx;
                let y = cy;
                let step = Math.PI / spikes;

                ctx.beginPath();
                ctx.moveTo(cx, cy - outerRadius);
                for (let i = 0; i < spikes; i++) {
                    x = cx + Math.cos(rot) * outerRadius;
                    y = cy + Math.sin(rot) * outerRadius;
                    ctx.lineTo(x, y);
                    rot += step;

                    x = cx + Math.cos(rot) * innerRadius;
                    y = cy + Math.sin(rot) * innerRadius;
                    ctx.lineTo(x, y);
                    rot += step;
                }
                ctx.lineTo(cx, cy - outerRadius);
                ctx.closePath();
                ctx.fillStyle = color;
                ctx.fill();
            }

            function drawHeart(ctx, x, y, size, color) {
                ctx.beginPath();
                const topCurveHeight = size * 0.3;
                ctx.moveTo(x, y + topCurveHeight);
                ctx.bezierCurveTo(
                    x - size / 2, y - topCurveHeight,
                    x - size, y + topCurveHeight,
                    x, y + size
                );
                ctx.bezierCurveTo(
                    x + size, y + topCurveHeight,
                    x + size / 2, y - topCurveHeight,
                    x, y + topCurveHeight
                );
                ctx.closePath();
                ctx.fillStyle = color;
                ctx.fill();
            }

            function tickTrail() {
                ctx.clearRect(0, 0, width, height);
                for (let i = particles.length - 1; i >= 0; i--) {
                    const p = particles[i];
                    p.x += p.vx;
                    p.y += p.vy;
                    p.alpha -= 0.02;
                    p.size *= 0.97;
                    if (p.alpha <= 0 || p.size <= 0.5) {
                        particles.splice(i, 1);
                        continue;
                    }
                    ctx.save();
                    ctx.globalAlpha = p.alpha;
                    if (cursorEffect === 'trail') {
                        ctx.fillStyle = p.color;
                        ctx.beginPath();
                        ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                        ctx.fill();
                    } else if (cursorEffect === 'trail_stars') {
                        ctx.translate(p.x, p.y);
                        p.rotation += p.vRotation;
                        ctx.rotate(p.rotation);
                        drawStar(ctx, 0, 0, 5, p.size, p.size / 2, p.color);
                    } else if (cursorEffect === 'trail_hearts') {
                        drawHeart(ctx, p.x, p.y - p.size / 2, p.size, p.color);
                    }
                    ctx.restore();
                }
                window.trailRafId = requestAnimationFrame(tickTrail);
            }
            tickTrail();
        } else if (cursorEffect === 'cat_follower') {
            const cat = document.createElement('div');
            cat.className = 'profile-cat-follower';
            cat.innerHTML = '🐈';
            document.body.appendChild(cat);
            window.currentFollowerEl = cat;

            let mouseX = -100, mouseY = -100;
            let currentX = -100, currentY = -100;

            window.followerMoveHandler = (e) => {
                mouseX = e.clientX;
                mouseY = e.clientY;
            };
            window.addEventListener('pointermove', window.followerMoveHandler, { passive: true });

            function tickCat() {
                if (currentX === -100 && currentY === -100) {
                    currentX = mouseX;
                    currentY = mouseY;
                } else {
                    const dx = mouseX - currentX;
                    const dy = mouseY - currentY;
                    currentX += dx * 0.06;
                    currentY += dy * 0.06;
                    let scaleX = 1;
                    if (Math.abs(dx) > 0.5) {
                        scaleX = dx > 0 ? 1 : -1;
                    }
                    cat.style.transform = `translate(-50%, -50%) scaleX(${scaleX})`;
                }
                cat.style.left = `${currentX}px`;
                cat.style.top = `${currentY}px`;
                window.followerRafId = requestAnimationFrame(tickCat);
            }
            tickCat();
        }
    };
    window.initCursorEffects = initCursorEffects;

    // Custom cursor image follower (for animated GIF cursors that CSS can't animate)
    const initCustomCursorImage = () => {
        // Clean up previous custom cursor follower
        if (window.customCursorEl) {
            window.customCursorEl.remove();
            window.customCursorEl = null;
        }
        if (window.customCursorRafId) {
            cancelAnimationFrame(window.customCursorRafId);
            window.customCursorRafId = null;
        }
        if (window.customCursorMoveHandler) {
            window.removeEventListener('pointermove', window.customCursorMoveHandler);
            window.customCursorMoveHandler = null;
        }
        if (window.customCursorOverHandler) {
            window.removeEventListener('pointerover', window.customCursorOverHandler);
            window.customCursorOverHandler = null;
        }
        // Remove previous cursor-none class
        body.classList.remove('custom-cursor-js-active');

        const standardUrl = body.dataset.cursorCustomUrl;
        const hoverUrl = body.dataset.cursorCustomHoverUrl;
        const standardCenter = body.dataset.cursorCustomCenter === '1';
        const hoverCenter = body.dataset.cursorCustomHoverCenter === '1';
        const standardAnimated = standardUrl && /\.(gif)$/i.test(standardUrl);
        const hoverAnimated = hoverUrl && /\.(gif)$/i.test(hoverUrl);

        // Only use JS follower if at least one of the cursors is animated (GIF)
        if (!standardAnimated && !hoverAnimated) return;

        const cursorEl = document.createElement('img');
        cursorEl.style.cssText = 'position:fixed;pointer-events:none;z-index:999999;width:64px;height:64px;image-rendering:pixelated;left:-100px;top:-100px;transform:translate(0, 0);will-change:transform;display:none;';
        body.appendChild(cursorEl);
        window.customCursorEl = cursorEl;

        let mouseX = -100, mouseY = -100;

        const updateActiveCursorState = (target) => {
            const isHoveringClickable = target && typeof target.closest === 'function' && !!target.closest('a, button, select, [role="button"], input[type="submit"], input[type="button"], input[type="reset"]');

            let activeUrl = '';
            let activeCenter = false;
            let activeAnimated = false;

            if (isHoveringClickable && hoverUrl) {
                activeUrl = hoverUrl;
                activeCenter = hoverCenter;
                activeAnimated = hoverAnimated;
            } else if (standardUrl) {
                activeUrl = standardUrl;
                activeCenter = standardCenter;
                activeAnimated = standardAnimated;
            }

            if (activeUrl && activeAnimated) {
                if (cursorEl.getAttribute('data-active-src') !== activeUrl) {
                    cursorEl.src = activeUrl;
                    cursorEl.setAttribute('data-active-src', activeUrl);
                }
                const transformVal = activeCenter ? 'translate(-50%, -50%)' : 'translate(0, 0)';
                if (cursorEl.style.transform !== transformVal) {
                    cursorEl.style.transform = transformVal;
                }
                if (cursorEl.style.display !== 'block') {
                    cursorEl.style.display = 'block';
                }
                if (!body.classList.contains('custom-cursor-js-active')) {
                    body.classList.add('custom-cursor-js-active');
                }
            } else {
                if (cursorEl.style.display !== 'none') {
                    cursorEl.style.display = 'none';
                }
                if (body.classList.contains('custom-cursor-js-active')) {
                    body.classList.remove('custom-cursor-js-active');
                }
            }
        };

        window.customCursorMoveHandler = (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
            updateActiveCursorState(e.target);
        };
        window.addEventListener('pointermove', window.customCursorMoveHandler, { passive: true });

        window.customCursorOverHandler = (e) => {
            updateActiveCursorState(e.target);
        };
        window.addEventListener('pointerover', window.customCursorOverHandler, { passive: true });

        function tickCursor() {
            cursorEl.style.left = mouseX + 'px';
            cursorEl.style.top = mouseY + 'px';
            window.customCursorRafId = requestAnimationFrame(tickCursor);
        }
        tickCursor();
    };
    window.initCustomCursorImage = initCustomCursorImage;


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
                const reportedUserEl = reportForm.querySelector('input[name="reported_user_id"]');
                const reportedUserId = reportedUserEl ? parseInt(reportedUserEl.value, 10) : 0;

                const isIt = document.documentElement.lang === 'it';

                if (!reportedUserId) {
                    showToast(isIt ? 'ID utente non trovato.' : 'User ID not found.');
                    return;
                }

                fetch('/api/report_profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        reported_user_id: reportedUserId,
                        reason: reason,
                        detail: detail
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.ok) {
                        showToast(isIt ? 'Segnalazione inviata con successo ai moderatori.' : 'Report submitted successfully to moderators.');
                    } else {
                        showToast(data.error || (isIt ? 'Errore durante l\'invio.' : 'Error submitting report.'));
                    }
                })
                .catch(err => {
                    showToast(isIt ? 'Errore di connessione.' : 'Connection error.');
                });
                
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

                    searchResults.innerHTML = data.map((user) => {
                        let roleLabel = user.ruolo === 'owner' ? 'Owner' : (user.ruolo === 'admin' ? 'Admin' : (isIt ? 'Utente' : 'User'));
                        return `
                            <a href="/u/${encodeURIComponent(user.username)}" class="profile-search-item">
                                <img src="${user.pfp}" alt="${user.username}" class="profile-search-avatar" onerror="this.src='/img/default_pfp.png'">
                                <div class="profile-search-info">
                                    <div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                                        <span class="profile-search-username">${user.display_name && user.display_name.trim() ? user.display_name : user.username}</span>
                                        ${user.is_premium ? `<i class="fa-solid fa-gem" style="color: #fbbf24; font-size: 0.78rem;" title="Premium"></i>` : ''}
                                    </div>
                                    ${user.display_name && user.display_name.trim() && user.display_name !== user.username ? `<span class="profile-search-handle" style="font-size: 0.78rem; opacity: 0.6; margin-top: 1px;">@${user.username}</span>` : ''}
                                    <span class="profile-search-role ${user.ruolo}">${roleLabel}</span>
                                </div>
                                <i class="fa-solid fa-arrow-up-right-from-square profile-search-arrow"></i>
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

            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const firstItem = searchResults.querySelector('.profile-search-item');
                    if (firstItem) {
                        firstItem.click();
                    }
                }
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
        initFloatingAudioBtn();
        initProfileEffects();
        initCursorEffects();
        initCustomCursorImage();
        initActivityCarousel();
        updateActivityTimestamps();
        persistDetailsState();
        initProfileNavRedesign();

        updateStickyBehavior();
        updateProfileViewportFit();
        window.addEventListener('resize', updateStickyBehavior);
        window.addEventListener('resize', updateProfileViewportFit);
        window.addEventListener('load', updateStickyBehavior);
        window.addEventListener('load', updateProfileViewportFit);
        if (window.ResizeObserver) {
            const profilePage = document.getElementById('bioPage');
            if (profilePage) {
                new ResizeObserver(updateProfileViewportFit).observe(profilePage);
            }
        }
        setInterval(updateActivityTimestamps, 1000);
        setInterval(refreshDiscordStatus, 30000);

        // Click to Enter Overlay
        const overlay = document.getElementById('clickToEnterOverlay');
        if (overlay) {
            overlay.addEventListener('click', async () => {
                const audio = window.getActiveAudioElement();
                if (audio) {
                    try {
                        if (audio === document.getElementById('profileBgVideo')) {
                            audio.muted = false;
                        }
                        await audio.play();
                        const playIcon = document.getElementById('profileAudioIcon');
                        if (playIcon) playIcon.className = 'fa-solid fa-pause';
                    } catch (e) {
                        console.warn('Autoplay via click to enter skipped:', e.message);
                    }
                }
                document.documentElement.classList.remove('click-to-enter-active');
                document.body.classList.remove('click-to-enter-active');
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
        const audio = window.getActiveAudioElement();
        const useBgVideoAudio = document.body.dataset.bgUseVideoAudio === '1';
        const hasAutoplay = (audio && audio.dataset && audio.dataset.autoplay === '1') || useBgVideoAudio;
        if (!audio || !hasAutoplay) return;
        if (document.getElementById('clickToEnterOverlay')) return;

        const defaultVol = Number(audio.dataset.defaultVolume || 0.18);
        const profileUrl = document.body.dataset.profileUrl || 'global';
        const resolvedVolume = getResolvedVolume(profileUrl, defaultVol);
        audio.volume = resolvedVolume;
        audio.loop = true;
        
        if (audio === document.getElementById('profileBgVideo')) {
            audio.muted = false;
        }

        const tryPlay = async (showMessage = false) => {
            const currentActive = window.getActiveAudioElement();
            if (!currentActive) return false;
            try {
                if (currentActive === document.getElementById('profileBgVideo')) {
                    currentActive.muted = false;
                }
                await currentActive.play();
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
            const unlock = async (e) => {
                if (e && e.target && e.target.closest('[data-floating-audio]')) return;
                const currentActive = window.getActiveAudioElement();
                if (!currentActive) return;
                if (!armed || !currentActive.paused) return;
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

    const body = document.body;
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

    const initTabTitleAnimation = () => {
        const title = body.dataset.tabTitle || document.title;
        const anim = body.dataset.tabAnimation || 'static';
        const speed = parseInt(body.dataset.tabAnimationSpeed ?? 1000, 10);
        const text = body.dataset.tabAnimationText ?? '';

        if (anim === 'static') return;

        let interval = null;
        if (anim === 'marquee') {
            let marqueeText = (text || title) + '   ';
            interval = setInterval(() => {
                marqueeText = marqueeText.substring(1) + marqueeText.substring(0, 1);
                document.title = marqueeText;
            }, speed);
        } else if (anim === 'bounce') {
            let bounceText = text || title;
            let pos = 0;
            let direction = 1;
            const paddingMax = 6;
            interval = setInterval(() => {
                let spaces = ' '.repeat(pos);
                document.title = spaces + bounceText;
                pos += direction;
                if (pos >= paddingMax || pos <= 0) direction = -direction;
            }, speed);
        } else if (anim === 'pulse') {
            let state = false;
            const t1 = title;
            const t2 = text || (title + ' ♡');
            interval = setInterval(() => {
                document.title = state ? t1 : t2;
                state = !state;
            }, speed);
        }
    };
    initTabTitleAnimation();

    const initScrollSnapPagination = () => {
        const activeBody = document.body;
        const bioPage = document.getElementById('bioPage');
        if (!bioPage) return;

        // Cleanup function to restore DOM to standard state
        const cleanupSlides = () => {
            const wrappers = Array.from(bioPage.querySelectorAll('.profile-snap-slide-wrapper'));
            wrappers.forEach(wrapper => {
                if (wrapper.classList.contains('profile-smart-hero-wrapper')) {
                    wrapper.classList.remove('profile-snap-slide-wrapper');
                } else {
                    const parent = wrapper.parentNode;
                    while (wrapper.firstChild) {
                        parent.insertBefore(wrapper.firstChild, wrapper);
                    }
                    wrapper.remove();
                }
            });
        };

        // First, restore DOM to clean state before querying
        cleanupSlides();

        // Helper to find all slide elements
        const getSlides = () => {
            const arr = [];
            const heroWrapper = bioPage.querySelector('.profile-smart-hero-wrapper');
            if (heroWrapper) arr.push(heroWrapper);

            const contentWrapper = bioPage.querySelector('.profile-smart-content');
            if (contentWrapper) {
                const children = Array.from(contentWrapper.querySelectorAll(':scope > section, :scope > div.bio-stats-grid, .profile-split-item'));
                children.sort((a, b) => {
                    const styleA = a.style ? a.style.getPropertyValue('--profile-split-order') : '';
                    const styleB = b.style ? b.style.getPropertyValue('--profile-split-order') : '';
                    let orderA = parseInt(styleA, 10);
                    let orderB = parseInt(styleB, 10);
                    if (isNaN(orderA)) orderA = 0;
                    if (isNaN(orderB)) orderB = 0;
                    return orderA - orderB;
                });
                children.forEach(child => arr.push(child));
            }
            return arr;
        };

        const rawSlides = getSlides();

        // Cleanup global listeners - clear from window
        if (window._snapWheelHandler) {
            window.removeEventListener('wheel', window._snapWheelHandler, { passive: false });
            window._snapWheelHandler = null;
        }
        if (window._snapTouchStartHandler) {
            window.removeEventListener('touchstart', window._snapTouchStartHandler);
            window._snapTouchStartHandler = null;
        }
        if (window._snapTouchMoveHandler) {
            window.removeEventListener('touchmove', window._snapTouchMoveHandler, { passive: false });
            window._snapTouchMoveHandler = null;
        }
        if (window._snapTouchEndHandler) {
            window.removeEventListener('touchend', window._snapTouchEndHandler);
            window._snapTouchEndHandler = null;
        }
        if (window._snapKeyDownHandler) {
            window.removeEventListener('keydown', window._snapKeyDownHandler);
            window._snapKeyDownHandler = null;
        }
        if (window._snapResizeHandler) {
            window.removeEventListener('resize', window._snapResizeHandler);
            window._snapResizeHandler = null;
        }

        const existingDots = document.querySelector('.profile-snap-dots');
        if (existingDots) existingDots.remove();

        rawSlides.forEach(slide => {
            slide.classList.remove('profile-snap-slide', 'slide-active', 'slide-before', 'slide-after', 'is-active');
        });

        // 1. Mobile Check (< 768px)
        const isMobileWidth = () => window.innerWidth < 768;

        // If data-layout-snap is not active, OR we are on mobile, stop here!
        if (activeBody.getAttribute('data-layout-snap') !== '1' || isMobileWidth()) {
            activeBody.classList.remove('snap-active');
            bioPage.scrollTop = 0;

            // Keep the resize listener active so that if they resize back to desktop, it activates.
            if (activeBody.getAttribute('data-layout-snap') === '1') {
                const handleResize = () => {
                    if (window.innerWidth >= 768) {
                        initScrollSnapPagination();
                    }
                };
                window._snapResizeHandler = handleResize;
                window.addEventListener('resize', handleResize);
            }
            return;
        }

        if (rawSlides.length <= 1) {
            activeBody.classList.remove('snap-active');
            return;
        }

        // Activate snap mode – CSS depends on this class
        activeBody.classList.add('snap-active');

        // Wrap slides in profile-snap-slide-wrapper
        const slides = [];
        rawSlides.forEach(slide => {
            if (slide.classList.contains('profile-smart-hero-wrapper')) {
                slide.classList.add('profile-snap-slide-wrapper');
                slide.classList.add('profile-snap-slide');
                slides.push(slide);
            } else {
                const wrapper = document.createElement('div');
                wrapper.className = 'profile-snap-slide-wrapper profile-snap-slide';
                slide.parentNode.insertBefore(wrapper, slide);
                wrapper.appendChild(slide);
                slides.push(wrapper);
            }
        });

        let activeIndex = 0;
        let isScrolling = false;
        let scrollStartTime = 0;
        let startScrollTop = 0;
        let targetScrollTop = 0;
        let currentScrollDuration = 750; // ms transition duration
        const scrollDuration = 750;

        const easeInOutCubic = (t) => {
            return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
        };

        const animateScroll = (timestamp) => {
            if (!scrollStartTime) scrollStartTime = timestamp;
            const elapsed = timestamp - scrollStartTime;
            const progress = Math.min(elapsed / currentScrollDuration, 1);
            const easedProgress = easeInOutCubic(progress);

            bioPage.scrollTop = startScrollTop + (targetScrollTop - startScrollTop) * easedProgress;

            if (progress < 1) {
                requestAnimationFrame(animateScroll);
            } else {
                isScrolling = false;
                scrollStartTime = 0;
            }
        };

        const scrollToPosition = (pos, duration = 750) => {
            startScrollTop = bioPage.scrollTop;
            targetScrollTop = pos;
            isScrolling = true;
            currentScrollDuration = duration;
            scrollStartTime = 0;
            requestAnimationFrame(animateScroll);
        };

        const dotsContainer = document.createElement('div');
        dotsContainer.className = 'profile-snap-dots';
        const dots = [];

        const goToSlide = (index) => {
            if (index < 0 || index >= slides.length) return;
            
            activeIndex = index;
            
            // Trigger smooth scroll animation to the target slide's actual offsetTop
            const targetSlide = slides[activeIndex];
            if (targetSlide) {
                scrollToPosition(targetSlide.offsetTop, scrollDuration);
            }

            // Update active states
            slides.forEach((slide, idx) => {
                slide.classList.toggle('is-active', idx === activeIndex);
            });

            dots.forEach((dot, idx) => {
                dot.classList.toggle('is-active', idx === activeIndex);
            });
        };

        slides.forEach((slide, index) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'profile-snap-dot';
            
            let label = '';
            if (index === 0) {
                label = 'Profile';
            } else {
                let sectionEl = slide.querySelector('[data-section-title]');
                if (!sectionEl && slide.hasAttribute && slide.hasAttribute('data-section-title')) {
                    sectionEl = slide;
                }
                if (sectionEl) {
                    label = sectionEl.getAttribute('data-section-title') || '';
                }
                
                if (!label) {
                    const titleEl = slide.querySelector('h2, h3, .section-title, .bio-card-title, .bio-section-heading span, .profile-clean-heading span, .profile-spotlight-content strong, .profile-embed-header span');
                    if (titleEl) {
                        label = titleEl.textContent.trim();
                    }
                }
                
                if (!label) {
                    let secType = '';
                    if (sectionEl) {
                        secType = sectionEl.getAttribute('data-section-type') || '';
                    }
                    
                    if (secType === 'characters' || slide.querySelector('.profile-characters-section, .profile-character-grid')) {
                        label = 'Character';
                    } else if (secType === 'embeds' || slide.querySelector('.profile-embeds-section, .profile-embed-wrapper, iframe')) {
                        label = 'Embed';
                    } else if (secType === 'blocks' || slide.querySelector('.profile-block-grid, .profile-block-card')) {
                        label = 'Block';
                    } else if (secType === 'projects' || slide.querySelector('.bio-project-grid, .bio-project-card')) {
                        label = 'Project';
                    } else if (secType === 'links' || slide.querySelector('.profile-link-grid, .bio-featured-link')) {
                        label = 'Link';
                    } else if (secType === 'badges' || slide.querySelector('.profile-badge-grid, .custom-badge-card')) {
                        label = 'Badge';
                    } else if (secType === 'stats' || slide.querySelector('.bio-stats-grid')) {
                        label = 'Stats';
                    } else if (secType === 'contents') {
                        label = 'Content';
                    } else if (secType === 'activity') {
                        label = 'Activity';
                    } else if (secType === 'featured' || slide.querySelector('.profile-spotlight')) {
                        label = 'Featured';
                    } else {
                        label = 'Section ' + index;
                    }
                }
            }
            dot.setAttribute('data-label', label);

            dot.addEventListener('click', () => {
                if (isScrolling) return;
                goToSlide(index);
            });

            dotsContainer.appendChild(dot);
            dots.push(dot);
        });

        document.body.appendChild(dotsContainer);

        // Set initial state
        goToSlide(0);

        // 1. Wheel Listener (Mouse & Trackpad) - Registered on window
        const handleWheel = (e) => {
            if (window.innerWidth < 768) return;
            e.preventDefault();

            const delta = e.deltaY;
            if (Math.abs(delta) < 5) return;

            const currentSlide = slides[activeIndex];
            if (!currentSlide) return;

            const viewportHeight = bioPage.clientHeight || window.innerHeight;
            const currentSlideStart = currentSlide.offsetTop;
            const currentSlideHeight = currentSlide.offsetHeight;
            const currentSlideEnd = currentSlideStart + currentSlideHeight;

            // If a slide-change scroll is currently active, don't interrupt
            if (isScrolling) {
                const isChangingSlide = targetScrollTop < currentSlideStart || targetScrollTop > (currentSlideEnd - viewportHeight + 5);
                if (isChangingSlide) return;
            }

            if (currentSlideHeight > viewportHeight) {
                // Slide is longer than viewport!
                const maxScrollInside = currentSlideEnd - viewportHeight;
                let currentPos = isScrolling ? targetScrollTop : bioPage.scrollTop;
                
                if (delta > 0) {
                    // Scrolling down
                    if (currentPos < maxScrollInside - 5) {
                        const step = Math.min(150, maxScrollInside - currentPos);
                        scrollToPosition(currentPos + step, 200); // Faster animation for internal scrolling
                    } else {
                        if (isScrolling) return;
                        goToSlide(activeIndex + 1);
                    }
                } else {
                    // Scrolling up
                    if (currentPos > currentSlideStart + 5) {
                        const step = Math.min(150, currentPos - currentSlideStart);
                        scrollToPosition(currentPos - step, 200); // Faster animation for internal scrolling
                    } else {
                        if (isScrolling) return;
                        goToSlide(activeIndex - 1);
                    }
                }
            } else {
                // Standard height slide
                if (isScrolling) return;
                if (delta > 0) {
                    goToSlide(activeIndex + 1);
                } else {
                    goToSlide(activeIndex - 1);
                }
            }
        };

        window._snapWheelHandler = handleWheel;
        window.addEventListener('wheel', handleWheel, { passive: false });

        // 2. Touch/Swipe Listeners - Registered on window
        let touchStartY = 0;
        let touchStartScrollTop = 0;
        
        const handleTouchStart = (e) => {
            if (window.innerWidth < 768) return;
            touchStartY = e.touches[0].clientY;
            touchStartScrollTop = bioPage.scrollTop;
        };

        const handleTouchMove = (e) => {
            if (window.innerWidth < 768) return;
            
            const currentSlide = slides[activeIndex];
            if (currentSlide) {
                const viewportHeight = bioPage.clientHeight || window.innerHeight;
                const currentSlideHeight = currentSlide.offsetHeight;
                
                if (currentSlideHeight > viewportHeight) {
                    const touchCurrentY = e.touches[0].clientY;
                    const diffY = touchStartY - touchCurrentY;
                    
                    const currentSlideStart = currentSlide.offsetTop;
                    const currentSlideEnd = currentSlideStart + currentSlideHeight;
                    const maxScrollInside = currentSlideEnd - viewportHeight;
                    
                    const targetPos = touchStartScrollTop + diffY;
                    
                    // If target position is inside the current tall slide, scroll naturally with finger
                    if (targetPos >= currentSlideStart && targetPos <= maxScrollInside) {
                        e.preventDefault();
                        bioPage.scrollTop = targetPos;
                        return;
                    }
                }
            }
            e.preventDefault();
        };

        const handleTouchEnd = (e) => {
            if (window.innerWidth < 768) return;
            if (isScrolling) return;

            const touchEndY = e.changedTouches[0].clientY;
            const diffY = touchStartY - touchEndY;

            const currentSlide = slides[activeIndex];
            if (!currentSlide) return;

            const viewportHeight = bioPage.clientHeight || window.innerHeight;
            const currentSlideStart = currentSlide.offsetTop;
            const currentSlideHeight = currentSlide.offsetHeight;
            const currentSlideEnd = currentSlideStart + currentSlideHeight;

            if (currentSlideHeight > viewportHeight) {
                // Tall slide!
                const maxScrollInside = currentSlideEnd - viewportHeight;
                const currentPos = bioPage.scrollTop;

                if (Math.abs(diffY) > 50) {
                    if (diffY > 0) {
                        // Swipe up (scroll down)
                        if (currentPos < maxScrollInside - 5) {
                            const step = Math.min(200, maxScrollInside - currentPos);
                            scrollToPosition(currentPos + step, 300);
                        } else {
                            goToSlide(activeIndex + 1);
                        }
                    } else {
                        // Swipe down (scroll up)
                        if (currentPos > currentSlideStart + 5) {
                            const step = Math.min(200, currentPos - currentSlideStart);
                            scrollToPosition(currentPos - step, 300);
                        } else {
                            goToSlide(activeIndex - 1);
                        }
                    }
                }
            } else {
                // Short slide
                if (Math.abs(diffY) > 50) {
                    if (diffY > 0) {
                        goToSlide(activeIndex + 1);
                    } else {
                        goToSlide(activeIndex - 1);
                    }
                }
            }
        };

        window._snapTouchStartHandler = handleTouchStart;
        window._snapTouchMoveHandler = handleTouchMove;
        window._snapTouchEndHandler = handleTouchEnd;

        window.addEventListener('touchstart', handleTouchStart, { passive: true });
        window.addEventListener('touchmove', handleTouchMove, { passive: false });
        window.addEventListener('touchend', handleTouchEnd, { passive: true });

        // 3. Keydown Listener
        const handleKeyDown = (e) => {
            if (window.innerWidth < 768) return;
            const activeEl = document.activeElement;
            if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.isContentEditable)) {
                return;
            }

            if (isScrolling) {
                if (['ArrowDown', 'ArrowUp', 'PageDown', 'PageUp', ' '].includes(e.key)) {
                    e.preventDefault();
                }
                return;
            }

            const currentSlide = slides[activeIndex];
            if (!currentSlide) return;

            const viewportHeight = bioPage.clientHeight || window.innerHeight;
            const currentSlideStart = currentSlide.offsetTop;
            const currentSlideHeight = currentSlide.offsetHeight;
            const currentSlideEnd = currentSlideStart + currentSlideHeight;
            const currentScrollTop = bioPage.scrollTop;

            if (currentSlideHeight > viewportHeight) {
                const maxScrollInside = currentSlideEnd - viewportHeight;
                if (e.key === 'ArrowDown' || e.key === ' ') {
                    e.preventDefault();
                    if (currentScrollTop < maxScrollInside - 5) {
                        const step = Math.min(150, maxScrollInside - currentScrollTop);
                        scrollToPosition(currentScrollTop + step, 200);
                    } else {
                        goToSlide(activeIndex + 1);
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (currentScrollTop > currentSlideStart + 5) {
                        const step = Math.min(150, currentScrollTop - currentSlideStart);
                        scrollToPosition(currentScrollTop - step, 200);
                    } else {
                        goToSlide(activeIndex - 1);
                    }
                } else if (e.key === 'PageDown') {
                    e.preventDefault();
                    if (currentScrollTop < maxScrollInside - 5) {
                        const step = Math.min(viewportHeight * 0.8, maxScrollInside - currentScrollTop);
                        scrollToPosition(currentScrollTop + step, 300);
                    } else {
                        goToSlide(activeIndex + 1);
                    }
                } else if (e.key === 'PageUp') {
                    e.preventDefault();
                    if (currentScrollTop > currentSlideStart + 5) {
                        const step = Math.min(viewportHeight * 0.8, currentScrollTop - currentSlideStart);
                        scrollToPosition(currentScrollTop - step, 300);
                    } else {
                        goToSlide(activeIndex - 1);
                    }
                }
            } else {
                if (e.key === 'ArrowDown' || e.key === 'PageDown' || e.key === ' ') {
                    e.preventDefault();
                    goToSlide(activeIndex + 1);
                } else if (e.key === 'ArrowUp' || e.key === 'PageUp') {
                    e.preventDefault();
                    goToSlide(activeIndex - 1);
                }
            }
        };

        window._snapKeyDownHandler = handleKeyDown;
        window.addEventListener('keydown', handleKeyDown);

        // 4. Resize Listener
        let lastWidth = window.innerWidth;
        const handleResize = () => {
            const currentWidth = window.innerWidth;
            // Re-init completely if we cross the mobile/desktop threshold
            if ((lastWidth < 768 && currentWidth >= 768) || (lastWidth >= 768 && currentWidth < 768)) {
                lastWidth = currentWidth;
                initScrollSnapPagination();
                return;
            }
            lastWidth = currentWidth;

            // Otherwise adjust active position if on desktop
            if (currentWidth >= 768) {
                const targetSlide = slides[activeIndex];
                if (targetSlide) {
                    bioPage.scrollTop = targetSlide.offsetTop;
                }
            }
        };
        window._snapResizeHandler = handleResize;
        window.addEventListener('resize', handleResize);
    };

    initScrollSnapPagination();
    window.initScrollSnapPagination = initScrollSnapPagination;
})();
