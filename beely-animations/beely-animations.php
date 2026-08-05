<?php
/**
 * Plugin Name: Bricks Animation System (Attributes)
 * Description: Système d'animation complet piloté par attributs HTML pour Bricks Builder
 * Version: 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Bricks_Animation_System {

    public function __construct() {
        add_action( 'wp_head',   [ $this, 'inject_css' ], 99 );
        add_action( 'wp_footer', [ $this, 'inject_js'  ], 99 );
    }

    public function inject_css() { ?>
<style id="bas-styles">

/* ============================================================
   ÉTAT INITIAL — tout élément animé est invisible par défaut
   sauf si animate="no-hide"
============================================================ */

[animate]:not([animate="no-hide"]):not([animate=""]) {
    opacity: 0;
}

/* Déjà visible si pas de scroll (JS retire cet état) */
[animate].bas-ready {
    opacity: 1;
}

/* ============================================================
   KEYFRAMES
============================================================ */

/* Fade */
@keyframes bas-fade-in        { from { opacity:0 }                                                           to { opacity:1 } }
@keyframes bas-fade-out        { from { opacity:1 }                                                           to { opacity:0 } }

/* Fade + direction */
@keyframes bas-fade-up         { from { opacity:0; transform:translateY(var(--bas-dist,40px)) }               to { opacity:1; transform:translateY(0) } }
@keyframes bas-fade-down       { from { opacity:0; transform:translateY(calc(var(--bas-dist,40px)*-1)) }      to { opacity:1; transform:translateY(0) } }
@keyframes bas-fade-left       { from { opacity:0; transform:translateX(calc(var(--bas-dist,40px)*-1)) }      to { opacity:1; transform:translateX(0) } }
@keyframes bas-fade-right      { from { opacity:0; transform:translateX(var(--bas-dist,40px)) }               to { opacity:1; transform:translateX(0) } }

/* Slide */
@keyframes bas-slide-up        { from { transform:translateY(var(--bas-dist,40px)) }                          to { transform:translateY(0) } }
@keyframes bas-slide-down      { from { transform:translateY(calc(var(--bas-dist,40px)*-1)) }                 to { transform:translateY(0) } }
@keyframes bas-slide-left      { from { transform:translateX(calc(var(--bas-dist,40px)*-1)) }                 to { transform:translateX(0) } }
@keyframes bas-slide-right     { from { transform:translateX(var(--bas-dist,40px)) }                          to { transform:translateX(0) } }

/* Zoom */
@keyframes bas-zoom-in         { from { opacity:0; transform:scale(var(--bas-scale,0.85)) }                   to { opacity:1; transform:scale(1) } }
@keyframes bas-zoom-out        { from { opacity:1; transform:scale(1) }                                       to { opacity:0; transform:scale(var(--bas-scale,0.85)) } }
@keyframes bas-zoom-in-up      { from { opacity:0; transform:scale(var(--bas-scale,0.85)) translateY(var(--bas-dist,40px)) }   to { opacity:1; transform:scale(1) translateY(0) } }
@keyframes bas-zoom-in-down    { from { opacity:0; transform:scale(var(--bas-scale,0.85)) translateY(calc(var(--bas-dist,40px)*-1)) } to { opacity:1; transform:scale(1) translateY(0) } }
@keyframes bas-zoom-in-left    { from { opacity:0; transform:scale(var(--bas-scale,0.85)) translateX(calc(var(--bas-dist,40px)*-1)) } to { opacity:1; transform:scale(1) translateX(0) } }
@keyframes bas-zoom-in-right   { from { opacity:0; transform:scale(var(--bas-scale,0.85)) translateX(var(--bas-dist,40px)) }   to { opacity:1; transform:scale(1) translateX(0) } }
@keyframes bas-scale-x         { from { transform:scaleX(0) }                                                 to { transform:scaleX(1) } }
@keyframes bas-scale-y         { from { transform:scaleY(0) }                                                 to { transform:scaleY(1) } }

