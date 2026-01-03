(function () {
    function bindMediaButtons(context) {
        const selects = context.querySelectorAll('.aegis-media-select');
        const clears = context.querySelectorAll('.aegis-media-clear');

        selects.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const targetId = button.getAttribute('data-media-target');
                const previewId = button.getAttribute('data-preview-target');
                const targetInput = targetId ? document.getElementById(targetId) : null;
                const previewImg = previewId ? document.getElementById(previewId) : null;

                if (!targetInput) {
                    return;
                }

                const frame = wp.media({
                    title: 'Select image',
                    multiple: false,
                    library: { type: 'image' },
                });

                frame.on('select', () => {
                    const attachment = frame.state().get('selection').first().toJSON();
                    targetInput.value = attachment.id;
                    if (previewImg) {
                        previewImg.src = attachment.url;
                        previewImg.style.display = 'block';
                    }
                });

                frame.open();
            });
        });

        clears.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const targetId = button.getAttribute('data-clear-target');
                const previewId = button.getAttribute('data-preview-target');
                const targetInput = targetId ? document.getElementById(targetId) : null;
                const previewImg = previewId ? document.getElementById(previewId) : null;

                if (targetInput) {
                    targetInput.value = '';
                }
                if (previewImg) {
                    previewImg.src = '';
                    previewImg.style.display = 'none';
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const adminWrap = document.querySelector('.wrap');
        if (!adminWrap || typeof wp === 'undefined' || !wp.media) {
            return;
        }
        bindMediaButtons(adminWrap);
    });
})();
