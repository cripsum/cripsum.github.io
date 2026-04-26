class ContentSlider {
    constructor(options = {}) {
        this.root = document.getElementById(options.rootId || 'content-slider');
        this.wrapper = document.getElementById(options.wrapperId || 'sliderWrapper');
        this.dotsContainer = document.getElementById(options.dotsId || 'sliderDots');
        this.prevButton = document.querySelector('[data-slider-prev]');
        this.nextButton = document.querySelector('[data-slider-next]');

        this.slides = [
            {
                media: '../img/mentone.jpg',
                fallback: '/img/Susremaster.png',
                title: '🌟 GoonLand',
                description: 'La zona più strana del sito. Entra solo se sei pronto.',
                buttonText: 'Entra in GoonLand',
                tag: 'Area principale',
                link: 'goonland/home'
            },
            {
                media: '../img/waguri.jpeg',
                fallback: '/img/Susremaster.png',
                title: '📦 Lootbox',
                description: 'Apri casse, trova personaggi e spera di non beccare sempre comuni.',
                buttonText: 'Apri lootbox',
                tag: 'Gioco',
                link: 'lootbox'
            },
            {
                media: '../img/segone4.png',
                fallback: '/img/Susremaster.png',
                title: '🏆 Achievements',
                description: 'Badge, obiettivi e piccole missioni sparse nel sito.',
                buttonText: 'Vedi achievement',
                tag: 'Progressi',
                link: 'achievements'
            },
            {
                media: '../img/pfp choso2 cc.png',
                fallback: '/img/Susremaster.png',
                title: '🎬 Edit',
                description: 'Una raccolta dei miei edit e dei video più importanti.',
                buttonText: 'Guarda gli edit',
                tag: 'Video',
                link: 'edits'
            },
            {
                media: '../img/abdul.jpg',
                fallback: '/img/Susremaster.png',
                title: '💬 Chat Globale',
                description: 'Un posto semplice per scrivere con gli altri utenti.',
                buttonText: 'Entra in chat',
                tag: 'Community',
                link: 'global-chat'
            },
            {
                media: '../img/dukedennis.jpg',
                fallback: '/img/Susremaster.png',
                title: '⬇️ Download',
                description: 'File, meme e contenuti extra raccolti in una pagina sola.',
                buttonText: 'Vai ai download',
                tag: 'Extra',
                link: 'download'
            }
        ];

        this.currentIndex = Math.floor(Math.random() * this.slides.length);
        this.autoSlideInterval = null;
        this.isTransitioning = false;
        this.intervalMs = 6000;

        this.init();
    }

    init() {
        if (!this.root || !this.wrapper || !this.dotsContainer || this.slides.length === 0) {
            return;
        }

        this.createSlides();
        this.createDots();
        this.bindEvents();
        this.showSlide(this.currentIndex, 'right', false);
        this.startAutoSlide();
    }

    createSlides() {
        this.wrapper.innerHTML = '';

        this.slides.forEach((slide) => {
            const slideElement = document.createElement('article');
            slideElement.className = 'slider-slide';
            slideElement.setAttribute('aria-hidden', 'true');

            slideElement.innerHTML = `
                <div class="content-showcase">
                    <div class="showcase-wrapper">
                        <div class="showcase-media">
                            <img src="${this.escapeAttribute(slide.media)}" alt="${this.escapeAttribute(slide.title)}" class="showcase-image" loading="lazy" data-fallback="${this.escapeAttribute(slide.fallback)}">
                        </div>
                        <div class="showcase-content">
                            <span class="showcase-tag">${this.escapeHTML(slide.tag)}</span>
                            <h3 class="showcase-title">${this.escapeHTML(slide.title)}</h3>
                            <p class="showcase-description">${this.escapeHTML(slide.description)}</p>
                            <a href="${this.escapeAttribute(slide.link)}" class="showcase-button">
                                ${this.escapeHTML(slide.buttonText)}
                            </a>
                        </div>
                    </div>
                </div>
            `;

            const image = slideElement.querySelector('img');
            if (image) {
                image.addEventListener('error', () => this.handleImageError(image), { once: true });
            }

            this.wrapper.appendChild(slideElement);
        });
    }

    createDots() {
        this.dotsContainer.innerHTML = '';

        this.slides.forEach((slide, index) => {
            const dot = document.createElement('button');
            dot.className = 'dot';
            dot.type = 'button';
            dot.setAttribute('aria-label', `Vai alla slide ${index + 1}: ${slide.title}`);
            dot.addEventListener('click', () => this.goToSlide(index));
            this.dotsContainer.appendChild(dot);
        });
    }

    bindEvents() {
        if (this.nextButton) {
            this.nextButton.addEventListener('click', () => this.nextSlide());
        }

        if (this.prevButton) {
            this.prevButton.addEventListener('click', () => this.previousSlide());
        }

        this.root.addEventListener('mouseenter', () => this.pauseAutoSlide());
        this.root.addEventListener('mouseleave', () => this.resumeAutoSlide());
        this.root.addEventListener('focusin', () => this.pauseAutoSlide());
        this.root.addEventListener('focusout', () => this.resumeAutoSlide());
    }

    showSlide(index, direction = 'right', animate = true) {
        if (this.isTransitioning || index < 0 || index >= this.slides.length) return;

        this.isTransitioning = animate;

        const slides = this.wrapper.querySelectorAll('.slider-slide');
        const dots = this.dotsContainer.querySelectorAll('.dot');

        slides.forEach((slide) => {
            slide.classList.remove('active', 'from-right', 'from-left');
            slide.setAttribute('aria-hidden', 'true');
        });

        if (slides[index]) {
            if (animate) {
                slides[index].classList.add(direction === 'right' ? 'from-right' : 'from-left');
            }
            slides[index].classList.add('active');
            slides[index].setAttribute('aria-hidden', 'false');
        }

        dots.forEach((dot) => dot.classList.remove('active'));
        if (dots[index]) {
            dots[index].classList.add('active');
        }

        this.currentIndex = index;

        window.setTimeout(() => {
            this.isTransitioning = false;
            if (slides[index]) {
                slides[index].classList.remove('from-right', 'from-left');
            }
        }, 650);
    }

    nextSlide() {
        const nextIndex = (this.currentIndex + 1) % this.slides.length;
        this.showSlide(nextIndex, 'right');
        this.restartAutoSlide();
    }

    previousSlide() {
        const prevIndex = (this.currentIndex - 1 + this.slides.length) % this.slides.length;
        this.showSlide(prevIndex, 'left');
        this.restartAutoSlide();
    }

    goToSlide(index) {
        if (this.isTransitioning || index === this.currentIndex) return;
        const direction = index > this.currentIndex ? 'right' : 'left';
        this.showSlide(index, direction);
        this.restartAutoSlide();
    }

    startAutoSlide() {
        this.pauseAutoSlide();
        this.autoSlideInterval = window.setInterval(() => this.nextSlide(), this.intervalMs);
    }

    restartAutoSlide() {
        this.startAutoSlide();
    }

    pauseAutoSlide() {
        if (this.autoSlideInterval) {
            window.clearInterval(this.autoSlideInterval);
            this.autoSlideInterval = null;
        }
    }

    resumeAutoSlide() {
        if (!this.autoSlideInterval) {
            this.startAutoSlide();
        }
    }

    handleImageError(image) {
        const fallback = image.dataset.fallback || '/img/Susremaster.png';
        image.src = fallback;
        image.classList.add('image-fallback');
    }

    escapeHTML(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    escapeAttribute(value) {
        return this.escapeHTML(value);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.contentSlider = new ContentSlider();
});
