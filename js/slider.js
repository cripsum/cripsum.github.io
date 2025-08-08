        class ContentSlider {
            constructor() {
                this.slides = [
                    {
                        media: '../img/jay.png',
                        title: 'Ciao! Sono Jay!',
                        description: 'Vuoi imparare l\'arte dello Spinjitzu?',
                        buttonText: 'Acquista il videocorso',
                        buttonClass: 'primary',
                        link: 'https://payhip.com/b/m0kaT'
                    },
                    {
                        media: '../img/chinese-essay-2.jpg',
                        title: 'Hey! Mi chiamo 優希!',
                        description: 'Vuoi imparare l\'arte dello Yoshukai?',
                        buttonText: 'Scarica la guida gratuita',
                        buttonClass: 'success',
                        link: 'download/yoshukai'
                    },
                    {
                        media: '../img/segone4.png',
                        title: '🏆 Achievements',
                        description: 'Sblocca tutti gli achievement del sito!',
                        buttonText: 'Visualizza progressi',
                        buttonClass: 'warning',
                        link: 'achievements'
                    },
                    {
                        media: '../img/cassa.png',
                        title: '📦 Lootboxes',
                        description: 'Apri lootbox e ottieni ricompense esclusive!',
                        buttonText: 'Apri lootbox',
                        buttonClass: 'info',
                        link: 'lootbox'
                    },
                    {
                        media: '../img/pfp choso2 cc.png',
                        title: '🎬 I miei Edit',
                        description: 'Guarda i miei ultimi edit e video!',
                        buttonText: 'Scopri gli edit',
                        buttonClass: 'danger',
                        link: 'edits'
                    },
                    {
                        media: '../img/raspberry-chan8gb.png',
                        title: '🌟 Goonland',
                        description: 'Entra nella dimensione segreta del sito!',
                        buttonText: 'Accedi a Goonland',
                        buttonClass: 'dark',
                        link: 'goonland/home'
                    },
                    {
                        media: '../img/abdul.jpg',
                        title: '💬 Chat Globale',
                        description: 'Chatta con tutti gli utenti del sito!',
                        buttonText: 'Entra in chat',
                        buttonClass: 'success',
                        link: 'global-chat'
                    },
                    {
                        media: '../img/dukedennis.jpg',
                        title: '⬇️ Downloads',
                        description: 'Scarica contenuti esclusivi e meme!',
                        buttonText: 'Vai ai download',
                        buttonClass: 'secondary',
                        link: 'download'
                    }
                ];
                
                this.currentIndex = Math.floor(Math.random() * this.slides.length);
                this.autoSlideInterval = null;
                this.isTransitioning = false;
                this.init();
            }

            init() {
                this.createSlides();
                this.createDots();
                setTimeout(() => {
                    const slides = document.querySelectorAll('.slider-slide');
                    const dots = document.querySelectorAll('.dot');
                    
                    if (slides[this.currentIndex]) {
                        slides[this.currentIndex].classList.add('active');
                    }
                    if (dots[this.currentIndex]) {
                        dots[this.currentIndex].classList.add('active');
                    }
                    
                    this.startAutoSlide();
                }, 100);
            }

            createSlides() {
                const wrapper = document.getElementById('sliderWrapper');
                wrapper.innerHTML = '';

                this.slides.forEach((slide, index) => {
                    const slideElement = document.createElement('div');
                    slideElement.className = 'slider-slide';
                    
                    slideElement.innerHTML = `
                        <div class="content-showcase">
                            <div class="showcase-wrapper">
                                <div class="showcase-media">
                                    <img src="${slide.media}" alt="${slide.title}" class="showcase-image" />
                                </div>
                                <div class="showcase-content">
                                    <h3 class="showcase-title">${slide.title}</h3>
                                    <p class="showcase-description">${slide.description}</p>
                                    <a href="${slide.link}" class="showcase-button btn btn-${slide.buttonClass}" data-slide="${index}">
                                        <span class="testobianco">${slide.buttonText}</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    wrapper.appendChild(slideElement);
                });
            }

            createDots() {
                const dotsContainer = document.getElementById('sliderDots');
                dotsContainer.innerHTML = '';
                
                this.slides.forEach((_, index) => {
                    const dot = document.createElement('span');
                    dot.className = 'dot';
                    dot.onclick = () => this.goToSlide(index);
                    dotsContainer.appendChild(dot);
                });
            }

            showSlide(index, direction = 'right') {
                if (this.isTransitioning) return;
                this.isTransitioning = true;

                const slides = document.querySelectorAll('.slider-slide');
                const dots = document.querySelectorAll('.dot');

                slides.forEach(slide => {
                    slide.classList.remove('active', 'from-right', 'from-left');
                });

                if (slides[index]) {
                    slides[index].classList.add(direction === 'right' ? 'from-right' : 'from-left');
                    slides[index].classList.add('active');
                    
                    const button = slides[index].querySelector('.showcase-button');
                    if (button && this.slides[index]) {
                        button.href = this.slides[index].link;
                    }
                }

                dots.forEach(dot => dot.classList.remove('active'));
                if (dots[index]) {
                    dots[index].classList.add('active');
                }

                this.currentIndex = index;
                
                setTimeout(() => {
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
                this.autoSlideInterval = setInterval(() => {
                    this.nextSlide();
                }, 5000);
            }

            restartAutoSlide() {
                clearInterval(this.autoSlideInterval);
                this.startAutoSlide();
            }

            pauseAutoSlide() {
                clearInterval(this.autoSlideInterval);
            }

            resumeAutoSlide() {
                this.startAutoSlide();
            }
        }

        let contentSlider;

        function nextSlide() {
            if (contentSlider) contentSlider.nextSlide();
        }

        function previousSlide() {
            if (contentSlider) contentSlider.previousSlide();
        }

        document.addEventListener('DOMContentLoaded', function() {
            contentSlider = new ContentSlider();
            
            const slider = document.getElementById('content-slider');
            if (slider) {
                slider.addEventListener('mouseenter', () => {
                    if (contentSlider) contentSlider.pauseAutoSlide();
                });
                
                slider.addEventListener('mouseleave', () => {
                    if (contentSlider) contentSlider.resumeAutoSlide();
                });
            }
        });