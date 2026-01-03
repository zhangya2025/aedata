( function ( wp ) {
const { registerBlockType } = wp.blocks;
const { InspectorControls } = wp.blockEditor || wp.editor;
const { PanelBody, ToggleControl, Notice } = wp.components;
const ServerSideRender = wp.serverSideRender;
const el = wp.element.createElement;

registerBlockType( 'aegis/mega-header', {
title: 'Aegis Mega Header',
icon: 'menu',
category: 'theme',
supports: {
html: false,
},
attributes: {
placeholder: {
type: 'boolean',
default: true,
},
showUtilityBar: {
type: 'boolean',
default: true,
},
showSearch: {
type: 'boolean',
default: true,
},
showCart: {
type: 'boolean',
default: true,
},
},
edit: ( props ) => {
const { attributes, setAttributes } = props;

const controls = el(
InspectorControls,
null,
el(
PanelBody,
{ title: 'Display Options', initialOpen: true },
el( ToggleControl, {
label: 'Show Utility Bar',
checked: attributes.showUtilityBar,
onChange: ( value ) => setAttributes( { showUtilityBar: value } ),
} ),
el( ToggleControl, {
label: 'Show Search',
checked: attributes.showSearch,
onChange: ( value ) => setAttributes( { showSearch: value } ),
} ),
el( ToggleControl, {
label: 'Show Cart',
checked: attributes.showCart,
onChange: ( value ) => setAttributes( { showCart: value } ),
} ),
el( ToggleControl, {
label: 'Use Placeholder Data',
checked: attributes.placeholder,
onChange: ( value ) => setAttributes( { placeholder: value } ),
} ),
)
);

const notice = ! attributes.placeholder
? el( Notice, { status: 'warning', isDismissible: false }, 'Placeholder is disabled. Panel data not configured for this version.' )
: null;

return el(
'div',
{ className: 'aegis-mega-header-editor' },
controls,
notice,
el( ServerSideRender, {
block: 'aegis/mega-header',
attributes,
} ),
);
},
save: () => null,
} );
} )( window.wp );
