(function () {
    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('is-open');
        const img = modal.querySelector('.sku-preview-img');
        if (img) {
            img.src = '';
        }
    }

    function openModal(modal, url) {
        if (!modal || !url) return;
        const img = modal.querySelector('.sku-preview-img');
        if (!img) return;
        img.src = url;
        modal.classList.add('is-open');
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.sku-thumb');
        const modal = document.querySelector('.sku-preview-modal');
        if (trigger && modal) {
            const url = trigger.getAttribute('data-full');
            if (url) {
                event.preventDefault();
                openModal(modal, url);
            }
            return;
        }

        if (event.target.classList.contains('sku-preview-backdrop') || event.target.closest('.sku-preview-close')) {
            closeModal(document.querySelector('.sku-preview-modal'));
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            const modal = document.querySelector('.sku-preview-modal');
            if (modal && modal.classList.contains('is-open')) {
                closeModal(modal);
            }
        }
    });
})();
