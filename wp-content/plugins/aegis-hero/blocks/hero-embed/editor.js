(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { __ } = wp.i18n;
    const { useSelect } = wp.data;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, SelectControl, Spinner } = wp.components;
    const ServerSideRender = wp.serverSideRender;

    registerBlockType('aegis/hero-embed', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { heroId } = attributes;

            const { heroes, isLoading } = useSelect(
                (select) => {
                    const core = select('core');
                    return {
                        heroes: core.getEntityRecords('postType', 'aegis_hero', {
                            status: 'publish',
                            per_page: -1,
                        }),
                        isLoading: core.isResolving('getEntityRecords', [
                            'postType',
                            'aegis_hero',
                            { status: 'publish', per_page: -1 },
                        ]),
                    };
                },
                []
            );

            const options = [
                {
                    label: __('Select a Hero preset', 'aegis-hero'),
                    value: 0,
                },
            ];

            if (heroes && heroes.length) {
                heroes.forEach((hero) => {
                    const title = hero.title && hero.title.rendered ? hero.title.rendered : __('(no title)', 'aegis-hero');
                    options.push({
                        label: `${title} (#${hero.id})`,
                        value: hero.id,
                    });
                });
            }

            const handleChange = (value) => {
                setAttributes({ heroId: parseInt(value, 10) || 0 });
            };

            return (
                wp.element.createElement(
                    wp.element.Fragment,
                    null,
                    wp.element.createElement(
                        InspectorControls,
                        null,
                        wp.element.createElement(
                            PanelBody,
                            { title: __('Hero Preset', 'aegis-hero'), initialOpen: true },
                            isLoading
                                ? wp.element.createElement(Spinner, null)
                                : wp.element.createElement(SelectControl, {
                                      label: __('Select preset', 'aegis-hero'),
                                      value: heroId || 0,
                                      options: options,
                                      onChange: handleChange,
                                  })
                        )
                    ),
                    wp.element.createElement(ServerSideRender, {
                        block: 'aegis/hero-embed',
                        attributes: attributes,
                    })
                )
            );
        },
        save: function () {
            return null;
        },
    });
})(window.wp);
