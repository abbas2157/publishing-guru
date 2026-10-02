// Admin: mobile sidebar toggle.
(function () {
    var body = document.body;
    function set(open) { body.classList.toggle('sidebar-open', open); }
    document.querySelectorAll('[data-sidebar-open]').forEach(function (el) {
        el.addEventListener('click', function () { set(true); });
    });
    document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
        el.addEventListener('click', function () { set(false); });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
})();

// Admin: confirm destructive buttons (data-confirm="Question?").
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-confirm]');
    if (btn && !confirm(btn.getAttribute('data-confirm'))) e.preventDefault();
});

// Admin: blog post editor (Quill rich text, image uploads, slug, SEO defaults).
// Runs before the shared editor block below so the SEO preview starts with the right defaults.
(function () {
    var form = document.querySelector('[data-blog-form]');
    if (!form) return;
    var csrf = form.querySelector('input[name="csrf"]').value;
    var uploadUrl = form.dataset.uploadUrl;

    function slugify(text) {
        return text.normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase()
            .replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
    }

    function upload(file) {
        var data = new FormData();
        data.append('csrf', csrf);
        data.append('image', file);
        return fetch(uploadUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { error: 'Upload failed (' + r.status + ').' }; }); })
            .then(function (res) { if (res.error) throw new Error(res.error); return res; });
    }

    // Title → slug (until the slug is edited by hand) and SEO defaults.
    var title = form.querySelector('[data-post-title]');
    var slug = form.querySelector('[data-post-slug]');
    var excerpt = form.querySelector('[data-post-excerpt]');
    var metaTitle = form.querySelector('#meta-title');
    var metaDesc = form.querySelector('#meta-description');
    var serpSlug = form.querySelector('[data-serp-slug]');
    function syncDefaults() {
        metaTitle.dataset.default = title.value.trim() ? title.value.trim() + metaTitle.dataset.suffix : '';
        metaDesc.dataset.default = excerpt.value.trim();
        serpSlug.textContent = slug.value || slugify(title.value) || 'post-url';
    }
    title.addEventListener('input', function () {
        if (slug.dataset.auto === '1') slug.value = slugify(title.value);
        syncDefaults();
        metaTitle.dispatchEvent(new Event('input', { bubbles: true }));
    });
    slug.addEventListener('input', function () { slug.dataset.auto = slug.value ? '0' : '1'; syncDefaults(); });
    slug.addEventListener('blur', function () { slug.value = slugify(slug.value); syncDefaults(); });
    excerpt.addEventListener('input', function () {
        syncDefaults();
        metaDesc.dispatchEvent(new Event('input', { bubbles: true }));
    });
    syncDefaults();

    // Category: "+ New category…" reveals a name field.
    var catSelect = form.querySelector('[data-category-select]');
    var newCat = form.querySelector('[data-new-category]');
    catSelect.addEventListener('change', function () {
        newCat.hidden = catSelect.value !== '__new';
        if (!newCat.hidden) newCat.focus(); else newCat.value = '';
    });

    // Featured image.
    var featured = form.querySelector('[data-featured]');
    var pathInput = featured.querySelector('[data-featured-path]');
    var preview = featured.querySelector('[data-featured-preview]');
    var img = featured.querySelector('[data-featured-img]');
    var label = featured.querySelector('[data-featured-label]');
    var removeBtn = featured.querySelector('[data-featured-remove]');
    function setImage(path, url) {
        pathInput.value = path;
        img.src = url || '';
        img.hidden = !path;
        preview.classList.toggle('is-empty', !path);
        removeBtn.hidden = !path;
        label.textContent = path ? 'Replace image' : 'Upload image';
        pathInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
    featured.querySelector('[data-featured-input]').addEventListener('change', function (e) {
        var file = e.target.files[0];
        if (!file) return;
        label.textContent = 'Uploading…';
        preview.classList.add('is-loading');
        upload(file).then(function (res) { setImage(res.path, res.url); })
            .catch(function (err) { alert(err.message); label.textContent = pathInput.value ? 'Replace image' : 'Upload image'; })
            .then(function () { preview.classList.remove('is-loading'); e.target.value = ''; });
    });
    removeBtn.addEventListener('click', function () { setImage('', ''); });

    // Rich text editor.
    var holder = form.querySelector('[data-blog-editor]');
    var output = form.querySelector('[data-blog-content]');
    if (!window.Quill) {
        holder.outerHTML = '<p class="alert">The text editor could not load (no internet connection?). Reload the page to try again.</p>';
        form.addEventListener('submit', function (e) { e.preventDefault(); alert('The text editor did not load, so the post can\'t be saved safely. Reload the page.'); });
        return;
    }
    // getSemanticHTML() turns video embeds into plain links; keep the <iframe> (cleaned server-side).
    Quill.import('formats/video').prototype.html = function () { return this.domNode.outerHTML; };
    var quill = new Quill(holder, {
        theme: 'snow',
        placeholder: 'Write your post…',
        modules: {
            toolbar: {
                container: [
                    [{ header: [2, 3, 4, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'code-block'],
                    ['link', 'image', 'video'],
                    [{ align: [] }],
                    ['clean']
                ],
                handlers: {
                    image: function () {
                        var input = document.createElement('input');
                        input.type = 'file';
                        input.accept = 'image/jpeg,image/png,image/webp,image/gif';
                        input.onchange = function () {
                            if (!input.files[0]) return;
                            var range = quill.getSelection(true);
                            upload(input.files[0]).then(function (res) {
                                var alt = window.prompt('Alt text for this image (describe what it shows):', '') || '';
                                quill.insertEmbed(range.index, 'image', res.url, 'user');
                                quill.root.querySelectorAll('img').forEach(function (el) {
                                    if (el.getAttribute('src') === res.url && !el.hasAttribute('alt')) el.setAttribute('alt', alt.trim());
                                });
                                quill.setSelection(range.index + 1, 0);
                            }).catch(function (err) { alert(err.message); });
                        };
                        input.click();
                    }
                }
            }
        }
    });
    quill.on('text-change', function () { form.dispatchEvent(new Event('input')); });
    quill.root.addEventListener('click', function (e) {
        if (e.target.tagName !== 'IMG') return;
        var alt = window.prompt('Alt text for this image:', e.target.getAttribute('alt') || '');
        if (alt !== null) {
            e.target.setAttribute('alt', alt.trim());
            form.dispatchEvent(new Event('input'));
        }
    });
    form.addEventListener('submit', function () {
        output.value = quill.getLength() > 1 ? quill.getSemanticHTML().replace(/&nbsp;/g, ' ') : '';
    });
})();

// Admin: SEO & content editor.
(function () {
    var form = document.querySelector('[data-dirty-form]');
    if (!form) return;

    function norm(v) { return v.replace(/\s+/g, ' ').trim(); }

    // Grow textareas to fit their content.
    function autosize(el) {
        el.style.height = 'auto';
        el.style.height = (el.scrollHeight + 2) + 'px';
    }

    // Character counters for meta title / description.
    function count(el) {
        var out = document.querySelector('[data-counter-for="' + el.id + '"]');
        if (!out) return;
        var n = norm(el.value).length, min = +out.dataset.min, max = +out.dataset.max;
        out.textContent = n + ' / ' + max;
        out.className = 'counter ' + (n === 0 ? 'len-empty' : n < min || n > max ? 'len-warn' : 'len-ok');
    }

    // Live Google preview (empty fields fall back to the default, as on the site).
    function serp(el) {
        var out = document.querySelector('[data-serp-out="' + el.dataset.serp + '"]');
        if (!out) return;
        var text = norm(el.value) || norm(el.dataset.default || '');
        var max = el.dataset.serp === 'title' ? 60 : 160;
        out.textContent = text.length > max ? text.slice(0, max - 1).trim() + '…' : text;
    }

    // Schema JSON check (the server validates again on save).
    function checkJson(el) {
        var out = form.querySelector('[data-json-state]');
        var v = el.value.trim();
        if (!v) { out.textContent = 'Default schema'; out.className = 'json-state'; return; }
        try {
            var parsed = JSON.parse(v);
            var items = Array.isArray(parsed) ? parsed : [parsed];
            var bad = items.some(function (i) { return !i || typeof i !== 'object' || Array.isArray(i) || !i['@type']; });
            out.textContent = bad ? 'Each item needs an "@type"' : '✓ Valid JSON · ' + items.length + (items.length === 1 ? ' block' : ' blocks');
            out.className = 'json-state ' + (bad ? 'len-warn' : 'len-ok');
        } catch (e) {
            out.textContent = 'Invalid JSON: ' + e.message;
            out.className = 'json-state len-warn';
        }
    }

    function edited(el) {
        var field = el.closest('[data-field]');
        if (field && el.dataset.default !== undefined && field.querySelector('[data-reset]')) {
            field.classList.toggle('is-edited', norm(el.value) !== norm(el.dataset.default));
        }
    }

    function refresh(el) {
        if (el.hasAttribute('data-autosize')) autosize(el);
        if (el.dataset.serp) serp(el);
        if (el.hasAttribute('data-json')) checkJson(el);
        count(el);
    }

    form.querySelectorAll('.text-input').forEach(function (el) { refresh(el); edited(el); });
    window.addEventListener('resize', function () {
        form.querySelectorAll('[data-autosize]').forEach(autosize);
    });

    // Unsaved-changes guard.
    var dirty = false, label = form.querySelector('[data-dirty-label]');
    function markDirty() {
        if (dirty) return;
        dirty = true;
        document.body.classList.add('is-dirty');
        if (label) label.textContent = 'Unsaved changes';
    }
    form.addEventListener('input', function (e) {
        if (e.target.matches('.text-input')) {
            refresh(e.target);
            edited(e.target);
        }
        markDirty();
    });
    form.addEventListener('change', markDirty);
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    // Restore a field to its template default.
    form.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-reset]');
        if (!btn) return;
        var el = btn.closest('[data-field]').querySelector('.text-input');
        el.value = el.dataset.default;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.focus();
    });

    // FAQ rows: add, delete, reorder. Form indices only need to be unique; PHP keeps DOM order.
    var faq = form.querySelector('[data-faq]');
    if (faq) {
        var list = faq.querySelector('[data-faq-list]');
        var template = faq.querySelector('[data-faq-template]');
        var next = list.children.length;

        var renumber = function () {
            var rows = list.querySelectorAll('[data-faq-item]');
            rows.forEach(function (row, i) { row.querySelector('[data-faq-num]').textContent = 'Q' + (i + 1); });
            faq.querySelector('[data-faq-count]').textContent = rows.length + (rows.length === 1 ? ' question' : ' questions');
            faq.querySelector('[data-faq-empty]').hidden = rows.length > 0;
        };
        var changed = function (row) {
            renumber();
            var input = row && row.querySelector('.text-input');
            (input || form.querySelector('.text-input')).dispatchEvent(new Event('input', { bubbles: true }));
        };
        renumber();

        faq.querySelector('[data-faq-add]').addEventListener('click', function () {
            list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__i__/g, 'n' + next++));
            var row = list.lastElementChild;
            changed(row);
            row.querySelector('.faq-question').focus();
        });

        faq.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-faq-move], [data-faq-remove]');
            if (!btn) return;
            var row = btn.closest('[data-faq-item]');
            if (btn.hasAttribute('data-faq-remove')) {
                var filled = Array.prototype.some.call(row.querySelectorAll('.text-input'), function (el) { return el.value.trim(); });
                if (filled && !confirm('Delete this question?')) return;
                row.remove();
                changed(null);
                return;
            }
            var sibling = btn.dataset.faqMove === '-1' ? row.previousElementSibling : row.nextElementSibling;
            if (!sibling) return;
            list.insertBefore(row, btn.dataset.faqMove === '-1' ? sibling : sibling.nextElementSibling);
            changed(row);
            btn.focus();
        });
    }

    // Find a field by its text.
    var filter = document.querySelector('[data-field-filter]');
    var empty = document.querySelector('[data-filter-empty]');
    if (filter) {
        filter.addEventListener('input', function () {
            var q = filter.value.trim().toLowerCase(), any = false;
            form.querySelectorAll('.seo-card').forEach(function (card) {
                var shown = 0;
                card.querySelectorAll('[data-field]').forEach(function (f) {
                    var input = f.querySelector('.text-input');
                    var hit = !q || (f.textContent + ' ' + input.value).toLowerCase().indexOf(q) !== -1;
                    f.hidden = !hit;
                    if (hit) shown++;
                });
                card.hidden = shown === 0;
                if (shown) any = true;
            });
            if (empty) empty.hidden = any;
            form.querySelectorAll('[data-autosize]').forEach(autosize);
        });
    }
})();