/* Flip */
@keyframes bas-flip-x          { from { opacity:0; transform:perspective(600px) rotateX(90deg) }              to { opacity:1; transform:perspective(600px) rotateX(0deg) } }
@keyframes bas-flip-y          { from { opacity:0; transform:perspective(600px) rotateY(90deg) }              to { opacity:1; transform:perspective(600px) rotateY(0deg) } }
@keyframes bas-flip-x-reverse  { from { opacity:0; transform:perspective(600px) rotateX(-90deg) }             to { opacity:1; transform:perspective(600px) rotateX(0deg) } }
@keyframes bas-flip-y-reverse  { from { opacity:0; transform:perspective(600px) rotateY(-90deg) }             to { opacity:1; transform:perspective(600px) rotateY(0deg) } }

/* Rotate */
@keyframes bas-rotate-in       { from { opacity:0; transform:rotate(var(--bas-rotate,-15deg)) }               to { opacity:1; transform:rotate(0deg) } }
@keyframes bas-rotate-in-up    { from { opacity:0; transform:rotate(var(--bas-rotate,-15deg)) translateY(var(--bas-dist,40px)) } to { opacity:1; transform:rotate(0deg) translateY(0) } }
@keyframes bas-spin            { from { transform:rotate(0deg) }                                               to { transform:rotate(360deg) } }
@keyframes bas-spin-reverse    { from { transform:rotate(0deg) }                                               to { transform:rotate(-360deg) } }

/* Blur */
@keyframes bas-blur-in         { from { opacity:0; filter:blur(var(--bas-blur,10px)) }                        to { opacity:1; filter:blur(0) } }
@keyframes bas-blur-in-up      { from { opacity:0; filter:blur(var(--bas-blur,10px)); transform:translateY(var(--bas-dist,30px)) } to { opacity:1; filter:blur(0); transform:translateY(0) } }
@keyframes bas-blur-out        { from { opacity:1; filter:blur(0) }                                           to { opacity:0; filter:blur(var(--bas-blur,10px)) } }

/* Clip / Reveal */
@keyframes bas-clip-top        { from { clip-path:inset(0 0 100% 0) }                                         to { clip-path:inset(0 0 0% 0) } }
@keyframes bas-clip-bottom     { from { clip-path:inset(100% 0 0 0) }                                         to { clip-path:inset(0% 0 0 0) } }
@keyframes bas-clip-left       { from { clip-path:inset(0 100% 0 0) }                                         to { clip-path:inset(0 0% 0 0) } }
@keyframes bas-clip-right      { from { clip-path:inset(0 0 0 100%) }                                         to { clip-path:inset(0 0 0 0%) } }
@keyframes bas-clip-center     { from { clip-path:inset(0 50%) }                                               to { clip-path:inset(0 0%) } }
@keyframes bas-clip-circle     { from { clip-path:circle(0% at 50% 50%) }                                     to { clip-path:circle(150% at 50% 50%) } }

