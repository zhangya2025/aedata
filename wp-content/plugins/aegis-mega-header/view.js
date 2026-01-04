( function () {
function initMegaHeader( header ) {
const panelShell = header.querySelector('[data-mega-panels]');
const panels = header.querySelectorAll('.aegis-mega-header__panel');
const nav = header.querySelector('.aegis-header__nav');
const navItems = header.querySelectorAll('.aegis-header__nav-item');
const mainBar = header.querySelector('.aegis-header__main');
const mobileOverlay = header.querySelector('.aegis-mobile-overlay');
const mobileDrawer = header.querySelector('.aegis-mobile-drawer');
const mobilePanelsScript = header.querySelector('[data-mobile-panels]');
const mobileRootView = header.querySelector('.aegis-mobile-view--root');
const mobileSubView = header.querySelector('.aegis-mobile-view--sub');
const mobileSubtitle = header.querySelector('[data-subtitle]');
const mobileSubcontent = header.querySelector('[data-subcontent]');
let activeKey = null;
let mobilePanelsData = {};
let ticking = false;
const scroller = document.scrollingElement || document.documentElement;
const getY = () => ( scroller ? scroller.scrollTop || 0 : window.scrollY );
let lastScrollY = getY();
let hoverSolid = false;
let megaOpen = false;
const topThreshold = 10;
const isHome =
document.body.classList.contains('home') ||
document.body.classList.contains('front-page') ||
document.body.classList.contains('page-id-49966');
let overlayEnabled = isHome && window.innerWidth > 960;
const debugOverlay = new URLSearchParams( window.location.search ).get('aegisHeaderDebug') === '1';

if ( ! header || ! mainBar ) {
return;
}

if ( ! isHome ) {
header.classList.remove('is-main-transparent', 'is-main-solid', 'is-main-hidden');
}

if ( mobilePanelsScript && mobilePanelsScript.textContent ) {
try {
mobilePanelsData = JSON.parse( mobilePanelsScript.textContent );
} catch ( e ) {
mobilePanelsData = {};
}
}

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
header.classList.remove('is-mega-open');
megaOpen = false;
activeKey = null;
applySurface( getY() );
applyHidden( getY(), 0 );
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
header.classList.add('is-mega-open');
header.classList.add('is-main-solid');
header.classList.remove('is-main-transparent');
header.classList.remove('is-main-hidden');
megaOpen = true;
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
event.preventDefault();
closePanels();
header.classList.remove('is-main-hidden');
applySurface( getY() );
}
} );

function applySurface( scrollY ) {
if ( ! overlayEnabled ) {
header.classList.remove('is-main-transparent');
header.classList.remove('is-main-hidden');
return;
}

const nearTop = scrollY <= topThreshold;

if ( megaOpen || hoverSolid ) {
header.classList.add('is-main-solid');
header.classList.remove('is-main-transparent');
return;
}

if ( nearTop ) {
header.classList.add('is-main-transparent');
header.classList.remove('is-main-solid');
} else {
header.classList.add('is-main-solid');
header.classList.remove('is-main-transparent');
}
}

function applyHidden( scrollY, delta ) {
if ( ! overlayEnabled || megaOpen || hoverSolid ) {
header.classList.remove('is-main-hidden');
return;
}

if ( delta > 6 && scrollY > 80 ) {
header.classList.add('is-main-hidden');
} else if ( delta < -6 ) {
header.classList.remove('is-main-hidden');
}
}

function logOverlay( scrollY, delta ) {
if ( ! debugOverlay ) {
return;
}

console.log( 'AegisHeader overlay', {
y: scrollY,
delta,
hidden: header.classList.contains('is-main-hidden'),
transparent: header.classList.contains('is-main-transparent'),
solid: header.classList.contains('is-main-solid'),
megaOpen,
hoverSolid,
overlayEnabled,
} );
}

function handleScroll() {
const currentY = getY();
const delta = currentY - lastScrollY;

applySurface( currentY );
applyHidden( currentY, delta );
logOverlay( currentY, delta );

lastScrollY = currentY;
ticking = false;
}

function onScroll() {
if ( ! overlayEnabled ) {
lastScrollY = getY();
return;
}

if ( ! ticking ) {
ticking = true;
requestAnimationFrame( handleScroll );
}
}

function syncOverlayEnabled() {
overlayEnabled = isHome && window.innerWidth > 960;

if ( ! overlayEnabled ) {
header.classList.remove('is-main-hidden');
header.classList.remove('is-main-transparent');
header.classList.remove('is-main-solid');
lastScrollY = getY();
return;
}

lastScrollY = getY();
applySurface( getY() );
applyHidden( getY(), 0 );
}

function bindHoverSolid() {
if ( ! mainBar ) {
return;
}

const enter = () => {
if ( ! overlayEnabled ) {
return;
}
hoverSolid = true;
header.classList.add('is-main-solid');
header.classList.remove('is-main-transparent');
header.classList.remove('is-main-hidden');
};

const exit = () => {
hoverSolid = false;
applySurface( getY() );
applyHidden( getY(), 0 );
};

mainBar.addEventListener('mouseenter', enter);
mainBar.addEventListener('mouseleave', exit);
mainBar.addEventListener('focusin', enter);
mainBar.addEventListener('focusout', exit);
}

function lockBody( lock ) {
if ( lock ) {
document.body.classList.add('aegis-mobile-locked');
document.body.style.overflow = 'hidden';
} else {
document.body.classList.remove('aegis-mobile-locked');
document.body.style.overflow = '';
}
}

