(function () {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const { PanelBody, SelectControl, Notice } = wp.components;
    const { Fragment, createElement: el } = wp.element;
    const { useSelect } = wp.data;
    const ServerSideRender = wp.serverSideRender;
    const { __ } = wp.i18n;

    registerBlockType('aegis/hero-embed', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const heroId = attributes.heroId || 0;

            const heroes = useSelect(
                (select) => {
                    return select('core').getEntityRecords('postType', 'aegis_hero', {
                        per_page: 100,
                        status: 'publish',
                        orderby: 'title',
                        order: 'asc'
                    });
                },
                []
            );

            const options = [{ label: __('Select a Hero preset', 'aegis-hero'), value: 0 }];
            if (Array.isArray(heroes)) {
                heroes.forEach((hero) => {
                    const label = hero && hero.title && hero.title.rendered
                        ? hero.title.rendered + ' (' + hero.id + ')'
                        : __('(Untitled)', 'aegis-hero') + ' (' + hero.id + ')';
                    options.push({ label: label, value: hero.id });
                });
            }

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Hero Preset', 'aegis-hero'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Hero', 'aegis-hero'),
                            value: heroId,
                            options: options,
                            onChange: (value) => setAttributes({ heroId: parseInt(value, 10) || 0 })
                        })
                    )
                ),
                heroId
                    ? el(ServerSideRender, { block: 'aegis/hero-embed', attributes: attributes })
                    : el(Notice, { status: 'warning', isDismissible: false }, __('Select a Hero preset', 'aegis-hero'))
            );
        },
        save: function () {
            return null;
        }
    });
})();
