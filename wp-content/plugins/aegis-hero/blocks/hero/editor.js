(function () {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, MediaUpload, MediaUploadCheck, useBlockProps, InnerBlocks } = wp.blockEditor || wp.editor;
    const { PanelBody, ToggleControl, RangeControl, Button, TextControl, SelectControl, NumberControl } = wp.components;
    const { Fragment, createElement: el } = wp.element;
    const { useSelect } = wp.data;
    const { __ } = wp.i18n;

    const slideTypes = [
        { label: __('Image', 'aegis-hero'), value: 'image' },
        { label: __('Video File', 'aegis-hero'), value: 'video' },
        { label: __('External Video (YouTube)', 'aegis-hero'), value: 'external' }
    ];

    const anchorOptions = [
        { label: __('Top Left', 'aegis-hero'), value: 'top-left' },
        { label: __('Top', 'aegis-hero'), value: 'top' },
        { label: __('Top Right', 'aegis-hero'), value: 'top-right' },
        { label: __('Left', 'aegis-hero'), value: 'left' },
        { label: __('Center', 'aegis-hero'), value: 'center' },
        { label: __('Right', 'aegis-hero'), value: 'right' },
        { label: __('Bottom Left', 'aegis-hero'), value: 'bottom-left' },
        { label: __('Bottom', 'aegis-hero'), value: 'bottom' },
        { label: __('Bottom Right', 'aegis-hero'), value: 'bottom-right' }
    ];

    const promoAllowedBlocks = ['core/heading', 'core/paragraph', 'core/buttons', 'core/button', 'core/list'];
    const promoTemplate = [
        ['core/heading', { content: __('Unlimited Imagination', 'aegis-hero') }],
        ['core/paragraph', { content: __('Add your supporting copy here.', 'aegis-hero') }],
        ['core/buttons', {}, [['core/button', { text: __('Learn more', 'aegis-hero') }]]]
    ];
    const NumberInput = NumberControl || TextControl;

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
            const {
                slides = [],
                heightDesktop,
                heightMobile,
                heightVhDesktop,
                heightVhMobile,
                heightMode,
                subtractHeader,
                headerOffsetPx,
                showArrows,
                showDots,
                autoplay,
                intervalMs,
                hidePageTitleHint,
                align,
                promoEnabled = true,
                promoAnchor = 'center',
                promoOffsetX = 0,
                promoOffsetY = 0,
                promoUseSameOnMobile = true,
                promoOffsetXMobile = 0,
                promoOffsetYMobile = 0,
                promoMaxWidth = 720,
            } = attributes;

            const alignClassName = attributes && attributes.align ? 'align' + attributes.align : '';

            const blockProps = useBlockProps({
                className: ['aegis-hero-editor', alignClassName].filter(Boolean).join(' ')
            });
            const previewSlide = slides[0];
            const previewMedia = useSelect(
                (select) => {
                    if (!previewSlide) {
                        return null;
                    }

                    const core = select('core');
                    const imageId = previewSlide.image_id;
                    const posterId = previewSlide.poster_image_id;

                    if (previewSlide.type === 'image' && imageId) {
                        return core.getMedia(imageId);
                    }

                    if ((previewSlide.type === 'video' || previewSlide.type === 'external') && posterId) {
                        return core.getMedia(posterId);
                    }

                    return null;
                },
                [previewSlide]
            );

            const previewUrl = previewMedia && previewMedia.source_url ? previewMedia.source_url : '';

            const heightModeValue = heightMode || 'fixed';
            const anchorValue = anchorOptions.some((option) => option.value === promoAnchor) ? promoAnchor : 'center';
            const promoOffsetXValue = Number.isFinite(promoOffsetX) ? promoOffsetX : 0;
            const promoOffsetYValue = Number.isFinite(promoOffsetY) ? promoOffsetY : 0;
            const promoOffsetXMobileValue = promoUseSameOnMobile
                ? promoOffsetXValue
                : (Number.isFinite(promoOffsetXMobile) ? promoOffsetXMobile : 0);
            const promoOffsetYMobileValue = promoUseSameOnMobile
                ? promoOffsetYValue
                : (Number.isFinite(promoOffsetYMobile) ? promoOffsetYMobile : 0);
            const promoMaxWidthValue = Number.isFinite(promoMaxWidth) ? promoMaxWidth : 720;
            const previewStyle = {
                '--aegis-hero-h': (heightDesktop || 520) + 'px',
                '--aegis-hero-h-m': (heightMobile || 320) + 'px',
                '--aegis-hero-vh': heightVhDesktop || 70,
                '--aegis-hero-vh-m': heightVhMobile || 60,
                '--aegis-hero-header-offset': (headerOffsetPx || 0) + 'px',
                '--aegis-promo-offset-x': promoOffsetXValue + 'px',
                '--aegis-promo-offset-y': promoOffsetYValue + 'px',
                '--aegis-promo-offset-x-m': promoOffsetXMobileValue + 'px',
                '--aegis-promo-offset-y-m': promoOffsetYMobileValue + 'px',
                '--aegis-promo-maxw': promoMaxWidthValue + 'px',
                minHeight: heightModeValue === 'fullscreen' ? '60vh' : '280px'
            };

            if (heightModeValue === 'fullscreen') {
                const offset = subtractHeader ? (headerOffsetPx || 0) : 0;
                previewStyle.height = offset ? 'calc(70vh - ' + offset + 'px)' : '70vh';
            }

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
                        el(SelectControl, {
                            label: __('Height mode', 'aegis-hero'),
                            value: heightModeValue,
                            options: [
                                { label: __('Fixed', 'aegis-hero'), value: 'fixed' },
                                { label: __('Viewport', 'aegis-hero'), value: 'viewport' },
                                { label: __('Aspect', 'aegis-hero'), value: 'aspect' },
                                { label: __('Full Screen', 'aegis-hero'), value: 'fullscreen' }
                            ],
                            onChange: (value) => setAttributes({ heightMode: value })
                        }),
                        heightModeValue === 'fullscreen' && el(ToggleControl, {
                            label: __('Subtract header height', 'aegis-hero'),
                            checked: !!subtractHeader,
                            onChange: (value) => setAttributes({ subtractHeader: value })
                        }),
                        heightModeValue === 'fullscreen' && subtractHeader && el(TextControl, {
                            label: __('Header offset px', 'aegis-hero'),
                            type: 'number',
                            value: headerOffsetPx || 0,
                            onChange: (value) => setAttributes({ headerOffsetPx: parseInt(value, 10) || 0 })
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
                        }),
                        el(ToggleControl, {
                            label: __('Hide page title hint', 'aegis-hero'),
                            checked: !!hidePageTitleHint,
                            onChange: (value) => setAttributes({ hidePageTitleHint: value })
                        })
                    ),
                    el(PanelBody, { title: __('Promo Overlay', 'aegis-hero'), initialOpen: false },
                        el(ToggleControl, {
                            label: __('Enable promo overlay', 'aegis-hero'),
                            checked: !!promoEnabled,
                            onChange: (value) => setAttributes({ promoEnabled: value })
                        }),
                        promoEnabled && el(Fragment, {},
                            el('div', { className: 'aegis-hero-editor__anchor-grid' },
                                anchorOptions.map((option) => el(Button, {
                                    key: option.value,
                                    variant: anchorValue === option.value ? 'primary' : 'secondary',
                                    className: 'aegis-hero-editor__anchor-btn',
                                    onClick: () => setAttributes({ promoAnchor: option.value })
                                }, option.label))
                            ),
                            el(RangeControl, {
                                label: __('Offset X (px)', 'aegis-hero'),
                                min: -400,
                                max: 400,
                                value: promoOffsetXValue,
                                onChange: (value) => setAttributes({ promoOffsetX: value })
                            }),
                            el(RangeControl, {
                                label: __('Offset Y (px)', 'aegis-hero'),
                                min: -400,
                                max: 400,
                                value: promoOffsetYValue,
                                onChange: (value) => setAttributes({ promoOffsetY: value })
                            }),
                            el(ToggleControl, {
                                label: __('Use same offsets on mobile', 'aegis-hero'),
                                checked: !!promoUseSameOnMobile,
                                onChange: (value) => setAttributes({ promoUseSameOnMobile: value })
                            }),
                            !promoUseSameOnMobile && el(Fragment, {},
                                el(RangeControl, {
                                    label: __('Mobile offset X (px)', 'aegis-hero'),
                                    min: -400,
                                    max: 400,
                                    value: promoOffsetXMobileValue,
                                    onChange: (value) => setAttributes({ promoOffsetXMobile: value })
                                }),
                                el(RangeControl, {
                                    label: __('Mobile offset Y (px)', 'aegis-hero'),
                                    min: -400,
                                    max: 400,
                                    value: promoOffsetYMobileValue,
                                    onChange: (value) => setAttributes({ promoOffsetYMobile: value })
                                })
                            ),
                            el(NumberInput, {
                                label: __('Max width (px)', 'aegis-hero'),
                                min: 200,
                                max: 1400,
                                value: promoMaxWidthValue,
                                type: 'number',
                                onChange: (value) => setAttributes({ promoMaxWidth: parseInt(value, 10) || 720 })
                            })
                        )
                    )
                ),
                el('div', blockProps,
                    el('div', {
                className: [
                    'aegis-hero-editor__preview',
                    'aegis-hero',
                    alignClassName,
                    'aegis-hero--mode-' + heightModeValue,
                    subtractHeader ? 'aegis-hero--subtract-header' : '',
                    promoEnabled ? 'aegis-hero--promo-anchor-' + anchorValue : ''
                ].filter(Boolean).join(' '),
                style: previewStyle
            },
                        previewUrl ?
                            el('img', {
                                className: 'aegis-hero-editor__preview-media',
                                src: previewUrl,
                                alt: __('Preview', 'aegis-hero')
                            }) :
                            el('div', { className: 'aegis-hero-editor__preview-placeholder' },
                                previewSlide ? __('Cover preview unavailable', 'aegis-hero') : __('Add slides to preview', 'aegis-hero')
                            ),
                        el('div', {
                            className: 'aegis-hero__overlay',
                            style: promoEnabled ? undefined : { display: 'none' },
                            'aria-hidden': promoEnabled ? undefined : true
                        },
                            el('div', { className: 'aegis-hero__promo' },
                                el(InnerBlocks, {
                                    allowedBlocks: promoAllowedBlocks,
                                    template: promoTemplate,
                                    templateLock: false
                                })
                            )
                        )
                    ),
                    !hidePageTitleHint && el('p', { className: 'aegis-hero-editor__hint' },
                        __('页面顶部的 Home 标题来自 Post Title/模板；如需隐藏，请在模板中移除 Post Title 块或使用无标题模板。', 'aegis-hero')
                    ),
                    el('div', { className: 'aegis-hero-editor__slides' },
                        slides.map(function (slide, index) {
                            return el('details', { key: index, className: 'aegis-hero-editor__slide', open: index === 0 },
                                el('summary', { className: 'aegis-hero-editor__summary' },
                                    el('span', {}, __('Slide', 'aegis-hero') + ' ' + (index + 1)),
                                    el('span', { className: 'aegis-hero-editor__summary-type' }, slide.type || 'image')
                                ),
                                el('div', { className: 'aegis-hero-editor__row' },
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