/* Attention (loops) */
@keyframes bas-pulse           { 0%,100% { transform:scale(1) }       50% { transform:scale(1.06) } }
@keyframes bas-pulse-soft      { 0%,100% { opacity:1 }                50% { opacity:0.5 } }
@keyframes bas-bounce          { 0%,100% { transform:translateY(0);   animation-timing-function:cubic-bezier(.8,0,1,1) } 50% { transform:translateY(-28%); animation-timing-function:cubic-bezier(0,0,.2,1) } }
@keyframes bas-shake           { 0%,100% { transform:translateX(0) }  20% { transform:translateX(-8px) }  40% { transform:translateX(8px) }  60% { transform:translateX(-5px) }  80% { transform:translateX(5px) } }
@keyframes bas-wiggle          { 0%,100% { transform:rotate(0deg) }   25% { transform:rotate(-6deg) }     75% { transform:rotate(6deg) } }
@keyframes bas-float           { 0%,100% { transform:translateY(0) }  50% { transform:translateY(-14px) } }
@keyframes bas-sway            { 0%,100% { transform:translateX(0) }  50% { transform:translateX(14px) } }
@keyframes bas-flash           { 0%,50%,100% { opacity:1 }            25%,75% { opacity:0 } }
@keyframes bas-heartbeat       { 0%,100% { transform:scale(1) }       14% { transform:scale(1.18) }  28% { transform:scale(1) }  42% { transform:scale(1.18) }  70% { transform:scale(1) } }
@keyframes bas-rubber-band     { 0% { transform:scale3d(1,1,1) }      30% { transform:scale3d(1.25,.75,1) } 40% { transform:scale3d(.75,1.25,1) } 50% { transform:scale3d(1.15,.85,1) } 65% { transform:scale3d(.95,1.05,1) } 75% { transform:scale3d(1.05,.95,1) } 100% { transform:scale3d(1,1,1) } }
@keyframes bas-jello           { 0%,100% { transform:none }           22% { transform:skewX(-10deg) skewY(-10deg) }  33% { transform:skewX(6deg) skewY(6deg) }   44% { transform:skewX(-3deg) skewY(-3deg) }   55% { transform:skewX(1.5deg) skewY(1.5deg) }  66% { transform:skewX(-.75deg) skewY(-.75deg) } }
@keyframes bas-breathe         { 0%,100% { transform:scale(1); opacity:1 } 50% { transform:scale(1.04); opacity:.8 } }
@keyframes bas-swing           { 0%,100% { transform:rotate(0deg) }   20% { transform:rotate(12deg) }    40% { transform:rotate(-8deg) }   60% { transform:rotate(4deg) }    80% { transform:rotate(-2deg) } }
@keyframes bas-tada            { 0%,100% { transform:scale(1) rotate(0deg) }  10%,20% { transform:scale(.9) rotate(-3deg) }  30%,50%,70%,90% { transform:scale(1.1) rotate(3deg) }  40%,60%,80% { transform:scale(1.1) rotate(-3deg) } }

/* Typewriter — géré en JS */
@keyframes bas-cursor-blink    { 0%,100% { opacity:1 } 50% { opacity:0 } }
.bas-cursor::after {
    content: var(--bas-cursor,'|');
    animation: bas-cursor-blink 0.8s step-end infinite;
}

/* ============================================================
   ACCESSIBILITÉ
============================================================ */
@media (prefers-reduced-motion: reduce) {
    [animate], [parallax] {
        animation: none !important;
        transition: none !important;
        transform: none !important;
        opacity: 1 !important;
        filter: none !important;
        clip-path: none !important;
    }
}

