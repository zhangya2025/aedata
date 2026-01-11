(function () {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
    const { PanelBody, SelectControl, Spinner } = wp.components;
    const { Fragment, createElement: el } = wp.element;
    const { useSelect } = wp.data;
    const ServerSideRender = wp.serverSideRender;
    const { __ } = wp.i18n;

    const HERO_QUERY = {
        per_page: 100,
        status: 'publish',
        orderby: 'title',
        order: 'asc'
    };

    registerBlockType('aegis/hero-embed', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { heroId } = attributes;
            const blockProps = useBlockProps();

            const heroes = useSelect(
                (select) => select('core').getEntityRecords('postType', 'aegis_hero', HERO_QUERY),
                []
            );

            const heroOptions = [
                { label: __('Select a hero preset', 'aegis-hero'), value: 0 }
            ];

            if (Array.isArray(heroes)) {
                heroes.forEach((hero) => {
                    heroOptions.push({
                        label: `${hero.title?.rendered || __('(Untitled)', 'aegis-hero')} (#${hero.id})`,
                        value: hero.id
                    });
                });
            }

            return el(
                Fragment,
                {},
                el(
                    'div',
                    blockProps,
                    el(
                        'div',
                        { className: 'aegis-hero-embed__selector' },
                        el(SelectControl, {
                            label: __('Hero preset', 'aegis-hero'),
                            value: heroId,
                            options: heroOptions,
                            onChange: (value) => setAttributes({ heroId: parseInt(value, 10) || 0 })
                        }),
                        !heroes && el(Spinner, {})
                    ),
                    heroId
                        ? el(ServerSideRender, {
                              block: 'aegis/hero-embed',
                              attributes: attributes
                          })
                        : el('p', {}, __('Select a hero preset to preview.', 'aegis-hero'))
                ),
                InspectorControls &&
                    el(
                        InspectorControls,
                        {},
                        el(
                            PanelBody,
                            { title: __('Hero Preset', 'aegis-hero'), initialOpen: true },
                            el(SelectControl, {
                                label: __('Hero preset', 'aegis-hero'),
                                value: heroId,
                                options: heroOptions,
                                onChange: (value) => setAttributes({ heroId: parseInt(value, 10) || 0 })
                            })
                        )
                    )
            );
        },
        save: function () {
            return null;
        }
    });
})();
