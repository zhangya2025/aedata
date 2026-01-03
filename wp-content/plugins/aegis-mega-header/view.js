( function () {
function initMegaHeader( header ) {
const panelShell = header.querySelector('[data-mega-panels]');
const panels = header.querySelectorAll('.aegis-mega-header__panel');
const nav = header.querySelector('.aegis-header__nav');
const navItems = header.querySelectorAll('.aegis-header__nav-item');
let activeKey = null;

function isMegaTrigger( item ) {
if ( ! item ) {
return false;
}
const panelId = item.getAttribute('data-panel-target');
if ( ! panelId ) {
return false;
}
const panel = header.querySelector('#' + panelId);
return !! panel;
}

function closePanels() {
panels.forEach( ( panel ) => {
panel.hidden = true;
panel.classList.remove('is-active');
} );
navItems.forEach( ( trigger ) => {
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
closePanels();
return;
}

panels.forEach( ( pane ) => {
pane.hidden = true;
pane.classList.remove('is-active');
} );
navItems.forEach( ( btn ) => {
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

function handleNavEnter( event ) {
const trigger = event.target.closest('.aegis-header__nav-item');
if ( ! trigger || ! header.contains( trigger ) ) {
return;
}

if ( ! isMegaTrigger( trigger ) ) {
closePanels();
return;
}

const key = trigger.getAttribute('data-mega-trigger');
const panelId = trigger.getAttribute('data-panel-target');
openPanel( key, panelId, trigger );
}

if ( nav ) {
nav.addEventListener('mouseover', handleNavEnter);
nav.addEventListener('focusin', handleNavEnter);
nav.addEventListener('click', ( event ) => {
const trigger = event.target.closest('.aegis-header__nav-item');
if ( ! trigger || ! header.contains( trigger ) ) {
return;
}

if ( ! isMegaTrigger( trigger ) ) {
closePanels();
return;
}

const key = trigger.getAttribute('data-mega-trigger');
const panelId = trigger.getAttribute('data-panel-target');
openPanel( key, panelId, trigger );
} );
}

navItems.forEach( ( trigger ) => {
trigger.addEventListener('keydown', ( event ) => {
if ( event.key === 'Escape' ) {
event.preventDefault();
closePanels();
trigger.focus();
}
} );
} );

if ( panelShell ) {
panelShell.addEventListener('mouseleave', closePanels );
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