</style>
    <?php }

    public function inject_js() { ?>
<script id="bas-script">
(function () {
'use strict';

/* ============================================================
   CONSTANTES & CONFIG
============================================================ */

const PREFIX   = 'bas-';
const ANIMATTR = 'animate';

const DEFAULTS = {
    duration  : 600,    // ms
    delay     : 0,      // ms
    easing    : 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
    distance  : 40,     // px
    scale     : 0.85,
    rotate    : -15,    // deg
    blur      : 10,     // px
    threshold : 0.15,
    rootMargin: '0px 0px -60px 0px',
    origin    : null,   // transform-origin
    iterations: 1,
    fill      : 'both',
    direction : 'normal',
};

const EASINGS = {
    'linear'      : 'linear',
    'ease'        : 'ease',
    'ease-in'     : 'ease-in',
    'ease-out'    : 'ease-out',
    'ease-in-out' : 'ease-in-out',
    'smooth'      : 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
    'bounce'      : 'cubic-bezier(0.34, 1.56, 0.64, 1)',
    'spring'      : 'cubic-bezier(0.175, 0.885, 0.32, 1.275)',
    'expo-in'     : 'cubic-bezier(0.7, 0, 0.84, 0)',
    'expo-out'    : 'cubic-bezier(0.16, 1, 0.3, 1)',
    'expo-in-out' : 'cubic-bezier(0.77, 0, 0.175, 1)',
    'back-in'     : 'cubic-bezier(0.36, 0, 0.66, -0.56)',
    'back-out'    : 'cubic-bezier(0.34, 1.56, 0.64, 1)',
    'back-in-out' : 'cubic-bezier(0.68, -0.55, 0.27, 1.55)',
    'circ-out'    : 'cubic-bezier(0, 0.55, 0.45, 1)',
    'circ-in'     : 'cubic-bezier(0.55, 0, 1, 0.45)',
    'sine-out'    : 'cubic-bezier(0.39, 0.575, 0.565, 1)',
    'sine-in'     : 'cubic-bezier(0.47, 0, 0.745, 0.715)',
    'quart-out'   : 'cubic-bezier(0.165, 0.84, 0.44, 1)',
    'quart-in'    : 'cubic-bezier(0.895, 0.03, 0.685, 0.22)',
};

/* Animations dont opacity est déjà gérée dans le keyframe */
const OPACITY_MANAGED = new Set([
    'fade-in','fade-out','fade-up','fade-down','fade-left','fade-right',
    'zoom-in','zoom-out','zoom-in-up','zoom-in-down','zoom-in-left','zoom-in-right',
    'flip-x','flip-y','flip-x-reverse','flip-y-reverse',
    'rotate-in','rotate-in-up',
    'blur-in','blur-in-up','blur-out',
    'clip-top','clip-bottom','clip-left','clip-right','clip-center','clip-circle',
]);

/* Animations en boucle par défaut */
const LOOP_BY_DEFAULT = new Set([
    'spin','spin-reverse','pulse','pulse-soft','bounce','shake','wiggle',
    'float','sway','flash','heartbeat','rubber-band','jello','breathe','swing','tada',
]);

/* Animations pour les transform-origins spécifiques */
const ORIGINS = {
    'scale-x' : 'left center',
    'scale-y' : 'top center',
};

/* ============================================================
   LECTURE DES ATTRIBUTS
============================================================ */

function attr(el, name) {
    return el.getAttribute(name);
}

function parseMs(value) {
    if (!value) return null;
    value = value.trim();
    if (value.endsWith('ms')) return parseFloat(value);
    if (value.endsWith('s'))  return parseFloat(value) * 1000;
    return parseFloat(value); // assume ms
}

function parseOptions(el) {
    const animName  = attr(el, ANIMATTR);
    const duration  = parseMs(attr(el, 'duration'))  ?? DEFAULTS.duration;
    const delay     = parseMs(attr(el, 'delay'))     ?? DEFAULTS.delay;
    const rawEase   = attr(el, 'easing') || attr(el, 'ease');
    const easing    = rawEase ? (EASINGS[rawEase] || rawEase) : DEFAULTS.easing;
    const distance  = parseFloat(attr(el, 'distance') || DEFAULTS.distance);
    const scale     = parseFloat(attr(el, 'scale')    || DEFAULTS.scale);
    const rotate    = parseFloat(attr(el, 'rotate')   || DEFAULTS.rotate);
    const blur      = parseFloat(attr(el, 'blur')     || DEFAULTS.blur);
    const threshold = parseFloat(attr(el, 'threshold') || DEFAULTS.threshold);
    const origin    = attr(el, 'origin') || ORIGINS[animName] || null;
    /*
     * `scroll` est **actif par défaut** : une animation d'apparition attend d'être
     * visible, c'est sa raison d'être. Seul `scroll="false"` la joue aussitôt.
     *
     * L'écriture précédente rendait l'inverse. Sans attribut, `getAttribute` rend
     * `null`, et la chaîne s'effondrait :
     *
     *     null === 'true'  → faux
     *     null === ''      → faux
     *     null !== null    → faux, et le && court-circuitait
     *
     * `scroll` valait donc `false` sur **tout** élément qui ne nommait pas le
     * réglage, c'est-à-dire tous : aucun `IntersectionObserver` n'était posé, et la
     * page entière jouait ses animations au chargement.
     *
     * Symptôme à l'usage : on ne voit **jamais** une animation se jouer. Tout est
     * terminé quand on arrive au bloc, et le rechargement rejoue le tout hors de
     * l'écran. On croit que les animations ne marchent pas ; elles marchent trop tôt.
     *
     * Le compteur et la machine à écrire, dans ce même fichier, écrivaient déjà la
     * bonne forme — c'est ce désaccord interne qui aurait dû alerter.
     */
    const scroll    = attr(el, 'scroll') !== 'false';
    const replay    = attr(el, 'replay') === 'true'  || attr(el, 'replay') === '';
    const loop      = attr(el, 'loop');   // 'true' | 'false' | nombre | null
    const fill      = attr(el, 'fill')   || DEFAULTS.fill;
    const direction = attr(el, 'direction') || DEFAULTS.direction;
    const stagger   = parseMs(attr(el, 'stagger')) ?? null;

    // Iterations
    let iterations;
    if (loop === 'true' || loop === '')          iterations = Infinity;
    else if (loop === 'false')                   iterations = 1;
    else if (loop !== null && !isNaN(+loop))     iterations = parseFloat(loop);
    else if (LOOP_BY_DEFAULT.has(animName))      iterations = Infinity;
    else                                          iterations = DEFAULTS.iterations;

    return {
        animName, duration, delay, easing, distance, scale,
        rotate, blur, threshold, origin, scroll, replay,
        iterations, fill, direction, stagger,
    };
}

/* ============================================================
   APPLICATION DE L'ANIMATION
============================================================ */

function applyAnimation(el, opts) {
    const { animName, duration, delay, easing, distance, scale,
            rotate, blur, iterations, fill, direction, origin } = opts;

    if (!animName || animName === 'no-hide') return;

    // Variables CSS personnalisées
    el.style.setProperty('--bas-dist',   `${distance}px`);
    el.style.setProperty('--bas-scale',  scale);
    el.style.setProperty('--bas-rotate', `${rotate}deg`);
    el.style.setProperty('--bas-blur',   `${blur}px`);

    if (origin) el.style.transformOrigin = origin;

    // Gérer opacity avant animation
    if (!OPACITY_MANAGED.has(animName)) {
        el.style.opacity = '1';
    }

    el.style.animation = [
        `${PREFIX}${animName}`,
        `${duration}ms`,
        easing,
        `${delay}ms`,
        iterations === Infinity ? 'infinite' : iterations,
        fill,
        direction,
    ].join(' ');

    // Marquer comme animé
    el.dataset.basState = 'running';

    // Callback de fin
    if (iterations !== Infinity) {
        el.addEventListener('animationend', () => {
            el.dataset.basState = 'done';
        }, { once: true });
    }
}

function resetAnimation(el) {
    el.style.animation    = '';
    el.style.opacity      = '';
    el.dataset.basState   = '';
    // Forcer reflow
    void el.offsetWidth;
}

/* ============================================================
   STAGGER ENFANTS
============================================================ */

function applyStagger(container, opts) {
    const { stagger } = opts;
    if (!stagger) return;

    // Cherche les enfants directs qui ont eux-mêmes un attribut animate
    // OU cible les enfants directs comme groupe
    const children = Array.from(container.children);
    children.forEach((child, i) => {
        const childOpts = parseOptions(child);
        // Si l'enfant n'a pas son propre attribut animate, appliquer celui du parent
        if (!child.hasAttribute(ANIMATTR)) {
            child.setAttribute(ANIMATTR, opts.animName);
        }
        const extraDelay = stagger * i;
        child.setAttribute('delay', `${(childOpts.delay || opts.delay) + extraDelay}ms`);

        /*
         * **Cet appel n'est pas facultatif.** `init()` parcourt une NodeList rendue
         * par `querySelectorAll` — donc **statique**, construite avant le premier
         * `initElement`. Les enfants qui reçoivent leur `animate` ci-dessus n'y sont
         * pas : personne ne les observe, personne ne les anime, et la règle
         * `[animate] { opacity: 0 }` posée par l'attribut qu'on vient de leur donner
         * les masque **pour de bon**.
         *
         * La panne est difficile à lire parce que le conteneur, lui, s'anime
         * normalement : la section entre, et reste vide.
         *
         * `initElement` est idempotent du point de vue de l'observer — `getObserver`
         * met ses instances en cache, et `observe()` sur une cible déjà observée ne
         * fait rien.
         */
        child.dataset.basInit = 'true';
        initElement(child);
    });
}

/* ============================================================
   SCROLL (IntersectionObserver)
============================================================ */

const observerCache = new Map();

function getObserver(threshold, rootMargin, replay) {
    const key = `${threshold}|${rootMargin}|${replay}`;
    if (!observerCache.has(key)) {
        observerCache.set(key, new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const el   = entry.target;
                const opts = JSON.parse(el.dataset.basOpts || '{}');

                if (entry.isIntersecting) {
                    if (opts.replay && el.dataset.basState === 'done') {
                        resetAnimation(el);
                    }
                    if (!el.dataset.basState || el.dataset.basState === '') {
                        applyAnimation(el, opts);
                    }
                } else {
                    if (opts.replay && el.dataset.basState === 'done') {
                        resetAnimation(el);
                    }
                }
            });
        }, { threshold, rootMargin }));
    }
    return observerCache.get(key);
}

