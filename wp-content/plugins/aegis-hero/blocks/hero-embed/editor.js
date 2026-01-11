(function () {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps } = wp.blockEditor || wp.editor;
    const { SelectControl, Notice, Spinner } = wp.components;
    const { createElement: el, Fragment } = wp.element;
    const { useSelect } = wp.data;
    const { __ } = wp.i18n;
    const ServerSideRender = wp.serverSideRender;

    registerBlockType('aegis/hero-embed', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { heroId } = attributes;
            const { heroes, isResolving } = useSelect((select) => {
                const query = { per_page: -1, status: 'publish' };
                return {
                    heroes: select('core').getEntityRecords('postType', 'aegis_hero', query),
                    isResolving: select('core/data').isResolving('core', 'getEntityRecords', ['postType', 'aegis_hero', query])
                };
            }, []);

            const options = [{ label: __('Select a Hero', 'aegis-hero'), value: 0 }];
            if (Array.isArray(heroes)) {
                heroes.forEach((hero) => {
                    options.push({ label: hero.title.rendered || __('(Untitled)', 'aegis-hero'), value: hero.id });
                });
            }

            return el(
                'div',
                useBlockProps(),
                el(
                    Fragment,
                    {},
                    el(SelectControl, {
                        label: __('Hero Preset', 'aegis-hero'),
                        value: heroId,
                        options: options,
                        onChange: (value) => setAttributes({ heroId: parseInt(value, 10) || 0 })
                    }),
                    isResolving && el(Spinner),
                    !heroId && el(Notice, { status: 'warning', isDismissible: false }, __('Select a hero preset to embed.', 'aegis-hero')),
                    heroId && ServerSideRender && el(ServerSideRender, { block: 'aegis/hero-embed', attributes: attributes })
                )
            );
        },
        save: function () {
            return null;
        }
    });
})();
