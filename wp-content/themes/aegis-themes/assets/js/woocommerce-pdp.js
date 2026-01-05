(function () {
    const scrollButtons = document.querySelectorAll('[data-scroll-target]');

    const smoothScrollToTarget = (selector) => {
        const target = document.querySelector(selector);
        if (!target) {
            return;
        }

        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    scrollButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            const selector = event.currentTarget.getAttribute('data-scroll-target');
            smoothScrollToTarget(selector);
        });
    });
})();