/* ============================================================
   PARALLAX
============================================================ */

const parallaxEls = [];

function initParallax() {
    document.querySelectorAll('[parallax]').forEach(el => {
        const speed   = parseFloat(attr(el, 'parallax') || 0.2);
        const axis    = attr(el, 'parallax-axis') || 'y';
        const origin  = el.getBoundingClientRect().top + window.scrollY;
        parallaxEls.push({ el, speed, axis, origin });
    });

    if (!parallaxEls.length) return;

    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(updateParallax);
            ticking = true;
        }
    }, { passive: true });
    updateParallax();
}

function updateParallax() {
    const scrollY = window.scrollY;
    parallaxEls.forEach(({ el, speed, axis }) => {
        const rect   = el.getBoundingClientRect();
        const center = scrollY + window.innerHeight / 2;
        const elCenter = scrollY + rect.top + rect.height / 2;
        const offset = (scrollY - (elCenter - window.innerHeight / 2)) * speed;
        el.style.transform = axis === 'x'
            ? `translateX(${offset}px)`
            : `translateY(${offset}px)`;
        el.style.willChange = 'transform';
    });
    // reset ticking
    parallaxEls._ticking = false;
}

/* ============================================================
   COMPTEUR
============================================================ */

function animateCounter(el) {
    if (el.dataset.basCounterDone === 'true') return;

    const target    = parseFloat(attr(el, 'counter-to')     || el.innerText.replace(/[^\d.]/g, '') || 100);
    const start     = parseFloat(attr(el, 'counter-from')   || 0);
    const duration  = parseFloat(attr(el, 'counter-duration') || attr(el, 'duration') || 2000);
    const decimals  = parseInt(  attr(el, 'counter-decimals') || 0);
    const prefix    = attr(el, 'counter-prefix')   || '';
    const suffix    = attr(el, 'counter-suffix')   || '';
    const separator = attr(el, 'counter-separator') || '';
    const ease      = attr(el, 'counter-ease')     || 'expo-out';

    function easeFn(t) {
        switch(ease) {
            case 'linear'    : return t;
            case 'ease-out'  : return 1 - Math.pow(1 - t, 3);
            case 'expo-out'  : return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
            case 'bounce-out':
                if (t < 1/2.75)      return 7.5625 * t * t;
                else if (t < 2/2.75) return 7.5625 * (t -= 1.5/2.75) * t + 0.75;
                else if (t < 2.5/2.75) return 7.5625 * (t -= 2.25/2.75) * t + 0.9375;
                else                 return 7.5625 * (t -= 2.625/2.75) * t + 0.984375;
            default          : return 1 - Math.pow(2, -10 * t);
        }
    }

    function format(n) {
        let s = n.toFixed(decimals);
        if (separator) s = s.replace(/\B(?=(\d{3})+(?!\d))/g, separator);
        return prefix + s + suffix;
    }

    let startTime = null;
    const replay  = attr(el, 'replay') === 'true' || attr(el, 'replay') === '';

    function step(ts) {
        if (!startTime) startTime = ts;
        const p = Math.min((ts - startTime) / duration, 1);
        el.innerText = format(start + (target - start) * easeFn(p));
        if (p < 1) requestAnimationFrame(step);
        else {
            el.innerText = format(target);
            if (!replay) el.dataset.basCounterDone = 'true';
        }
    }

    requestAnimationFrame(step);
}

