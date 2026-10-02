/**
 * Publishing Guru — front-end behaviour.
 *
 * Vanilla-JS port of the interactive parts of the original React build:
 * scroll reveal, mobile menu sheet, select dropdowns, toasts (Sonner + shadcn),
 * contact forms, course checkout / purchase verification, and Facebook Pixel events.
 * Markup and class names mirror what the original components rendered.
 */
(function () {
    'use strict';

    var APP = window.APP || {};
    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    /* ------------------------------------------------------------------
     * Icons (lucide)
     * ---------------------------------------------------------------- */
    function lucide(name, cls, inner) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-' + name + ' ' + cls + '">' + inner + '</svg>';
    }
    var ICON = {
        loader: function (cls) {
            return lucide('loader', cls, '<path d="M12 2v4"></path><path d="m16.2 7.8 2.9-2.9"></path><path d="M18 12h4"></path><path d="m16.2 16.2 2.9 2.9"></path><path d="M12 18v4"></path><path d="m4.9 19.1 2.9-2.9"></path><path d="M2 12h4"></path><path d="m4.9 4.9 2.9 2.9"></path>');
        },
        loaderCircle: function (cls) { return lucide('loader-circle', cls, '<path d="M21 12a9 9 0 1 1-6.219-8.56"></path>'); },
        check: function (cls) { return lucide('check', cls, '<path d="M20 6 9 17l-5-5"></path>'); },
        x: function (cls) { return lucide('x', cls, '<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>'); }
    };

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ------------------------------------------------------------------
     * Facebook Pixel (retries while fbevents.js loads, like the original)
     * ---------------------------------------------------------------- */
    function withPixel(fn, retries, delay) {
        retries = retries === undefined ? 3 : retries;
        delay = delay || 100;
        if (typeof window.fbq === 'function') return fn();
        if (retries > 0) setTimeout(function () { withPixel(fn, retries - 1, delay * 2); }, delay);
    }
    function track(event, data) { withPixel(function () { window.fbq('track', event, data); }); }

    /* ------------------------------------------------------------------
     * Scroll lock + layered dismiss (what Radix does for modal layers)
     * ---------------------------------------------------------------- */
    var lockCount = 0, lockStyle = null;
    function lockScroll() {
        if (lockCount++ > 0) return;
        var gap = window.innerWidth - document.documentElement.clientWidth;
        lockStyle = document.createElement('style');
        lockStyle.textContent = 'body[data-scroll-locked]{overflow:hidden !important;overscroll-behavior:contain;position:relative !important;padding-left:0px;padding-top:0px;padding-right:0px;margin-left:0;margin-top:0;margin-right:' + gap + 'px !important;}';
        document.head.appendChild(lockStyle);
        document.body.setAttribute('data-scroll-locked', '1');
        document.body.style.pointerEvents = 'none';
    }
    function unlockScroll() {
        if (--lockCount > 0) return;
        lockCount = 0;
        document.body.removeAttribute('data-scroll-locked');
        document.body.style.pointerEvents = '';
        if (!document.body.getAttribute('style')) document.body.removeAttribute('style');
        if (lockStyle) lockStyle.remove();
        lockStyle = null;
    }

    // Stack of open layers; Escape closes the top-most one.
    var layers = [];
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && layers.length) {
            e.preventDefault();
            layers[layers.length - 1].close();
        }
    });

    /** Set data-state="closed", wait for the exit animation, then remove. */
    function animateOut(nodes, done) {
        var remaining = nodes.length;
        nodes.forEach(function (node) {
            node.setAttribute('data-state', 'closed');
            var finished = false;
            var finish = function () {
                if (finished) return;
                finished = true;
                node.remove();
                if (--remaining === 0 && done) done();
            };
            var name = getComputedStyle(node).animationName;
            if (!name || name === 'none') return finish();
            node.addEventListener('animationend', finish);
            setTimeout(finish, 600);
        });
    }

    /** Open a modal dialog/sheet from a <template> (overlay + content). */
    function openModal(template, opts) {
        opts = opts || {};
        var frag = template.content.cloneNode(true);
        var nodes = Array.prototype.slice.call(frag.children);
        var overlay = nodes[0], content = nodes[1];
        var previousFocus = document.activeElement;
        document.body.appendChild(frag);
        lockScroll();

        var layer = {
            content: content,
            close: function () {
                var i = layers.indexOf(layer);
                if (i === -1) return;
                layers.splice(i, 1);
                if (opts.onClose) opts.onClose();
                animateOut(nodes, function () { unlockScroll(); });
                if (previousFocus && previousFocus.focus) previousFocus.focus();
            }
        };
        layers.push(layer);

        overlay.addEventListener('pointerdown', function (e) { e.preventDefault(); layer.close(); });
        $$('[data-dialog-close]', content).forEach(function (btn) {
            btn.addEventListener('click', function () { layer.close(); });
        });
        if (opts.onOpen) opts.onOpen(content, layer);
        // Like Radix FocusScope: focus the first tabbable element, else the content itself.
        var first = $$('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])', content)[0];
        (first || content).focus({ preventScroll: true });
        return layer;
    }

    /* ------------------------------------------------------------------
     * Scroll-reveal images (IntersectionObserver, fires once)
     * ---------------------------------------------------------------- */
    function initReveal() {
        var els = $$('.animate-left-to-right, .animate-bottom-to-top, .animate-right-to-left');
        if (!els.length) return;
        if (!('IntersectionObserver' in window)) {
            els.forEach(function (el) { el.classList.add('animate'); });
            return;
        }
        els.forEach(function (el) {
            var io = new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting) {
                    el.classList.add('animate');
                    io.disconnect();
                }
            }, { threshold: 0.1, rootMargin: '50px' });
            io.observe(el);
        });
    }

    /* ------------------------------------------------------------------
     * Mobile menu sheet
     * ---------------------------------------------------------------- */
    function initMobileMenu() {
        var trigger = $('button[aria-controls="mobile-menu"]');
        var template = $('#mobile-menu-template');
        if (!trigger || !template) return;
        var servicesOpen = false; // remembered between openings, like the header's state

        trigger.addEventListener('click', function () {
            trigger.setAttribute('aria-expanded', 'true');
            trigger.setAttribute('data-state', 'open');
            openModal(template, {
                onOpen: function (content) {
                    var toggle = $('[data-services-toggle]', content);
                    var list = $('template[data-services-list]', content);
                    var chevron = toggle.querySelector(':scope > svg');
                    var rendered = null;
                    var render = function () {
                        chevron.setAttribute('class', 'lucide lucide-chevron-down w-4 h-4 transition-transform ' + (servicesOpen ? 'rotate-180' : ''));
                        if (servicesOpen && !rendered) {
                            rendered = list.content.firstElementChild.cloneNode(true);
                            list.parentNode.insertBefore(rendered, list);
                        } else if (!servicesOpen && rendered) {
                            rendered.remove();
                            rendered = null;
                        }
                    };
                    toggle.addEventListener('click', function () { servicesOpen = !servicesOpen; render(); });
                    render();
                },
                onClose: function () {
                    trigger.setAttribute('aria-expanded', 'false');
                    trigger.setAttribute('data-state', 'closed');
                }
            });
        });
    }

    /* ------------------------------------------------------------------
     * Select (Radix Select, popper position)
     * Trigger: button[role=combobox] followed by a visually hidden <select>.
     * ---------------------------------------------------------------- */
    var SELECT_ITEM_CLASS = 'relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50';
    var SELECT_CONTENT_CLASS = 'relative z-50 max-h-96 min-w-[8rem] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1';
    var SELECT_VIEWPORT_STYLE = '<style>[data-radix-select-viewport]{scrollbar-width:none;-ms-overflow-style:none;-webkit-overflow-scrolling:touch;}[data-radix-select-viewport]::-webkit-scrollbar{display:none}</style>';
    var COLLISION_PADDING = 10;

    function Select(trigger) {
        var native = trigger.nextElementSibling;
        var label = trigger.querySelector('span');
        var placeholder = native.getAttribute('data-placeholder-text') || label.textContent;
        var selected = native.querySelector('option[selected]');
        var state = { value: selected ? selected.value : '', open: null };
        if (!selected) native.selectedIndex = -1;
        var self = this;

        this.trigger = trigger;
        this.value = function () { return state.value; };
        this.label = function () {
            var opt = state.value ? native.querySelector('option[value="' + state.value + '"]') : null;
            return opt ? opt.textContent : '';
        };
        this.setValue = function (value) {
            state.value = value;
            native.value = value;
            if (!value) native.selectedIndex = -1;
            if (value) {
                trigger.removeAttribute('data-placeholder');
                label.textContent = self.label();
            } else {
                trigger.setAttribute('data-placeholder', '');
                label.textContent = placeholder;
            }
        };

        function open() {
            if (state.open) return;
            var opts = Array.prototype.slice.call(native.options);
            var wrapper = document.createElement('div');
            wrapper.setAttribute('data-radix-popper-content-wrapper', '');
            wrapper.setAttribute('dir', 'ltr');
            var items = opts.map(function (o, i) {
                var checked = o.value === state.value;
                return '<div role="option" aria-labelledby="select-item-' + i + '" aria-selected="' + checked + '" data-state="' + (checked ? 'checked' : 'unchecked') + '" tabindex="-1" class="' + SELECT_ITEM_CLASS + '" data-radix-collection-item="" data-value="' + escapeHtml(o.value) + '">' +
                    '<span class="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">' + (checked ? '<span aria-hidden="true">' + ICON.check('h-4 w-4') + '</span>' : '') + '</span>' +
                    '<span id="select-item-' + i + '">' + escapeHtml(o.textContent) + '</span></div>';
            }).join('');
            wrapper.innerHTML = '<div data-side="bottom" data-align="start" role="listbox" id="select-content" data-state="open" dir="ltr" class="' + SELECT_CONTENT_CLASS + '" tabindex="-1">' +
                SELECT_VIEWPORT_STYLE +
                '<div data-radix-select-viewport="" role="presentation" class="p-1 h-[var(--radix-select-trigger-height)] w-full min-w-[var(--radix-select-trigger-width)]" style="position: relative; flex: 1 1 0%; overflow: hidden auto;">' + items + '</div></div>';
            var content = wrapper.firstElementChild;
            content.style.cssText = 'box-sizing: border-box; display: flex; flex-direction: column; outline: none; --radix-select-content-transform-origin: var(--radix-popper-transform-origin); --radix-select-content-available-width: var(--radix-popper-available-width); --radix-select-content-available-height: var(--radix-popper-available-height); --radix-select-trigger-width: var(--radix-popper-anchor-width); --radix-select-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;';
            document.body.appendChild(wrapper);
            position(trigger, wrapper, content);

            trigger.setAttribute('aria-expanded', 'true');
            trigger.setAttribute('data-state', 'open');
            lockScroll();

            var optionEls = $$('[role=option]', content);
            var highlight = function (el) {
                optionEls.forEach(function (o) { o.removeAttribute('data-highlighted'); });
                if (el) {
                    el.setAttribute('data-highlighted', '');
                    el.focus({ preventScroll: true });
                    el.scrollIntoView({ block: 'nearest' });
                } else {
                    content.focus({ preventScroll: true });
                }
            };
            optionEls.forEach(function (el) {
                el.addEventListener('pointermove', function () { if (document.activeElement !== el) highlight(el); });
                el.addEventListener('pointerleave', function () { highlight(null); });
                el.addEventListener('click', function () { choose(el.getAttribute('data-value')); });
            });
            content.addEventListener('keydown', function (e) {
                var i = optionEls.indexOf(document.activeElement);
                if (e.key === 'ArrowDown') { e.preventDefault(); highlight(optionEls[Math.min(i + 1, optionEls.length - 1)]); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(optionEls[Math.max(i - 1, 0)]); }
                else if (e.key === 'Home') { e.preventDefault(); highlight(optionEls[0]); }
                else if (e.key === 'End') { e.preventDefault(); highlight(optionEls[optionEls.length - 1]); }
                else if ((e.key === 'Enter' || e.key === ' ') && i > -1) { e.preventDefault(); choose(optionEls[i].getAttribute('data-value')); }
                else if (e.key === 'Tab') { e.preventDefault(); }
                else if (e.key.length === 1) { // typeahead
                    var k = e.key.toLowerCase();
                    var match = optionEls.slice(i + 1).concat(optionEls.slice(0, i + 1)).filter(function (o) { return o.textContent.trim().toLowerCase().indexOf(k) === 0; })[0];
                    if (match) highlight(match);
                }
            });
            var onOutside = function (e) { if (!content.contains(e.target)) { e.preventDefault(); close(); } };
            var onResize = function () { position(trigger, wrapper, content); };
            document.addEventListener('pointerdown', onOutside, true);
            window.addEventListener('resize', onResize);

            var layer = { close: close };
            layers.push(layer);
            state.open = { wrapper: wrapper, content: content, layer: layer, cleanup: function () {
                document.removeEventListener('pointerdown', onOutside, true);
                window.removeEventListener('resize', onResize);
            } };
            highlight(optionEls.filter(function (o) { return o.getAttribute('data-state') === 'checked'; })[0] || optionEls[0]);
        }

        function close() {
            var o = state.open;
            if (!o) return;
            state.open = null;
            o.cleanup();
            layers.splice(layers.indexOf(o.layer), 1);
            trigger.setAttribute('aria-expanded', 'false');
            trigger.setAttribute('data-state', 'closed');
            unlockScroll();
            animateOut([o.content], function () { o.wrapper.remove(); });
            trigger.focus({ preventScroll: true });
        }

        function choose(value) {
            self.setValue(value);
            native.dispatchEvent(new Event('change', { bubbles: true }));
            close();
        }

        // Radix opens on pointerdown for mouse, click for touch/pen, and on keyboard.
        trigger.addEventListener('pointerdown', function (e) {
            if (e.button !== 0 || e.ctrlKey || e.pointerType !== 'mouse') return;
            e.preventDefault();
            trigger.focus();
            open();
        });
        trigger.addEventListener('click', function (e) {
            if (e.detail === 0 || state.open) return; // keyboard handled below
            open();
        });
        trigger.addEventListener('keydown', function (e) {
            if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].indexOf(e.key) > -1) { e.preventDefault(); open(); }
        });
    }

    /** floating-ui style placement: bottom-start, flip to top, shift inside viewport. */
    function position(trigger, wrapper, content) {
        var rect = trigger.getBoundingClientRect();
        var vw = document.documentElement.clientWidth, vh = window.innerHeight;
        wrapper.style.cssText = 'position: fixed; left: 0px; top: 0px; min-width: max-content; z-index: 50;' +
            ' --radix-popper-anchor-width: ' + rect.width + 'px; --radix-popper-anchor-height: ' + rect.height + 'px;';
        var h = content.offsetHeight, w = content.offsetWidth;
        var below = vh - rect.bottom - COLLISION_PADDING, above = rect.top - COLLISION_PADDING;
        var side = (h > below && above > below) ? 'top' : 'bottom';
        var x = Math.max(COLLISION_PADDING, Math.min(rect.left, vw - w - COLLISION_PADDING));
        var y = side === 'bottom' ? rect.bottom : rect.top - h;
        content.setAttribute('data-side', side);
        wrapper.style.transform = 'translate(' + Math.round(x) + 'px, ' + Math.round(y) + 'px)';
        wrapper.style.setProperty('--radix-popper-available-width', (vw - COLLISION_PADDING * 2) + 'px');
        wrapper.style.setProperty('--radix-popper-available-height', (side === 'bottom' ? below : above) + 'px');
        wrapper.style.setProperty('--radix-popper-transform-origin', (rect.left - x) + 'px ' + (side === 'bottom' ? '0px' : h + 'px'));
    }

    /* ------------------------------------------------------------------
     * Sonner toasts (used by the contact forms)
     * ---------------------------------------------------------------- */
    var sonner = (function () {
        var VISIBLE = 3, GAP = 14, LIFETIME = 4000, UNMOUNT_DELAY = 200;
        var section = $('section[aria-label^="Notifications alt"]');
        var ol = null, toasts = [], expanded = false, nextId = 1;
        var ICONS = {
            success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" height="20" width="20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"></path></svg>',
            error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" height="20" width="20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path></svg>'
        };

        function ensureList() {
            if (ol) return;
            ol = document.createElement('ol');
            ol.setAttribute('dir', 'ltr');
            ol.setAttribute('tabindex', '-1');
            ol.className = 'toaster group';
            ol.setAttribute('data-sonner-toaster', 'true');
            ol.setAttribute('data-theme', 'light');
            ol.setAttribute('data-y-position', 'bottom');
            ol.setAttribute('data-lifted', 'false');
            ol.setAttribute('data-x-position', 'right');
            ol.style.cssText = '--front-toast-height: 0px; --width: 356px; --gap: 14px; --offset-top: 32px; --offset-right: 32px; --offset-bottom: 32px; --offset-left: 32px; --mobile-offset-top: 16px; --mobile-offset-right: 16px; --mobile-offset-bottom: 16px; --mobile-offset-left: 16px;';
            ol.addEventListener('mouseenter', function () { setExpanded(true); });
            ol.addEventListener('mousemove', function () { setExpanded(true); });
            ol.addEventListener('mouseleave', function () { setExpanded(false); });
            section.appendChild(ol);
        }

        function setExpanded(v) {
            if (expanded === v) return;
            expanded = v;
            toasts.forEach(function (t) { v ? t.pause() : t.resume(); });
            layout();
        }

        function layout() {
            if (!ol) return;
            var live = toasts.filter(function (t) { return !t.deleted; });
            ol.style.setProperty('--front-toast-height', (live[0] ? live[0].height : 0) + 'px');
            ol.setAttribute('data-lifted', String(expanded && live.length > 1));
            var before = 0;
            toasts.forEach(function (t, index) {
                var offset = t.removed ? t.offsetBeforeRemove : index * GAP + before;
                if (!t.removed) t.offsetBeforeRemove = offset;
                var el = t.el;
                el.setAttribute('data-index', index);
                el.setAttribute('data-front', String(index === 0));
                el.setAttribute('data-visible', String(index < VISIBLE));
                el.setAttribute('data-expanded', String(expanded));
                el.setAttribute('data-removed', String(t.removed));
                el.style.setProperty('--index', index);
                el.style.setProperty('--toasts-before', index);
                el.style.setProperty('--z-index', toasts.length - index);
                el.style.setProperty('--offset', offset + 'px');
                el.style.setProperty('--initial-height', t.height + 'px');
                before += t.height;
            });
        }

        function show(type, message) {
            ensureList();
            var li = document.createElement('li');
            li.setAttribute('tabindex', '0');
            li.className = 'group toast group-[.toaster]:bg-background group-[.toaster]:text-foreground group-[.toaster]:border-border group-[.toaster]:shadow-lg';
            var attrs = { 'data-sonner-toast': '', 'data-styled': 'true', 'data-mounted': 'false', 'data-promise': 'false', 'data-swiped': 'false', 'data-removed': 'false', 'data-visible': 'true', 'data-y-position': 'bottom', 'data-x-position': 'right', 'data-index': '0', 'data-front': 'true', 'data-swiping': 'false', 'data-dismissible': 'true', 'data-type': type, 'data-swipe-out': 'false', 'data-expanded': 'false' };
            Object.keys(attrs).forEach(function (k) { li.setAttribute(k, attrs[k]); });
            li.innerHTML = '<div data-icon="" class="">' + ICONS[type] + '</div><div data-content="" class=""><div data-title="" class="">' + escapeHtml(message) + '</div></div>';
            ol.insertBefore(li, ol.firstChild);

            var t = { id: nextId++, el: li, removed: false, deleted: false, height: 0, offsetBeforeRemove: 0 };
            // measure natural height
            li.style.height = 'auto';
            t.height = li.getBoundingClientRect().height;
            li.style.height = '';

            var remaining = LIFETIME, startedAt = 0, timer = null;
            t.pause = function () { if (timer) { clearTimeout(timer); timer = null; remaining -= Date.now() - startedAt; } };
            t.resume = function () {
                if (timer || t.removed) return;
                startedAt = Date.now();
                timer = setTimeout(function () { dismiss(t); }, Math.max(0, remaining));
            };
            toasts.unshift(t);
            layout();
            requestAnimationFrame(function () { li.setAttribute('data-mounted', 'true'); });
            if (!expanded) t.resume();
        }

        function dismiss(t) {
            if (t.removed) return;
            t.removed = true;
            layout();
            setTimeout(function () {
                t.deleted = true;
                t.el.remove();
                toasts.splice(toasts.indexOf(t), 1);
                if (!toasts.length && ol) { ol.remove(); ol = null; expanded = false; }
                layout();
            }, UNMOUNT_DELAY);
        }

        return {
            success: function (m) { show('success', m); },
            error: function (m) { show('error', m); }
        };
    })();

    /* ------------------------------------------------------------------
     * shadcn/Radix toast (used by the course book page) — one at a time
     * ---------------------------------------------------------------- */
    var shadcnToast = (function () {
        var DURATION = 5000;
        var current = null;
        var viewport = $('div[role=region][aria-label^="Notifications (F8)"] ol');

        function dismiss(t) {
            if (!t || t.closed) return;
            t.closed = true;
            clearTimeout(t.timer);
            animateOut([t.el]);
            if (current === t) current = null;
        }

        return function (opts) {
            if (!viewport) return;
            dismiss(current);
            var variant = opts.variant === 'destructive'
                ? 'destructive group border-destructive bg-destructive text-destructive-foreground'
                : 'border bg-background text-foreground';
            var li = document.createElement('li');
            li.setAttribute('role', 'status');
            li.setAttribute('aria-live', 'off');
            li.setAttribute('aria-atomic', 'true');
            li.setAttribute('tabindex', '0');
            li.setAttribute('data-state', 'open');
            li.setAttribute('data-swipe-direction', 'right');
            li.setAttribute('data-radix-collection-item', '');
            li.className = 'group pointer-events-auto relative flex w-full items-center justify-between space-x-4 overflow-hidden rounded-md border p-6 pr-8 shadow-lg transition-all data-[swipe=cancel]:translate-x-0 data-[swipe=end]:translate-x-[var(--radix-toast-swipe-end-x)] data-[swipe=move]:translate-x-[var(--radix-toast-swipe-move-x)] data-[swipe=move]:transition-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[swipe=end]:animate-out data-[state=closed]:fade-out-80 data-[state=closed]:slide-out-to-right-full data-[state=open]:slide-in-from-top-full data-[state=open]:sm:slide-in-from-bottom-full ' + variant;
            li.style.cssText = 'user-select: none; touch-action: none;';
            li.innerHTML = '<div class="grid gap-1">' +
                (opts.title ? '<div class="text-sm font-semibold">' + escapeHtml(opts.title) + '</div>' : '') +
                (opts.description ? '<div class="text-sm opacity-90">' + escapeHtml(opts.description) + '</div>' : '') +
                '</div><button type="button" class="absolute right-2 top-2 rounded-md p-1 text-foreground/50 opacity-0 transition-opacity hover:text-foreground focus:opacity-100 focus:outline-none focus:ring-2 group-hover:opacity-100 group-[.destructive]:text-red-300 group-[.destructive]:hover:text-red-50 group-[.destructive]:focus:ring-red-400 group-[.destructive]:focus:ring-offset-red-600" toast-close="" data-radix-toast-announce-exclude="">' + ICON.x('h-4 w-4') + '</button>';
            viewport.appendChild(li);

            var t = { el: li, closed: false, timer: null, remaining: DURATION, started: 0 };
            var start = function () { t.started = Date.now(); t.timer = setTimeout(function () { dismiss(t); }, t.remaining); };
            viewport.addEventListener('mouseenter', function () { clearTimeout(t.timer); t.remaining -= Date.now() - t.started; });
            viewport.addEventListener('mouseleave', function () { if (!t.closed) start(); });
            li.querySelector('[toast-close]').addEventListener('click', function () { dismiss(t); });
            current = t;
            start();
        };
    })();

    /* ------------------------------------------------------------------
     * Supabase Edge Functions
     * ---------------------------------------------------------------- */
    function invoke(fn, body) {
        return fetch(APP.supabaseUrl + '/functions/v1/' + fn, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + APP.supabaseKey,
                'apikey': APP.supabaseKey
            },
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.text().then(function (text) {
                var data = null;
                try { data = text ? JSON.parse(text) : null; } catch (e) { data = text; }
                if (!res.ok) {
                    var err = new Error('Edge Function returned a non-2xx status code');
                    err.status = res.status;
                    err.data = data;
                    throw err;
                }
                return data;
            });
        });
    }

    /* ------------------------------------------------------------------
     * Contact forms (home "Get in Touch" + /contact "Let's Connect")
     * ---------------------------------------------------------------- */
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function initContactForms(selects) {
        $$('form[data-contact-form]').forEach(function (form) {
            var kind = form.getAttribute('data-contact-form');
            var trigger = $('button[role=combobox]', form);
            var select = selects.filter(function (s) { return s.trigger === trigger; })[0];
            var submit = $('button[type=submit]', form);
            var submitLabel = submit.innerHTML;
            var field = function (name) { return form.querySelector('[name="' + name + '"]'); };
            var sending = false;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (sending) return;
                var data = {
                    name: field('name').value,
                    email: field('email').value,
                    phone: field('phone').value,
                    message: field('message').value
                };
                var serviceValue = select ? select.value() : '';
                var missing = !data.name || !data.email || !data.message || (kind === 'contact' && !serviceValue);
                if (missing) { sonner.error('Please fill in all required fields'); return; }
                if (!EMAIL_RE.test(data.email)) { sonner.error('Please enter a valid email address'); return; }

                var payload, successMessage, lead;
                if (kind === 'contact') {
                    payload = { service: serviceValue, name: data.name, email: data.email, phone: data.phone, message: data.message };
                    successMessage = "Thank you for your message! We'll get back to you within 24 hours. A confirmation email has been sent to your inbox.";
                    lead = { content_name: serviceValue, content_category: 'Service Inquiry' };
                } else {
                    var service = (select && select.label()) || 'General Inquiry';
                    payload = { service: service, name: data.name, email: data.email, phone: data.phone, message: data.message };
                    successMessage = "Thank you for your message! We'll get back to you within 24 hours.";
                    lead = { content_name: service, content_category: 'Homepage Inquiry' };
                }

                sending = true;
                submit.disabled = true;
                submit.innerHTML = ICON.loader('mr-2 h-4 w-4 animate-spin') + 'Sending...';

                // Save the query on our server (admin dashboard) and send the email notification;
                // the visitor sees success if either one worked.
                var saveError = null;
                var saved = fetch(APP.basePath + '/api/contact', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Object.assign({ source: kind, website: field('website') ? field('website').value : '' }, payload))
                }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (body) {
                        if (!res.ok) throw new Error(body.error || 'Could not save the message (' + res.status + ')');
                        return true;
                    });
                }).catch(function (err) { console.error('Error saving message:', err); saveError = err; return false; });
                var mailed = invoke('send-contact-email', payload).then(function () { return true; }, function (err) {
                    console.error('Error sending email:', err);
                    return false;
                });

                Promise.all([saved, mailed]).then(function (results) {
                    if (!results[0] && !results[1]) {
                        sonner.error(saveError && /Too many|valid email/.test(saveError.message) ? saveError.message : 'Failed to send message. Please try again.');
                        return;
                    }
                    sonner.success(successMessage);
                    track('Lead', lead);
                    ['name', 'email', 'phone', 'message'].forEach(function (n) { field(n).value = ''; });
                    if (select) select.setValue('');
                }).then(function () {
                    sending = false;
                    submit.disabled = false;
                    submit.innerHTML = submitLabel;
                });
            });
        });
    }

    /* ------------------------------------------------------------------
     * Amazon KDP Course Book: Stripe checkout + purchase verification
     * ---------------------------------------------------------------- */
    function initCourseBook() {
        var buttons = $$('[data-checkout]');
        if (!buttons.length) return;
        var labels = buttons.map(function (b) { return b.innerHTML; });

        var setLoading = function (loading) {
            buttons.forEach(function (b, i) {
                b.disabled = loading;
                b.innerHTML = loading ? ICON.loaderCircle('w-4 h-4 mr-2 animate-spin') + 'Processing...' : labels[i];
            });
        };

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setLoading(true);
                invoke('create-checkout-session', {}).then(function (data) {
                    if (data && data.url) window.location.href = data.url;
                    else throw new Error('No checkout URL received');
                }).catch(function (err) {
                    console.error('Error creating checkout:', err);
                    shadcnToast({ title: 'Checkout Error', description: 'Could not start checkout. Please try again.', variant: 'destructive' });
                    setLoading(false);
                });
            });
        });

        // Returning from Stripe with ?session_id=...
        var sessionId = new URLSearchParams(window.location.search).get('session_id');
        if (!sessionId) return;

        var header = $('header');
        var overlay = $('#verify-overlay-template').content.firstElementChild.cloneNode(true);
        header.parentNode.insertBefore(overlay, header.nextSibling);

        invoke('verify-purchase', { session_id: sessionId }).then(function (data) {
            if (data && data.success) {
                history.replaceState(null, '', window.location.pathname);
                openPurchaseDialog(data.download_url || null);
            } else {
                shadcnToast({ title: 'Verification Failed', description: (data && data.error) || 'Could not verify your purchase. Please contact support.', variant: 'destructive' });
            }
        }).catch(function (err) {
            console.error('Error verifying purchase:', err);
            shadcnToast({ title: 'Error', description: 'Something went wrong. Please contact support.', variant: 'destructive' });
        }).then(function () {
            overlay.remove();
        });
    }

    function openPurchaseDialog(downloadUrl) {
        openModal($('#purchase-dialog-template'), {
            onOpen: function (content) {
                var download = $('[data-download]', content);
                var unavailable = $('[data-download-unavailable]', content);
                if (downloadUrl) unavailable.remove();
                else download.disabled = true;
                download.addEventListener('click', function () {
                    if (downloadUrl) window.open(downloadUrl, '_blank');
                    else shadcnToast({ title: 'Download Unavailable', description: 'The PDF is not available yet. Please contact support via WhatsApp.', variant: 'destructive' });
                });
            }
        });
    }

    /* ------------------------------------------------------------------
     * Buttons that navigate (service CTAs, WhatsApp support) + CTA tracking
     * ---------------------------------------------------------------- */
    function initLinks() {
        $$('button[data-href]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var href = btn.getAttribute('data-href');
                if (btn.getAttribute('data-target') === '_blank') window.open(href, '_blank');
                else window.location.href = href;
            });
        });
        $$('[data-track-checkout]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                track('InitiateCheckout', { content_name: btn.getAttribute('data-track-checkout'), content_category: 'CTA Click' });
            });
        });
    }

    /* ------------------------------------------------------------------
     * Blog: "Copy link" share button
     * ---------------------------------------------------------------- */
    function initCopyLinks() {
        $$('[data-copy-link]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var link = btn.getAttribute('data-copy-link');
                var fallback = function () { window.prompt('Copy this link:', link); };
                if (!navigator.clipboard) return fallback();
                navigator.clipboard.writeText(link).then(function () { sonner.success('Link copied to clipboard'); }, fallback);
            });
        });
    }

    /* ------------------------------------------------------------------
     * Boot
     * ---------------------------------------------------------------- */
    function boot() {
        initReveal();
        initMobileMenu();
        var selects = $$('button[role=combobox]').map(function (t) { return new Select(t); });
        initContactForms(selects);
        initCourseBook();
        initLinks();
        initCopyLinks();
        if (APP.track) track('ViewContent', APP.track);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
