<?php
/** Create / edit a blog post. Expects $editing (post or null), $posted (form after a failed save), $error. */
$v = $posted ?? $editing ?? [];
$val = fn(string $key, string $default = '') => is_string($v[$key] ?? null) ? $v[$key] : $default;
$tags = is_array($v['tags'] ?? null) ? implode(', ', $v['tags']) : $val('tags');
$content = $posted !== null ? blog_clean_html($val('content')) : ($editing['content'] ?? '');
$publishedAt = $posted !== null ? $val('published_at') : blog_input_datetime($editing['published_at'] ?? null);
$status = $val('status', 'draft');
$image = $val('image');
$categories = blog_categories();
$titleSuffix = ' | ' . SITE_NAME;
$state = $editing ? blog_status($editing) : null;
?>
<a href="<?= url('/admin/blog') ?>" class="back-link"><?= admin_icon('arrow-left') ?> All posts</a>

<?php if (isset($_GET['saved']) && !$error): ?>
<p class="notice notice-ok" role="status"><?= admin_icon('check') ?>
    <?= $state === 'published' ? 'Saved. The post is live.' : ($state === 'scheduled' ? 'Saved. The post will go live on ' . e(blog_date($editing['published_at'], 'M j, Y \a\t g:i A')) . '.' : 'Draft saved.') ?>
<?php if ($state === 'published'): ?>
    <a class="link" href="<?= e(blog_url($editing)) ?>" target="_blank" rel="noopener">View post <?= admin_icon('external', 'icon icon-sm') ?></a>
<?php endif; ?>
</p>
<?php endif; ?>
<?php if ($error): ?>
<p class="alert" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" class="post-editor" data-dirty-form data-blog-form data-upload-url="<?= url('/admin/blog/upload') ?>">
    <?= csrf_field() ?>
    <div class="post-main">
        <section class="card seo-card">
            <div class="seo-card-body post-fields">
                <input class="text-input post-title-input" name="title" value="<?= e($val('title')) ?>" placeholder="Post title" aria-label="Post title" maxlength="200" required data-post-title>
                <div class="slug-row">
                    <span class="muted"><?= e(preg_replace('~^https?://~', '', SITE_URL)) ?>/blog/</span>
                    <input class="slug-input" name="slug" value="<?= e($val('slug')) ?>" placeholder="post-url" aria-label="URL slug" maxlength="80" data-post-slug<?= $editing ? '' : ' data-auto="1"' ?>>
                </div>
                <div class="editor-wrap">
                    <div id="blog-editor" data-blog-editor><?= blog_content_html($content) ?></div>
                    <textarea name="content" hidden data-blog-content><?= e(blog_content_html($content)) ?></textarea>
                </div>
                <p class="muted editor-hint">Use Heading 2 for main sections and Heading 3 for sub-sections; they build the table of contents. Click an image in the text to edit its alt text.</p>
            </div>
        </section>

        <section class="card seo-card">
            <div class="card-head"><h3><?= admin_icon('type') ?> Excerpt</h3><span class="counter" data-counter-for="post-excerpt" data-min="50" data-max="300"></span></div>
            <div class="seo-card-body">
                <textarea class="text-input" id="post-excerpt" name="excerpt" rows="3" maxlength="300" data-autosize data-post-excerpt placeholder="A one or two sentence summary shown on the blog page, under the title and in search results."><?= e($val('excerpt')) ?></textarea>
            </div>
        </section>

        <section class="card seo-card">
            <div class="card-head"><h3><?= admin_icon('search') ?> Search appearance</h3></div>
            <div class="seo-card-body seo-meta">
                <div class="seo-meta-fields">
                    <div class="seo-field" data-field>
                        <div class="field-top"><label for="meta-title">Meta title</label><span class="counter" data-counter-for="meta-title" data-min="<?= SEO_TITLE_RANGE[0] ?>" data-max="<?= SEO_TITLE_RANGE[1] ?>"></span></div>
                        <input class="text-input" id="meta-title" name="meta_title" value="<?= e($val('meta_title')) ?>" placeholder="Defaults to the post title" maxlength="200" data-serp="title" data-default="<?= e($val('title') ? $val('title') . $titleSuffix : '') ?>" data-suffix="<?= e($titleSuffix) ?>">
                    </div>
                    <div class="seo-field" data-field>
                        <div class="field-top"><label for="meta-description">Meta description</label><span class="counter" data-counter-for="meta-description" data-min="<?= SEO_DESCRIPTION_RANGE[0] ?>" data-max="<?= SEO_DESCRIPTION_RANGE[1] ?>"></span></div>
                        <textarea class="text-input" id="meta-description" name="meta_description" rows="3" maxlength="400" data-autosize data-serp="description" data-default="<?= e($val('excerpt')) ?>" placeholder="Defaults to the excerpt"><?= e($val('meta_description')) ?></textarea>
                    </div>
                </div>
                <div class="serp" aria-label="Google search preview">
                    <p class="serp-label">Google preview</p>
                    <div class="serp-site">
                        <span class="serp-favicon"><img src="<?= asset('assets/favicon-32x32-DOg4SlHu.png') ?>" alt="" width="18" height="18"></span>
                        <span><strong><?= e(SITE_NAME) ?></strong><small><?= e(seo_breadcrumb_url('/blog')) ?> › <span data-serp-slug><?= e($val('slug') ?: 'post-url') ?></span></small></span>
                    </div>
                    <p class="serp-title" data-serp-out="title"></p>
                    <p class="serp-desc" data-serp-out="description"></p>
                </div>
            </div>
        </section>
    </div>

    <aside class="post-side">
        <section class="card seo-card">
            <div class="card-head"><h3>Publish</h3><?= $editing ? blog_status_badge($editing) : '' ?></div>
            <div class="seo-card-body side-fields">
                <div class="segmented" role="radiogroup" aria-label="Status">
                    <label><input type="radio" name="status" value="draft"<?= $status !== 'published' ? ' checked' : '' ?>><span>Draft</span></label>
                    <label><input type="radio" name="status" value="published"<?= $status === 'published' ? ' checked' : '' ?>><span>Published</span></label>
                </div>
                <div class="seo-field">
                    <label for="post-date">Publish date</label>
                    <input class="text-input" type="datetime-local" id="post-date" name="published_at" value="<?= e($publishedAt) ?>">
                    <small class="muted">Empty = now. A future date schedules the post.</small>
                </div>
                <div class="side-actions">
                    <button type="submit" class="btn btn-primary btn-block"><?= admin_icon('check') ?> Save post</button>
