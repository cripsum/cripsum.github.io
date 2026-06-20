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

    function initSupportersMarquee() {
        // Clear any existing clones to reset measurements
        const clones = container.querySelectorAll('.supporter-clone');
        clones.forEach(c => c.remove());

        const originalChildren = Array.from(container.children);
        if (originalChildren.length === 0) return;

        // Measure container and content widths
        const originalScrollWidth = container.scrollWidth;
        const containerWidth = container.clientWidth;

        // If they do not overflow, center them and disable marquee behavior
        if (originalScrollWidth <= containerWidth) {
            container.style.cursor = 'default';
            container.style.justifyContent = 'center';
            autoScrollActive = false;
            return;
        }

        // Enable marquee behaviors
        container.style.cursor = 'grab';
        container.style.justifyContent = 'flex-start';
        autoScrollActive = true;

        // Clone nodes to support seamless infinite loop
        originalChildren.forEach(child => {
            const clone = child.cloneNode(true);
            clone.classList.add('supporter-clone');
            // Prevent drag ghost image behaviors on links/images inside clone
            clone.addEventListener('dragstart', (e) => e.preventDefault());
            container.appendChild(clone);
        });

        // Calculate transition wrap boundary using first clone offset
        const firstClone = container.querySelector('.supporter-clone');
        if (firstClone) {
            originalWidth = firstClone.offsetLeft - container.offsetLeft;
        } else {
            originalWidth = originalScrollWidth;
        }
    }

    // Initialize marquee layout
    initSupportersMarquee();

    // Prevent default drag and drop image behavior on original nodes
    Array.from(container.children).forEach(child => {
        child.addEventListener('dragstart', (e) => e.preventDefault());
    });

    // Re-initialize on screen resizing
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(initSupportersMarquee, 200);
    });

    // Auto scroll speed configuration (pixels per frame)
    const speed = 0.5;

    function scrollLoop() {
        if (autoScrollActive && !isDown) {
            container.scrollLeft += speed;
            // Seamless wrap around when hitting original width boundary
            if (originalWidth > 0 && container.scrollLeft >= originalWidth) {
                container.scrollLeft -= originalWidth;
            }
        }
        requestAnimationFrame(scrollLoop);
    }

    // Start auto-scroll
    requestAnimationFrame(scrollLoop);

    // Pause auto-scroll on interaction and resume after 3 seconds
    function pauseAutoScroll() {
        autoScrollActive = false;
        clearTimeout(userTimeout);
        userTimeout = setTimeout(() => {
            // Only resume if we still overflow
            const originalScrollWidth = container.scrollWidth / (container.querySelectorAll('.supporter-clone').length > 0 ? 2 : 1);
            if (originalScrollWidth > container.clientWidth) {
                autoScrollActive = true;
            }
        }, 3000);
    }

    // Mouse drag-to-scroll implementation
    container.addEventListener('mousedown', (e) => {
        const clonesCount = container.querySelectorAll('.supporter-clone').length;
        if (clonesCount === 0) return; // Not overflowing

        isDown = true;
        isDragging = false;
        container.classList.add('active');
        container.style.cursor = 'grabbing';
        
        startX = e.pageX - container.offsetLeft;
        scrollLeftVal = container.scrollLeft;
        
        downX = e.pageX;
        downY = e.pageY;
        
        pauseAutoScroll();
    });

    container.addEventListener('mouseleave', () => {
        if (!isDown) return;
        isDown = false;
        container.classList.remove('active');
        container.style.cursor = 'grab';
    });

    container.addEventListener('mouseup', () => {
        if (!isDown) return;
        isDown = false;
        container.classList.remove('active');
        container.style.cursor = 'grab';
    });

    container.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        pauseAutoScroll();

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
        container.scrollLeft = newScrollLeft;
    });

    // Touch events for mobile compatibility
    container.addEventListener('touchstart', () => {
        pauseAutoScroll();
    }, { passive: true });

    container.addEventListener('touchmove', () => {
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
            container.scrollLeft = newScrollLeft;
        }
    }, { passive: false });
});
