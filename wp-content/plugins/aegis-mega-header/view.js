( function () {
function initMegaHeader( header ) {
const panelShell = header.querySelector('[data-mega-panels]');
const triggers = header.querySelectorAll('[data-mega-trigger]');
const panels = header.querySelectorAll('.aegis-mega-header__panel');
let activeKey = null;

function closePanels() {
panels.forEach( ( panel ) => {
panel.hidden = true;
panel.classList.remove('is-active');
} );
triggers.forEach( ( trigger ) => {
trigger.classList.remove('is-active');
trigger.setAttribute('aria-expanded', 'false');
} );
if ( panelShell ) {
panelShell.classList.remove('is-open');
}
activeKey = null;
}

function openPanel( key, panelId, trigger ) {
const panel = header.querySelector('#' + panelId);
if ( ! panel || ! panelShell ) {
return;
}

panels.forEach( ( pane ) => {
pane.hidden = true;
pane.classList.remove('is-active');
} );
triggers.forEach( ( btn ) => {
btn.classList.remove('is-active');
btn.setAttribute('aria-expanded', 'false');
} );

panel.hidden = false;
panel.classList.add('is-active');
panelShell.classList.add('is-open');
if ( trigger ) {
trigger.classList.add('is-active');
trigger.setAttribute('aria-expanded', 'true');
}
activeKey = key;
}

triggers.forEach( ( trigger ) => {
const key = trigger.getAttribute('data-mega-trigger');
const panelId = trigger.getAttribute('data-panel-target');

trigger.addEventListener('mouseenter', () => openPanel( key, panelId, trigger ));
trigger.addEventListener('focus', () => openPanel( key, panelId, trigger ));
trigger.addEventListener('keydown', ( event ) => {
if ( event.key === 'Escape' ) {
event.preventDefault();
closePanels();
trigger.focus();
}
} );
} );

if ( panelShell ) {
panelShell.addEventListener('mouseleave', closePanels);
}
header.addEventListener('mouseleave', closePanels);

header.addEventListener('focusout', () => {
setTimeout( () => {
const active = document.activeElement;
if ( active && ! header.contains( active ) ) {
closePanels();
}
}, 10 );
} );

document.addEventListener('keydown', ( event ) => {
if ( event.key === 'Escape' && activeKey ) {
closePanels();
}
} );
}

document.addEventListener('DOMContentLoaded', () => {
const headers = document.querySelectorAll('.aegis-mega-header');
headers.forEach( initMegaHeader );
});
} )();
