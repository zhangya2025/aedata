(function () {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor || wp.editor;
    const { PanelBody, ToggleControl, RangeControl, Button, TextControl, SelectControl } = wp.components;
    const { Fragment, createElement: el } = wp.element;
    const { __ } = wp.i18n;

    const slideTypes = [
        { label: __('Image', 'aegis-hero'), value: 'image' },
        { label: __('Video File', 'aegis-hero'), value: 'video' },
        { label: __('External Video (YouTube)', 'aegis-hero'), value: 'external' }
    ];

    const defaultSlide = () => ({
        type: 'image',
        image_id: 0,
        mobile_image_id: 0,
        link_url: '',
        heading: '',
        subheading: '',
        button_label: '',
        button_url: '',
        video_id: 0,
        poster_image_id: 0,
        controls: true,
        autoplay: false,
        muted: true,
        loop: false,
        provider: 'youtube',
        url: ''
    });

    function updateSlide(slides, index, key, value) {
        const next = slides.slice();
        next[index] = Object.assign({}, next[index], { [key]: value });
        return next;
    }

    function removeSlide(slides, index) {
        const next = slides.slice();
        next.splice(index, 1);
        return next;
    }

    function moveSlide(slides, index, direction) {
        const next = slides.slice();
        const target = index + direction;
        if (target < 0 || target >= slides.length) {
            return slides;
        }
        const tmp = next[target];
        next[target] = next[index];
        next[index] = tmp;
        return next;
    }

    function mediaButton(label, value, onSelect, allowedTypes) {
        return el(MediaUploadCheck, {},
            el(MediaUpload, {
                onSelect: (media) => onSelect(media),
                allowedTypes: allowedTypes,
                value: value,
                render: ({ open }) => el(Button, { onClick: open, isSecondary: true }, label)
            })
        );
    }

    registerBlockType('aegis/hero', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { slides = [], heightDesktop, heightMobile, showArrows, showDots, autoplay, intervalMs } = attributes;

            const previewSlide = slides[0];
            const previewText = previewSlide ? __('Slide 1 preview', 'aegis-hero') : __('Add slides to preview', 'aegis-hero');

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Layout', 'aegis-hero'), initialOpen: true },
                        el(RangeControl, {
                            label: __('Desktop height (px)', 'aegis-hero'),
                            min: 200,
                            max: 900,
                            value: heightDesktop,
                            onChange: (value) => setAttributes({ heightDesktop: value })
                        }),
                        el(RangeControl, {
                            label: __('Mobile height (px)', 'aegis-hero'),
                            min: 160,
                            max: 700,
                            value: heightMobile,
                            onChange: (value) => setAttributes({ heightMobile: value })
                        }),
                        el(ToggleControl, {
                            label: __('Show arrows', 'aegis-hero'),
                            checked: showArrows,
                            onChange: (value) => setAttributes({ showArrows: value })
                        }),
                        el(ToggleControl, {
                            label: __('Show dots', 'aegis-hero'),
                            checked: showDots,
                            onChange: (value) => setAttributes({ showDots: value })
                        }),
                        el(ToggleControl, {
                            label: __('Autoplay', 'aegis-hero'),
                            checked: autoplay,
                            onChange: (value) => setAttributes({ autoplay: value })
                        }),
                        el(RangeControl, {
                            label: __('Autoplay interval (ms)', 'aegis-hero'),
                            min: 2000,
                            max: 15000,
                            step: 500,
                            value: intervalMs,
                            onChange: (value) => setAttributes({ intervalMs: value })
                        })
                    )
                ),
                el('div', { className: 'aegis-hero-editor' },
                    el('div', { className: 'aegis-hero-editor__slides' },
                        slides.map(function (slide, index) {
                            return el('div', { key: index, className: 'aegis-hero-editor__slide' },
                                el('div', { className: 'aegis-hero-editor__row' },
                                    el('strong', {}, __('Slide', 'aegis-hero') + ' ' + (index + 1)),
                                    el('div', { className: 'aegis-hero-editor__actions' },
                                        el(Button, { onClick: () => setAttributes({ slides: moveSlide(slides, index, -1) }), disabled: index === 0 }, __('Up', 'aegis-hero')),
                                        el(Button, { onClick: () => setAttributes({ slides: moveSlide(slides, index, 1) }), disabled: index === slides.length - 1 }, __('Down', 'aegis-hero')),
                                        el(Button, { isDestructive: true, onClick: () => setAttributes({ slides: removeSlide(slides, index) }) }, __('Remove', 'aegis-hero'))
                                    )
                                ),
                                el(SelectControl, {
                                    label: __('Type', 'aegis-hero'),
                                    value: slide.type || 'image',
                                    options: slideTypes,
                                    onChange: (value) => setAttributes({ slides: updateSlide(slides, index, 'type', value) })
                                }),
                                renderSlideFields(slide, index, slides, setAttributes)
                            );
                        }),
                        el(Button, {
                            isPrimary: true,
                            onClick: function () {
                                setAttributes({ slides: slides.concat([defaultSlide()]) });
                            }
                        }, __('Add Slide', 'aegis-hero'))
                    ),
                    el('div', { className: 'aegis-hero-editor__preview', style: { height: (heightDesktop || 520) + 'px' } },
                        el('div', { className: 'aegis-hero-editor__preview-inner' },
                            previewText
                        )
                    )
                )
            );
        },
        save: function () {
            return null;
        }
    });

    function renderSlideFields(slide, index, slides, setAttributes) {
        const type = slide.type || 'image';
        if (type === 'video') {
            return renderVideoFields(slide, index, slides, setAttributes);
        }
        if (type === 'external') {
            return renderExternalFields(slide, index, slides, setAttributes);
        }
        return renderImageFields(slide, index, slides, setAttributes);
    }

    function renderImageFields(slide, index, slides, setAttributes) {
        return el(Fragment, {},
            mediaButton(__('Select image', 'aegis-hero'), slide.image_id, function (media) {
                setAttributes({ slides: updateSlide(slides, index, 'image_id', media.id) });
            }, ['image']),
            mediaButton(__('Select mobile image (optional)', 'aegis-hero'), slide.mobile_image_id, function (media) {
                setAttributes({ slides: updateSlide(slides, index, 'mobile_image_id', media.id) });
            }, ['image']),
            el(TextControl, {
                label: __('Link URL (optional)', 'aegis-hero'),
                value: slide.link_url || '',
                onChange: (value) => setAttributes({ slides: updateSlide(slides, index, 'link_url', value) })
            })
        );
    }

    function renderVideoFields(slide, index, slides, setAttributes) {
        return el(Fragment, {},
            mediaButton(__('Select video', 'aegis-hero'), slide.video_id, function (media) {
                setAttributes({ slides: updateSlide(slides, index, 'video_id', media.id) });
            }, ['video']),
            mediaButton(__('Select poster', 'aegis-hero'), slide.poster_image_id, function (media) {
                setAttributes({ slides: updateSlide(slides, index, 'poster_image_id', media.id) });
            }, ['image']),
            el(ToggleControl, {
                label: __('Show controls', 'aegis-hero'),
                checked: slide.controls !== false,
                onChange: (value) => setAttributes({ slides: updateSlide(slides, index, 'controls', value) })
            }),
            el(ToggleControl, {
                label: __('Autoplay', 'aegis-hero'),
                checked: !!slide.autoplay,
                onChange: (value) => setAttributes({ slides: updateSlide(slides, index, 'autoplay', value) })
            }),
            el(ToggleControl, {
                label: __('Muted (required if autoplay)', 'aegis-hero'),
                checked: slide.autoplay ? true : !!slide.muted,
                disabled: !!slide.autoplay,
                onChange: (value) => setAttributes({ slides: updateSlide(slides, index, 'muted', value) })
            }),
            el(ToggleControl, {
                label: __('Loop', 'aegis-hero'),
                checked: !!slide.loop,
                onChange: (value) => setAttributes({ slides: updateSlide(slides, index, 'loop', value) })
            })
        );
    }

    function renderExternalFields(slide, index, slides, setAttributes) {
        return el(Fragment, {},
            el(SelectControl, {
                label: __('Provider', 'aegis-hero'),
                value: slide.provider || 'youtube',
                options: [{ label: 'YouTube', value: 'youtube' }],
                onChange: (value) => setAttributes({ slides: updateSlide(slides, index, 'provider', value) })
            }),
            el(TextControl, {
                label: __('Video URL', 'aegis-hero'),
                value: slide.url || '',
                onChange: (value) => setAttributes({ slides: updateSlide(slides, index, 'url', value) })
            }),
            mediaButton(__('Select poster (required)', 'aegis-hero'), slide.poster_image_id, function (media) {
                setAttributes({ slides: updateSlide(slides, index, 'poster_image_id', media.id) });
            }, ['image']),
            el(ToggleControl, {
                label: __('Autoplay after click', 'aegis-hero'),
                checked: !!slide.autoplay,
                onChange: (value) => setAttributes({ slides: updateSlide(slides, index, 'autoplay', value) })
            })
        );
    }
})();
