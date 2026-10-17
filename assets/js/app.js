/**
 * Publishing Guru — front-end behaviour.
 *
 * Vanilla-JS port of the interactive parts of the original React build:
 * scroll reveal, mobile menu sheet, select dropdowns, toasts (Sonner),
 * contact forms, and Facebook Pixel events.
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
        // Safari doesn't focus a tapped button, so callers can name where focus returns on close.
        var previousFocus = opts.returnFocus || document.activeElement;
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
        // Keep Tab / Shift+Tab inside the dialog while it is open.
        content.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;
            var tabbable = $$('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])', content)
                .filter(function (el) { return el.offsetParent !== null; });
            if (!tabbable.length) return;
            var first = tabbable[0], last = tabbable[tabbable.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === content)) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        });
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
     * Mark nav links to the current page (aria-current + underline style)
     * ---------------------------------------------------------------- */
    function markCurrentLinks(root) {
        var here = location.pathname.replace(/\/+$/, '') || '/';
        $$('a[href]', root).forEach(function (a) {
            if (a.closest('form') || a.target === '_blank') return;
            var path = a.pathname.replace(/\/+$/, '') || '/';
            if (a.origin === location.origin && path === here && !a.search) a.setAttribute('aria-current', 'page');
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
                returnFocus: trigger,
                onOpen: function (content) {
                    var toggle = $('[data-services-toggle]', content);
                    var list = $('template[data-services-list]', content);
                    var chevron = toggle.querySelector(':scope > svg');
                    var rendered = null;
                    markCurrentLinks(content);
                    var render = function () {
                        toggle.setAttribute('aria-expanded', servicesOpen ? 'true' : 'false');
                        chevron.setAttribute('class', 'lucide lucide-chevron-down w-4 h-4 transition-transform ' + (servicesOpen ? 'rotate-180' : ''));
                        if (servicesOpen && !rendered) {
                            rendered = list.content.firstElementChild.cloneNode(true);
                            list.parentNode.insertBefore(rendered, list);
                            markCurrentLinks(rendered);
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
     * Contact forms (home "Get in Touch" + /contact "Let's Connect")
     * ---------------------------------------------------------------- */
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    var ALERT_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>';

    /** Field rules, worded like the server's (includes/contact.php). Returns an error message or ''. */
    function contactFieldError(name, value) {
        value = (value || '').trim();
        switch (name) {
            case 'name': return value ? '' : 'Please enter your name.';
            case 'email':
                if (!value) return 'Please enter your email address.';
                return EMAIL_RE.test(value) ? '' : 'That email doesn’t look right. Please check it (e.g. name@example.com).';
            case 'phone': return !value || value.replace(/\D/g, '').length >= 7 ? '' : 'Please enter a valid phone number, or leave it blank.';
            case 'message':
                if (!value) return 'Please tell us a little about your project.';
                return value.length >= 10 ? '' : 'Please add a few more details (at least 10 characters).';
            case 'service': return value ? '' : 'Please choose a service.';
        }
        return '';
    }

    function initContactForms(selects) {
        $$('form[data-contact-form]').forEach(function (form) {
            var kind = form.getAttribute('data-contact-form');
            var trigger = $('button[role=combobox]', form);
            var select = selects.filter(function (s) { return s.trigger === trigger; })[0];
            var submit = $('button[type=submit]', form);
            var submitLabel = submit.innerHTML;
            var field = function (name) { return form.querySelector('[name="' + name + '"]'); };
            var sending = false, attempted = false, alertBox = null;

            // Our own messages replace the browser's validation bubbles.
            form.noValidate = true;
            var fields = { name: field('name'), email: field('email'), phone: field('phone'), message: field('message') };
            if (kind === 'contact' && select) fields.service = trigger;
            var valueOf = function (name) { return name === 'service' ? select.value() : fields[name].value; };

            /** Show (or clear, with msg '') the error under a field and mark it invalid for screen readers. */
            var setError = function (name, msg) {
                var el = fields[name];
                if (!el) return;
                var wrap = el.closest('.space-y-2') || el.parentNode;
                var id = (el.id || kind + '-' + name) + '-error';
                var p = document.getElementById(id);
                var described = (el.getAttribute('aria-describedby') || '').split(' ').filter(function (x) { return x && x !== id; });
                if (!msg) {
                    if (p) p.remove();
                    el.removeAttribute('aria-invalid');
                } else {
                    if (!p) {
                        p = document.createElement('p');
                        p.className = 'field-error';
                        p.id = id;
                        wrap.appendChild(p);
                    }
                    p.innerHTML = ALERT_ICON + '<span>' + escapeHtml(msg) + '</span>';
                    el.setAttribute('aria-invalid', 'true');
                    described.push(id);
                }
                if (described.length) el.setAttribute('aria-describedby', described.join(' '));
                else el.removeAttribute('aria-describedby');
            };
            /** Check every field; returns the top-most invalid one on the page (to focus), or null. */
            var validate = function () {
                var invalid = [];
                Object.keys(fields).forEach(function (name) {
                    var msg = contactFieldError(name, valueOf(name));
                    setError(name, msg);
                    if (msg) invalid.push(fields[name]);
                });
                invalid.sort(function (a, b) { return a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1; });
                return invalid[0] || null;
            };
            var clearErrors = function () { Object.keys(fields).forEach(function (n) { setError(n, ''); }); };

            // After the first attempt, re-check each field as it's edited (errors clear as soon as they're fixed).
            ['name', 'email', 'phone', 'message'].forEach(function (name) {
                var recheck = function () { if (attempted || fields[name].hasAttribute('aria-invalid')) setError(name, contactFieldError(name, valueOf(name))); };
                fields[name].addEventListener('input', recheck);
                fields[name].addEventListener('blur', recheck);
            });
            if (fields.service) {
                new MutationObserver(function () { if (attempted) setError('service', contactFieldError('service', valueOf('service'))); })
                    .observe(trigger, { childList: true, subtree: true, characterData: true });
            }

            /** Form-level problem (server/network): message plus other ways to reach us. */
            var showAlert = function (msg) {
                if (!alertBox) {
                    alertBox = document.createElement('div');
                    alertBox.className = 'form-alert';
                    alertBox.setAttribute('role', 'alert');
                    submit.parentNode.insertBefore(alertBox, submit);
                }
                alertBox.innerHTML = ALERT_ICON + '<div><p><strong>' + escapeHtml(msg) + '</strong></p><p>You can also reach us on '
                    + '<a href="https://wa.me/' + encodeURIComponent(APP.whatsapp) + '" target="_blank" rel="noopener noreferrer">WhatsApp</a> or at '
                    + '<a href="mailto:' + escapeHtml(APP.email) + '">' + escapeHtml(APP.email) + '</a>.</p></div>';
            };
            var clearAlert = function () { if (alertBox) { alertBox.remove(); alertBox = null; } };

            /** Swap the form for a confirmation panel; "Send another message" brings back a clean form. */
            var showSuccess = function (data) {
                var first = data.name.trim().split(/\s+/)[0];
                var panel = document.createElement('div');
                panel.className = 'form-success';
                panel.setAttribute('role', 'status');
                panel.setAttribute('tabindex', '-1');
                panel.innerHTML = '<span class="form-success-icon">' + ICON.check('h-7 w-7') + '</span>'
                    + '<h3>Message sent!</h3>'
                    + '<p>Thanks, ' + escapeHtml(first) + '. We’ve received your message and will reply to <strong>' + escapeHtml(data.email.trim()) + '</strong> within 24 hours.</p>'
                    + '<div class="form-success-actions">'
                    + '<a href="' + APP.basePath + '/book-call" class="form-success-primary">Book a free call</a>'
                    + '<button type="button" class="form-success-secondary">Send another message</button></div>';
                form.hidden = true;
                form.parentNode.insertBefore(panel, form.nextSibling);
                panel.focus({ preventScroll: true });
                panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                panel.querySelector('.form-success-secondary').addEventListener('click', function () {
                    panel.remove();
                    form.hidden = false;
                    fields.name.focus();
                });
            };

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (sending) return;
                attempted = true;
                clearAlert();
                var firstInvalid = validate();
                if (firstInvalid) { firstInvalid.focus(); return; }

                var data = { name: fields.name.value, email: fields.email.value, phone: fields.phone.value, message: fields.message.value };
                var service, lead;
                if (kind === 'contact') {
                    service = select.value();
                    lead = { content_name: service, content_category: 'Service Inquiry' };
                } else {
                    service = (select && select.label()) || 'General Inquiry';
                    lead = { content_name: service, content_category: 'Homepage Inquiry' };
                }

                sending = true;
                submit.disabled = true;
                submit.setAttribute('aria-busy', 'true');
                submit.innerHTML = ICON.loader('mr-2 h-4 w-4 animate-spin') + 'Sending...';

                // Save the query on our server (shown in the admin dashboard), with where the visit came from.
                fetch(APP.basePath + '/api/contact', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Object.assign({
                        source: kind,
                        service: service,
                        website: field('website') ? field('website').value : '',
                        attribution: readAttribution()
                    }, data))
                }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (body) {
                        if (!res.ok) { var err = new Error(body.error || 'HTTP ' + res.status); err.status = res.status; err.fields = body.fields; throw err; }
                    });
                }).then(function () {
                    track('Lead', lead);
                    showSuccess(data);
                    ['name', 'email', 'phone', 'message'].forEach(function (n) { fields[n].value = ''; });
                    if (select) select.setValue('');
                    attempted = false;
                    clearErrors();
                }, function (err) {
                    if (err.fields) { // the server disagreed with a field: show it there
                        var first = null;
                        Object.keys(err.fields).forEach(function (n) { setError(n, err.fields[n]); if (!first && fields[n]) first = fields[n]; });
                        if (first) { first.focus(); return; }
                    }
                    if (err.status === 429) showAlert('You’ve sent several messages in a short time. Please wait a few minutes and try again.');
                    else if (!err.status) showAlert('We couldn’t send your message. Please check your internet connection and try again.');
                    else showAlert('Something went wrong on our side and your message wasn’t sent. Please try again in a moment.');
                }).then(function () {
                    sending = false;
                    submit.disabled = false;
                    submit.removeAttribute('aria-busy');
                    submit.innerHTML = submitLabel;
                });
            });
        });
    }

    /* ------------------------------------------------------------------
     * Visit attribution: UTM tags, ad click IDs, referrer and landing page,
     * captured when the visit starts and sent with contact-form messages.
     * Kept for this browser tab's session only (sessionStorage).
     * ---------------------------------------------------------------- */
    var ATTRIBUTION_KEY = 'pg-attribution';
    var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
    var CLICK_IDS = ['gclid', 'fbclid', 'msclkid', 'ttclid'];

    function readAttribution() {
        try { return JSON.parse(sessionStorage.getItem(ATTRIBUTION_KEY)) || {}; } catch (e) { return {}; }
    }

    function initAttribution() {
        var params = new URLSearchParams(location.search);
        var record = {};
        UTM_KEYS.forEach(function (k) { var v = params.get(k); if (v) record[k] = v.slice(0, 150); });
        CLICK_IDS.some(function (k) { if (params.get(k)) { record.click_id = k; return true; } return false; });
        var campaign = Object.keys(record).length > 0;
        var existing = readAttribution();
        // Keep the visit's first record; a new campaign link within the visit replaces it (last click wins).
        if (existing.landing_page && !campaign) return;
        var ref = '';
        try { if (document.referrer && new URL(document.referrer).host !== location.host) ref = document.referrer.slice(0, 255); } catch (e) {}
        record.referrer = ref;
        record.landing_page = (location.pathname + location.search).slice(0, 255);
        try { sessionStorage.setItem(ATTRIBUTION_KEY, JSON.stringify(record)); } catch (e) {}
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
        // WhatsApp links (floating button, course book buy/support)
        $$('[data-track-contact]').forEach(function (link) {
            link.addEventListener('click', function () { track('Contact', { content_category: 'WhatsApp' }); });
        });
    }

    /* ------------------------------------------------------------------
     * Blog: "Copy link" share button
     * ---------------------------------------------------------------- */
    /** Copy text: Clipboard API, falling back to a hidden textarea (plain-http copies, older browsers). */
    function copyText(text) {
        var legacy = function () {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
            document.body.appendChild(ta);
            ta.select();
            ta.setSelectionRange(0, text.length);
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) {}
            ta.remove();
            return ok ? Promise.resolve() : Promise.reject(new Error('copy failed'));
        };
        if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text).catch(legacy);
        return legacy();
    }

    /** Wire a copy button: copies, shows "Copied!" on the button for 2s, and toasts. */
    function bindCopy(btn, getText, message) {
        var label = btn.querySelector('span') || btn;
        var original = label.textContent, timer = null;
        btn.addEventListener('click', function () {
            copyText(getText()).then(function () {
                label.textContent = 'Copied!';
                btn.classList.add('is-copied');
                clearTimeout(timer);
                timer = setTimeout(function () { label.textContent = original; btn.classList.remove('is-copied'); }, 2000);
                sonner.success(message);
            }, function () {
                sonner.error('Could not copy. Please select the text and copy it manually.');
            });
        });
    }

    function initCopyLinks() {
        $$('[data-copy-link]').forEach(function (btn) {
            bindCopy(btn, function () { return btn.getAttribute('data-copy-link'); }, 'Link copied to clipboard');
        });
        $$('[data-copy]').forEach(function (btn) {
            bindCopy(btn, function () { return btn.getAttribute('data-copy'); }, 'Copied to clipboard');
        });
        // Code blocks in blog articles get their own copy button.
        $$('.blog-content pre').forEach(function (pre) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'copy-btn';
            btn.setAttribute('aria-label', 'Copy code');
            btn.innerHTML = '<span>Copy</span>';
            pre.appendChild(btn);
            bindCopy(btn, function () { return (pre.querySelector('code') || pre).innerText.replace(/\n?Copy(ied!)?$/, ''); }, 'Code copied to clipboard');
        });
    }

    /* ------------------------------------------------------------------
     * Scroll / reading progress bar: tracks the article on blog posts,
     * otherwise the whole page
     * ---------------------------------------------------------------- */
    function initScrollProgress() {
        var bar = document.querySelector('[data-scroll-progress] span');
        if (!bar) return;
        var article = document.querySelector('.blog-content');
        var ticking = false;
        var update = function () {
            ticking = false;
            var done, total;
            // Article: 0 when its top reaches the top of the screen, 1 when its end comes into view.
            var rect = article && article.getBoundingClientRect();
            if (rect && rect.height - window.innerHeight > 200) {
                done = -rect.top;
                total = rect.height - window.innerHeight;
            } else { // no article, or one shorter than about a screen: track the whole page
                done = window.scrollY;
                total = document.documentElement.scrollHeight - window.innerHeight;
            }
            var progress = total > 0 ? Math.min(1, Math.max(0, done / total)) : 0;
            bar.style.transform = 'scaleX(' + progress.toFixed(4) + ')';
        };
        var queue = function () { if (!ticking) { ticking = true; requestAnimationFrame(update); } };
        window.addEventListener('scroll', queue, { passive: true });
        window.addEventListener('resize', queue);
        window.addEventListener('load', queue);
        update();
    }

    /* ------------------------------------------------------------------
     * Cookie banner: the Facebook Pixel only loads after "Accept"
     * (see the Pixel snippet in includes/layout.php)
     * ---------------------------------------------------------------- */
    function initCookieBanner() {
        var banner = document.querySelector('[data-cookie-banner]');
        if (!banner) return;
        var KEY = 'pg-cookie-consent', choice = null;
        try { choice = localStorage.getItem(KEY); } catch (e) {}
        if (choice) return;
        var floating = document.querySelector('[data-floating-actions]');
        // Keep the floating buttons (back-to-top, WhatsApp) above the banner while it is shown.
        var lift = function () {
            if (floating) floating.style.bottom = (window.innerHeight - banner.getBoundingClientRect().top + 16) + 'px';
        };
        banner.hidden = false;
        lift();
        window.addEventListener('resize', lift);
        $$('[data-cookie-choice]', banner).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var value = btn.getAttribute('data-cookie-choice');
                try { localStorage.setItem(KEY, value); } catch (e) {}
                if (value === 'granted' && window.loadPixel) window.loadPixel();
                banner.hidden = true;
                window.removeEventListener('resize', lift);
                if (floating) floating.style.bottom = '';
            });
        });
    }

    /* ------------------------------------------------------------------
     * Back-to-top button: appears after scrolling about a screen down
     * ---------------------------------------------------------------- */
    function initBackToTop() {
        var btn = document.querySelector('[data-back-to-top]');
        if (!btn) return;
        var ticking = false;
        var update = function () {
            ticking = false;
            var show = window.scrollY > Math.max(400, window.innerHeight * 0.8);
            btn.classList.toggle('is-visible', show);
            btn.tabIndex = show ? 0 : -1;
        };
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; requestAnimationFrame(update); }
        }, { passive: true });
        update();
        btn.addEventListener('click', function () {
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
            // Move keyboard focus back to the top of the page too.
            var logo = document.querySelector('header a[href]');
            if (logo) logo.focus({ preventScroll: true });
        });
    }

    /* ------------------------------------------------------------------
     * Page-load progress bar: a thin bar at the top while the next page
     * loads, shown only if that takes more than a moment
     * ---------------------------------------------------------------- */
    function initPageProgress() {
        var bar = document.createElement('div');
        bar.className = 'page-progress';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);
        var timer = null;
        var start = function () {
            clearTimeout(timer);
            timer = setTimeout(function () { bar.classList.add('is-loading'); }, 150);
        };
        var stop = function () { clearTimeout(timer); bar.classList.remove('is-loading'); };
        document.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('a[href]');
            if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            if ((a.target && a.target !== '_self') || a.hasAttribute('download') || a.origin !== location.origin) return;
            if (a.pathname === location.pathname && a.search === location.search) return; // same page / #anchor
            start();
        });
        $$('form[method=get]').forEach(function (form) { form.addEventListener('submit', start); });
        // Back/forward cache restores the old page with the bar still running.
        window.addEventListener('pageshow', stop);
    }

    /* ------------------------------------------------------------------
     * Boot
     * ---------------------------------------------------------------- */
    function boot() {
        initAttribution();
        initReveal();
        initMobileMenu();
        var selects = $$('button[role=combobox]').map(function (t) { return new Select(t); });
        initContactForms(selects);
        initLinks();
        initCopyLinks();
        initCookieBanner();
        initBackToTop();
        initPageProgress();
        initScrollProgress();
        $$('header nav').forEach(markCurrentLinks);
        if (APP.track) track('ViewContent', APP.track);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
