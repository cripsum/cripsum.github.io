document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.supporters-grid');
    if (!container) return;

    let originalWidth = 0;
    let autoScrollActive = true;
    let isDown = false;
    let startX;
    let scrollLeftVal;
    let userTimeout;
    
    // Drag detection to prevent click navigation
    let isDragging = false;
    let downX = 0;
    let downY = 0;

    // Momentum scrolling variables for desktop dragging
    let velocity = 0;
    let lastX = 0;
    let lastTime = Date.now();
    let momentumActive = false;
    let momentumAnimationFrame;

    // Flag to differentiate auto-scrolling from user-initiated scrolling
    let isAutoScrolling = false;

    // Float accumulator for scroll position to prevent browser rounding bugs on 1x DPI screens
    let currentScrollLeft = 0;

    // Cloning and measuring are kept strictly separate.
    //
    // They used to be one function wired to every image's `load` event, so a
    // row of 19 avatars tore down and re-created all 19 clones five times over
    // (95 <img> elements destroyed mid-flight). Removing a loading <img> aborts
    // its request, and because the clone requests the same URL as its original,
    // the abort took the original's load down with it: a handful of avatars
    // ended up permanently blank, a different handful on every page load.
    let clonesBuilt = false;

    function originalCards() {
        return Array.from(container.children).filter((child) => !child.classList.contains('supporter-clone'));
    }

    function removeClones() {
        if (!clonesBuilt) return;
        container.querySelectorAll('.supporter-clone').forEach((clone) => clone.remove());
        clonesBuilt = false;
    }

    // Built exactly once, and only after the originals have finished loading so
    // the clones are served straight from cache instead of racing them.
    function buildClones() {
        if (clonesBuilt || !autoScrollActive) return;

        const originals = originalCards();
        if (originals.length === 0) return;

        clonesBuilt = true;
        originals.forEach((child) => {
            const clone = child.cloneNode(true);
            clone.classList.add('supporter-clone');
            // Prevent drag ghost image behaviors on links/images inside clone
            clone.addEventListener('dragstart', (e) => e.preventDefault());
            container.appendChild(clone);
        });
    }

    // Cheap and side-effect free: safe to call as often as we like.
    function measureSupportersMarquee() {
        const originals = originalCards();
        if (originals.length === 0) return;

        // Calculate exact content width programmatically to avoid scrollWidth bugs
        let contentWidth = 0;
        const gap = 20; // 1.25rem = 20px
        originals.forEach((child, index) => {
            contentWidth += child.getBoundingClientRect().width;
            if (index < originals.length - 1) {
                contentWidth += gap;
            }
        });
        // Add padding (padding: 0.5rem 10px -> 10px left + 10px right = 20px)
        contentWidth += 20;

        const containerWidth = container.getBoundingClientRect().width;

        // If they do not overflow, center them and disable marquee behavior
        if (contentWidth <= containerWidth) {
            removeClones();
            container.style.cursor = 'default';
            container.style.justifyContent = 'center';
            autoScrollActive = false;
            container.scrollLeft = 0;
            currentScrollLeft = 0;
            return;
        }

        // Enable marquee behaviors
        container.style.cursor = 'grab';
        container.style.justifyContent = 'flex-start';
        autoScrollActive = true;

        // Calculate transition wrap boundary mathematically to avoid layout race conditions during resizes
        originalWidth = contentWidth - 20 + gap;

        // Keep the current offset inside the new wrap window instead of
        // snapping back to the start on every re-measure.
        if (originalWidth > 0 && currentScrollLeft >= originalWidth) {
            currentScrollLeft %= originalWidth;
            container.scrollLeft = currentScrollLeft;
        }
    }

    function allImagesSettled() {
        return originalCards()
            .flatMap((card) => Array.from(card.querySelectorAll('img')))
            .every((img) => img.complete);
    }

    // Initial layout pass. The cards carry explicit width/height, so this is
    // already accurate before a single avatar has arrived.
    measureSupportersMarquee();

    // Prevent default drag and drop image behavior on original nodes
    originalCards().forEach((child) => {
        child.addEventListener('dragstart', (e) => e.preventDefault());
    });

    // Re-measure as avatars arrive, and clone once they all have.
    const images = container.querySelectorAll('img');
    images.forEach((img) => {
        if (img.complete) return;
        const onSettled = () => {
            measureSupportersMarquee();
            if (allImagesSettled()) buildClones();
        };
        img.addEventListener('load', onSettled);
        // A failed avatar must not block the marquee forever.
        img.addEventListener('error', onSettled);
    });

    if (document.readyState === 'complete') {
        measureSupportersMarquee();
        buildClones();
    } else {
        window.addEventListener('load', () => {
            measureSupportersMarquee();
            buildClones();
        });
    }

    // Last resort: if some avatar never settles, start the marquee anyway.
    setTimeout(() => {
        measureSupportersMarquee();
        buildClones();
    }, 6000);

    // Re-measure on screen resizing. The clones stay where they are: only the
    // wrap boundary depends on the viewport.
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(measureSupportersMarquee, 200);
    });

    // Auto scroll speed configuration (pixels per frame)
    const speed = 0.4;

    function scrollLoop() {
        if (autoScrollActive && !isDown && !momentumActive) {
            isAutoScrolling = true;
            currentScrollLeft += speed;
            // Seamless wrap around when hitting original width boundary
            if (originalWidth > 0 && currentScrollLeft >= originalWidth) {
                currentScrollLeft -= originalWidth;
            }
            container.scrollLeft = currentScrollLeft;
        } else {
            // Keep accumulator in sync with manual scrolls (wheel, drag, touch)
            currentScrollLeft = container.scrollLeft;
        }
        requestAnimationFrame(scrollLoop);
    }

    // Start auto-scroll
    requestAnimationFrame(scrollLoop);

    // Pause auto-scroll on interaction and resume after 3 seconds of absolute stillness
    function pauseAutoScroll() {
        autoScrollActive = false;
        clearTimeout(userTimeout);
        userTimeout = setTimeout(() => {
            // Only resume if clones are present (meaning we overflow and initialized marquee)
            const clonesCount = container.querySelectorAll('.supporter-clone').length;
            if (clonesCount > 0) {
                autoScrollActive = true;
            }
        }, 3000);
    }

    // Custom decay momentum scrolling function (inertia)
    function startMomentum() {
        momentumActive = true;
        
        function step() {
            if (!momentumActive) return;
            
            isAutoScrolling = true;
            currentScrollLeft -= velocity;
            
            // Perform loop wrap boundary adjustments during momentum scroll
            if (originalWidth > 0) {
                if (currentScrollLeft >= originalWidth) {
                    currentScrollLeft -= originalWidth;
                } else if (currentScrollLeft < 0) {
                    currentScrollLeft += originalWidth;
                }
            }
            container.scrollLeft = currentScrollLeft;
            
            // Apply friction decay
            velocity *= 0.95;
            
            if (Math.abs(velocity) > 0.15) {
                momentumAnimationFrame = requestAnimationFrame(step);
            } else {
                momentumActive = false;
                pauseAutoScroll();
            }
        }
        
        momentumAnimationFrame = requestAnimationFrame(step);
    }

    // Removed scroll listener to prevent collisions with asynchronous browser scroll updates

    // Mouse drag-to-scroll implementation
    container.addEventListener('mousedown', (e) => {
        const clonesCount = container.querySelectorAll('.supporter-clone').length;
        if (clonesCount === 0) return; // Not overflowing

        isDown = true;
        isDragging = false;
        momentumActive = false;
        cancelAnimationFrame(momentumAnimationFrame);

        container.classList.add('active');
        container.style.cursor = 'grabbing';
        
        startX = e.pageX - container.offsetLeft;
        scrollLeftVal = container.scrollLeft;
        
        downX = e.pageX;
        downY = e.pageY;

        lastX = e.pageX;
        lastTime = Date.now();
        velocity = 0;
        
        pauseAutoScroll();
    });

    container.addEventListener('mouseleave', () => {
        if (!isDown) return;
        isDown = false;
        container.classList.remove('active');
        container.style.cursor = 'grab';

        // Apply remaining drag momentum on exit
        if (Math.abs(velocity) > 0.5) {
            startMomentum();
        } else {
            pauseAutoScroll();
        }
    });

    container.addEventListener('mouseup', () => {
        if (!isDown) return;
        isDown = false;
        container.classList.remove('active');
        container.style.cursor = 'grab';

        // Apply remaining drag momentum
        if (Math.abs(velocity) > 0.5) {
            startMomentum();
        } else {
            pauseAutoScroll();
        }
    });

    container.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        pauseAutoScroll();

        // Calculate drag velocity over time
        const now = Date.now();
        const dt = now - lastTime;
        const dx = e.pageX - lastX;
        
        if (dt > 0) {
            velocity = (dx / dt) * 16; // Normalise to pixels per frame (~16ms)
        }
        
        lastX = e.pageX;
        lastTime = now;

        // Detect drag gesture
        if (Math.abs(e.pageX - downX) > 5 || Math.abs(e.pageY - downY) > 5) {
            isDragging = true;
        }

        const x = e.pageX - container.offsetLeft;
        const walk = (x - startX) * 1.5; // Drag speed multiplier
        let newScrollLeft = scrollLeftVal - walk;

        // Perform wrap-around adjustments during manual dragging
        if (originalWidth > 0) {
            if (newScrollLeft >= originalWidth) {
                newScrollLeft -= originalWidth;
                startX = x;
                scrollLeftVal = newScrollLeft;
            } else if (newScrollLeft < 0) {
                newScrollLeft += originalWidth;
                startX = x;
                scrollLeftVal = newScrollLeft;
            }
        }
        isAutoScrolling = false;
        container.scrollLeft = newScrollLeft;
    });

    // Touch events for mobile compatibility
    container.addEventListener('touchstart', () => {
        momentumActive = false;
        cancelAnimationFrame(momentumAnimationFrame);
        pauseAutoScroll();
    }, { passive: true });

    container.addEventListener('touchmove', () => {
        pauseAutoScroll();
    }, { passive: true });

    container.addEventListener('touchend', () => {
        pauseAutoScroll();
    }, { passive: true });

    container.addEventListener('touchcancel', () => {
        pauseAutoScroll();
    }, { passive: true });

    // Capture click events and prevent navigation if user is dragging
    container.addEventListener('click', (e) => {
        if (isDragging) {
            e.preventDefault();
            e.stopPropagation();
            isDragging = false;
        }
    }, true);

    // Redirect vertical mouse wheel to horizontal scroll inside supporters grid
    container.addEventListener('wheel', (e) => {
        const clonesCount = container.querySelectorAll('.supporter-clone').length;
        if (clonesCount === 0) return; // Not overflowing

        if (e.deltaY !== 0) {
            e.preventDefault();
            pauseAutoScroll();
            let newScrollLeft = container.scrollLeft + e.deltaY * 0.8;

            if (originalWidth > 0) {
                if (newScrollLeft >= originalWidth) {
                    newScrollLeft -= originalWidth;
                } else if (newScrollLeft < 0) {
                    newScrollLeft += originalWidth;
                }
            }
            isAutoScrolling = false;
            container.scrollLeft = newScrollLeft;
        }
    }, { passive: false });
});