function renderMobileSubcontent( itemId ) {
if ( ! mobilePanelsData || ! mobileSubView || ! mobileSubcontent || ! mobileSubtitle ) {
return;
}

const data = mobilePanelsData[ itemId ];

if ( ! data ) {
return;
}

mobileSubcontent.innerHTML = '';
mobileSubtitle.textContent = data.label || '';

if ( data.url && data.url !== '#' ) {
const viewAll = document.createElement('a');
viewAll.className = 'aegis-mobile-viewall';
viewAll.href = data.url;
viewAll.textContent = 'View all';
mobileSubcontent.appendChild( viewAll );
}

if ( Array.isArray( data.groups ) ) {
data.groups.forEach( ( group ) => {
if ( ! group || ( ! group.title && ( ! Array.isArray( group.links ) || ! group.links.length ) ) ) {
return;
}

const section = document.createElement('div');
section.className = 'aegis-mobile-section';

if ( group.title ) {
const title = document.createElement('div');
title.className = 'aegis-mobile-section__title';
title.textContent = group.title;
section.appendChild( title );
}

if ( Array.isArray( group.links ) && group.links.length ) {
const list = document.createElement('ul');
list.className = 'aegis-mobile-links';
group.links.forEach( ( link ) => {
if ( ! link || ! link.label ) {
return;
}
const item = document.createElement('li');
const anchor = document.createElement('a');
anchor.className = 'aegis-mobile-link';
anchor.href = link.url || '#';
anchor.textContent = link.label;
item.appendChild( anchor );
list.appendChild( item );
} );
section.appendChild( list );
}

mobileSubcontent.appendChild( section );
} );
}

if ( data.sidebar && Array.isArray( data.sidebar.links ) && data.sidebar.links.length ) {
const section = document.createElement('div');
section.className = 'aegis-mobile-section';

if ( data.sidebar.title ) {
const title = document.createElement('div');
title.className = 'aegis-mobile-section__title';
title.textContent = data.sidebar.title;
section.appendChild( title );
}

const list = document.createElement('ul');
list.className = 'aegis-mobile-links';
data.sidebar.links.forEach( ( link ) => {
if ( ! link || ! link.label ) {
return;
}
const item = document.createElement('li');
const anchor = document.createElement('a');
anchor.className = 'aegis-mobile-link';
anchor.href = link.url || '#';
anchor.textContent = link.label;
item.appendChild( anchor );
list.appendChild( item );
} );
section.appendChild( list );
mobileSubcontent.appendChild( section );
}
}

function openMobileDrawer() {
if ( ! mobileDrawer || ! mobileOverlay ) {
return;
}

mobileDrawer.hidden = false;
mobileDrawer.setAttribute('aria-hidden', 'false');
mobileOverlay.hidden = false;
lockBody( true );
if ( mobileRootView ) {
mobileRootView.hidden = false;
}
if ( mobileSubView ) {
mobileSubView.hidden = true;
}
}

function closeMobileDrawer() {
if ( mobileDrawer ) {
mobileDrawer.hidden = true;
mobileDrawer.setAttribute('aria-hidden', 'true');
}
if ( mobileOverlay ) {
mobileOverlay.hidden = true;
}
if ( mobileRootView ) {
mobileRootView.hidden = false;
}
if ( mobileSubView ) {
mobileSubView.hidden = true;
}
lockBody( false );
}

function bindMobileNav() {
const openers = header.querySelectorAll('[data-mobile-open]');
const closers = header.querySelectorAll('[data-mobile-close]');

openers.forEach( ( btn ) => {
btn.addEventListener('click', ( event ) => {
event.preventDefault();
openMobileDrawer();
} );
} );

closers.forEach( ( btn ) => {
btn.addEventListener('click', ( event ) => {
event.preventDefault();
closeMobileDrawer();
} );
} );

if ( mobileOverlay ) {
mobileOverlay.addEventListener('click', ( event ) => {
event.preventDefault();
closeMobileDrawer();
} );
}

if ( mobileRootView ) {
mobileRootView.addEventListener('click', ( event ) => {
const trigger = event.target.closest('[data-enter]');
if ( ! trigger ) {
return;
}

const itemId = trigger.getAttribute('data-enter');
renderMobileSubcontent( itemId );
if ( mobileRootView ) {
mobileRootView.hidden = true;
}
if ( mobileSubView ) {
mobileSubView.hidden = false;
}
} );
}

const backBtn = header.querySelector('[data-back]');
if ( backBtn ) {
backBtn.addEventListener('click', ( event ) => {
event.preventDefault();
if ( mobileRootView ) {
mobileRootView.hidden = false;
}
if ( mobileSubView ) {
mobileSubView.hidden = true;
}
mobileSubcontent && ( mobileSubcontent.innerHTML = '' );
mobileSubtitle && ( mobileSubtitle.textContent = '' );
} );
}

document.addEventListener('keydown', ( event ) => {
if ( event.key === 'Escape' ) {
closeMobileDrawer();
}
} );

window.addEventListener('resize', () => {
if ( window.innerWidth > 960 ) {
closeMobileDrawer();
}
syncOverlayEnabled();
} );
}

bindMobileNav();
bindHoverSolid();
syncOverlayEnabled();
applySurface( getY() );
window.addEventListener('scroll', onScroll, { passive: true } );
}

document.addEventListener('DOMContentLoaded', () => {
const headers = document.querySelectorAll('.aegis-mega-header');
headers.forEach( initMegaHeader );
});
} )();