<?php if ($editing): ?>
                    <a class="btn btn-ghost btn-block" href="<?= url('/admin/blog/preview/' . $editing['id']) ?>" target="_blank" rel="noopener"><?= admin_icon('eye') ?> Preview</a>
<?php endif; ?>
                </div>
                <span class="save-state muted" data-dirty-label><?= $editing ? 'Last saved ' . e(time_ago($editing['updated_at'])) : 'Not saved yet' ?></span>
            </div>
        </section>

        <section class="card seo-card">
            <div class="card-head"><h3><?= admin_icon('image') ?> Featured image</h3></div>
            <div class="seo-card-body side-fields" data-featured>
                <input type="hidden" name="image" value="<?= e($image) ?>" data-featured-path>
                <div class="featured-preview<?= $image ? '' : ' is-empty' ?>" data-featured-preview>
                    <img src="<?= $image ? asset($image) : '' ?>" alt="" data-featured-img<?= $image ? '' : ' hidden' ?>>
                    <label class="featured-drop">
                        <?= admin_icon('upload') ?>
                        <span data-featured-label><?= $image ? 'Replace image' : 'Upload image' ?></span>
                        <small class="muted">JPG, PNG, WebP or GIF · resized to 1600px and converted to WebP</small>
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-featured-input>
                    </label>
                </div>
                <button type="button" class="reset-btn featured-remove" data-featured-remove<?= $image ? '' : ' hidden' ?>><?= admin_icon('trash', 'icon icon-sm') ?> Remove image</button>
                <div class="seo-field">
                    <label for="image-alt">Alt text</label>
                    <input class="text-input" id="image-alt" name="image_alt" value="<?= e($val('image_alt')) ?>" maxlength="250" placeholder="Describe the image (defaults to the title)">
                </div>
            </div>
        </section>

        <section class="card seo-card">
            <div class="card-head"><h3><?= admin_icon('folder') ?> Organise</h3></div>
            <div class="seo-card-body side-fields">
                <div class="seo-field">
                    <div class="field-top"><label for="post-category">Category</label><a class="link small-link" href="<?= url('/admin/blog/categories') ?>">Manage</a></div>
                    <select id="post-category" name="category" data-category-select>
                        <option value="">Uncategorised</option>
<?php foreach ($categories as $slug => $cat): ?>
                        <option value="<?= e($slug) ?>"<?= $val('category') === $slug ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
<?php endforeach; ?>
                        <option value="__new"<?= $val('new_category') !== '' ? ' selected' : '' ?>>+ New category…</option>
                    </select>
                    <input class="text-input" name="new_category" value="<?= e($val('new_category')) ?>" placeholder="New category name" maxlength="60" data-new-category<?= $val('new_category') !== '' ? '' : ' hidden' ?>>
                </div>
                <div class="seo-field">
                    <label for="post-tags">Tags</label>
                    <input class="text-input" id="post-tags" name="tags" value="<?= e($tags) ?>" placeholder="kdp, low content, keywords">
                    <small class="muted">Separate with commas.</small>
                </div>
                <div class="seo-field">
                    <label for="post-author">Author</label>
                    <input class="text-input" id="post-author" name="author" value="<?= e($val('author')) ?>" placeholder="<?= e(BLOG_DEFAULT_AUTHOR) ?>" maxlength="80">
                </div>
            </div>
        </section>

<?php if ($editing): ?>
        <button type="submit" form="delete-post" class="btn btn-ghost btn-block btn-danger" data-confirm="Delete “<?= e($editing['title']) ?>”? This can't be undone."><?= admin_icon('trash') ?> Delete post</button>
<?php endif; ?>
    </aside>
</form>
<?php if ($editing): ?>
<form method="post" id="delete-post" action="<?= url('/admin/blog/delete/' . $editing['id']) ?>" hidden><?= csrf_field() ?></form>
<?php endif; ?>