/* ============================================================
   TYPEWRITER
============================================================ */

function initTypewriter(el) {
    if (el.dataset.basTypeDone === 'true') return;

    const text     = attr(el, 'typewriter') || el.innerText;
    const speed    = parseFloat(attr(el, 'type-speed')   || 50);   // ms/char
    const delayMs  = parseFloat(attr(el, 'delay')        || 0);
    const cursor   = attr(el, 'type-cursor') !== null ? attr(el, 'type-cursor') : '|';
    const loop     = attr(el, 'loop') === 'true';
    const deleteSpeed = parseFloat(attr(el, 'type-delete-speed') || 30);
    const pauseEnd    = parseFloat(attr(el, 'type-pause')        || 1500);
    const replay      = attr(el, 'replay') === 'true' || attr(el, 'replay') === '';

    el.innerText = '';
    if (cursor) {
        el.style.setProperty('--bas-cursor', `'${cursor}'`);
        el.classList.add('bas-cursor');
    }

    let i = 0, deleting = false;

    function type() {
        if (!deleting) {
            el.innerText = text.slice(0, ++i);
            if (i < text.length) {
                setTimeout(type, speed);
            } else {
                if (loop) setTimeout(() => { deleting = true; type(); }, pauseEnd);
                else if (!replay) el.dataset.basTypeDone = 'true';
            }
        } else {
            el.innerText = text.slice(0, --i);
            if (i > 0) {
                setTimeout(type, deleteSpeed);
            } else {
                deleting = false;
                setTimeout(type, speed);
            }
        }
    }

    setTimeout(type, delayMs);
}

