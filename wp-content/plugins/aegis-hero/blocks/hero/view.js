(function () {
    function init() {
        const heroes = document.querySelectorAll('.aegis-hero');
        heroes.forEach(setupHero);
    }

    function setupHero(hero) {
        const slides = hero.querySelectorAll('.aegis-hero__slide');
        if (slides.length <= 1) {
            return;
        }

        let current = 0;
        const settings = parseSettings(hero.dataset.settings);
        const dots = hero.querySelectorAll('.aegis-hero__dot');
        const nextBtn = hero.querySelector('.aegis-hero__arrow--next');
        const prevBtn = hero.querySelector('.aegis-hero__arrow--prev');
        let autoplayTimer = null;
        let touchStartX = 0;

        function goTo(index) {
            if (index === current) {
                return;
            }
            if (index < 0) {
                index = slides.length - 1;
            } else if (index >= slides.length) {
                index = 0;
            }

            cleanupMedia(hero);

            slides[current].classList.remove('is-active');
            if (dots[current]) {
                dots[current].classList.remove('is-active');
            }

            slides[index].classList.add('is-active');
            if (dots[index]) {
                dots[index].classList.add('is-active');
            }

            current = index;
        }

        function next() {
            goTo(current + 1);
        }

        function prev() {
            goTo(current - 1);
        }

        function resetAutoplay() {
            if (!settings.autoplay) {
                return;
            }
            if (autoplayTimer) {
                clearInterval(autoplayTimer);
            }
            autoplayTimer = setInterval(next, settings.intervalMs);
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                next();
                resetAutoplay();
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                prev();
                resetAutoplay();
            });
        }

        if (dots.length) {
            dots.forEach(function (dot) {
                dot.addEventListener('click', function () {
                    const target = parseInt(dot.dataset.target, 10);
                    if (!isNaN(target)) {
                        goTo(target);
                        resetAutoplay();
                    }
                });
            });
        }

        hero.addEventListener('click', function (event) {
            const button = event.target.closest('.aegis-hero__play');
            if (!button || !hero.contains(button)) {
                return;
            }
            const external = button.closest('.aegis-hero__external');
            if (external) {
                insertExternalFrame(external);
            }
        });

        hero.addEventListener('touchstart', function (event) {
            const touch = event.touches[0];
            if (touch) {
                touchStartX = touch.clientX;
            }
        });

        hero.addEventListener('touchend', function (event) {
            const touch = event.changedTouches[0];
            if (!touchStartX || !touch) {
                return;
            }
            const delta = touch.clientX - touchStartX;
            if (Math.abs(delta) > 50) {
                if (delta < 0) {
                    next();
                } else {
                    prev();
                }
                resetAutoplay();
            }
            touchStartX = 0;
        });

        resetAutoplay();
    }

    function parseSettings(raw) {
        if (!raw) {
            return { autoplay: false, intervalMs: 6000 };
        }
        try {
            const parsed = JSON.parse(raw);
            return Object.assign({ autoplay: false, intervalMs: 6000 }, parsed);
        } catch (e) {
            return { autoplay: false, intervalMs: 6000 };
        }
    }

    function cleanupMedia(hero) {
        const videos = hero.querySelectorAll('video');
        videos.forEach(function (video) {
            if (typeof video.pause === 'function') {
                video.pause();
            }
        });

        hero.querySelectorAll('.aegis-hero__external-frame').forEach(function (frame) {
            frame.remove();
        });
    }

    function insertExternalFrame(external) {
        if (external.dataset.allowed !== 'true') {
            return;
        }

        const provider = external.dataset.provider;
        const videoId = external.dataset.videoId;
        const host = external.dataset.embedHost;
        if (provider !== 'youtube' || !videoId || !host) {
            return;
        }

        const autoplay = external.dataset.autoplay === 'true';
        const params = ['rel=0', 'playsinline=1'];
        if (autoplay) {
            params.push('autoplay=1', 'mute=1');
        }

        const src = 'https://' + host + '/embed/' + encodeURIComponent(videoId) + '?' + params.join('&');
        const frame = document.createElement('iframe');
        frame.className = 'aegis-hero__external-frame';
        frame.setAttribute('src', src);
        frame.setAttribute('title', 'External video');
        frame.setAttribute('allow', 'autoplay; fullscreen');
        frame.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation');
        frame.setAttribute('allowfullscreen', 'allowfullscreen');

        external.appendChild(frame);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