/* ============================================================
   HOVER
============================================================ */

function initHover(el) {
    const animIn  = attr(el, 'hover');
    const animOut = attr(el, 'hover-out') || null;
    const dur     = parseMs(attr(el, 'duration')) ?? DEFAULTS.duration;
    const ease    = EASINGS[attr(el,'easing') || attr(el,'ease')] || DEFAULTS.easing;

    el.addEventListener('mouseenter', () => {
        el.style.animation = `${PREFIX}${animIn} ${dur}ms ${ease} 1 both`;
    });

    el.addEventListener('mouseleave', () => {
        if (animOut) {
            el.style.animation = `${PREFIX}${animOut} ${dur}ms ${ease} 1 both`;
        } else {
            el.style.animation = '';
        }
    });
}

/* ============================================================
   INIT ELEMENT
============================================================ */

function initElement(el) {

    // HOVER
    if (el.hasAttribute('hover')) {
        initHover(el);
        return;
    }

    // TYPEWRITER
    if (el.hasAttribute('typewriter')) {
        const useScroll = attr(el, 'scroll') !== 'false';
        if (useScroll) {
            const threshold = parseFloat(attr(el, 'threshold') || DEFAULTS.threshold);
            const obs = new IntersectionObserver((entries) => {
                entries.forEach(e => {
                    if (e.isIntersecting) {
                        initTypewriter(el);
                        if (attr(el, 'replay') !== 'true') obs.unobserve(el);
                        else el.dataset.basTypeDone = 'false';
                    } else if (attr(el, 'replay') === 'true') {
                        el.dataset.basTypeDone = 'false';
                        el.innerText = '';
                    }
                });
            }, { threshold });
            obs.observe(el);
        } else {
            initTypewriter(el);
        }
        return;
    }

    // COUNTER
    if (el.hasAttribute('counter')) {
        const useScroll = attr(el, 'scroll') !== 'false';
        if (useScroll) {
            const threshold = parseFloat(attr(el, 'threshold') || DEFAULTS.threshold);
            const obs = new IntersectionObserver((entries) => {
                entries.forEach(e => {
                    if (e.isIntersecting) {
                        if (attr(el,'replay') === 'true') el.dataset.basCounterDone = 'false';
                        animateCounter(el);
                        if (attr(el,'replay') !== 'true') obs.unobserve(el);
                    } else if (attr(el, 'replay') === 'true') {
                        el.dataset.basCounterDone = 'false';
                    }
                });
            }, { threshold });
            obs.observe(el);
        } else {
            animateCounter(el);
        }
        return;
    }

    // ANIMATE
    if (el.hasAttribute(ANIMATTR)) {
        const opts = parseOptions(el);

        if (!opts.animName || opts.animName === 'no-hide') {
            el.style.opacity = '1';
            return;
        }

        // Stagger enfants
        if (opts.stagger !== null) {
            applyStagger(el, opts);
            // Continuer pour animer le container lui-même si nécessaire
        }

        // Sauvegarder opts pour l'observer
        el.dataset.basOpts = JSON.stringify(opts);

        if (opts.scroll !== false) {
            // Avec scroll
            const observer = getObserver(
                opts.threshold,
                DEFAULTS.rootMargin,
                opts.replay
            );
            observer.observe(el);
        } else {
            // Immédiat
            applyAnimation(el, opts);
        }
    }
}

/* ============================================================
   INIT GLOBAL
============================================================ */

function init(root = document) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    // Tous les éléments avec animate, counter, typewriter, hover, parallax
    root.querySelectorAll(`
        [${ANIMATTR}],
        [counter],
        [typewriter],
        [hover],
        [parallax]
    `).forEach(el => {
        // Éviter double init
        if (el.dataset.basInit === 'true') return;
        el.dataset.basInit = 'true';
        initElement(el);
    });

    initParallax();
}

/* ============================================================
   API PUBLIQUE
============================================================ */

window.BAS = {
    /**
     * Réinitialiser (après AJAX, Bricks popup, etc.)
     * @param {Element|Document} root - Scope optionnel
     */
    init(root = document) {
        root.querySelectorAll('[data-bas-init]').forEach(el => {
            delete el.dataset.basInit;
        });
        init(root);
    },

    /**
     * Déclencher manuellement une animation
     * @param {string|Element} target
     */
    play(target) {
        const el = typeof target === 'string' ? document.querySelector(target) : target;
        if (!el) return;
        const opts = parseOptions(el);
        resetAnimation(el);
        applyAnimation(el, opts);
    },

    /**
     * Réinitialiser une animation
     * @param {string|Element} target
     */
    reset(target) {
        const el = typeof target === 'string' ? document.querySelector(target) : target;
        if (el) resetAnimation(el);
    },

    /**
     * Lancer un compteur
     * @param {string|Element} target
     */
    counter(target) {
        const el = typeof target === 'string' ? document.querySelector(target) : target;
        if (el) { el.dataset.basCounterDone = 'false'; animateCounter(el); }
    },

    /**
     * Lancer un typewriter
     * @param {string|Element} target
     */
    typewriter(target) {
        const el = typeof target === 'string' ? document.querySelector(target) : target;
        if (el) { el.dataset.basTypeDone = 'false'; initTypewriter(el); }
    },
};

/* ============================================================
   DÉMARRAGE
============================================================ */

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init());
} else {
    init();
}

// Bricks Builder front-end
document.addEventListener('bricks/frontend/init', () => BAS.init());

})();
</script>
    <?php }
}

new Bricks_Animation_System();
