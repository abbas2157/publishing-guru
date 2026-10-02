<?php
/** Blog categories: add, rename, describe, delete. Expects $error, $message. */
$categories = blog_categories();
$counts = blog_category_counts(true);
?>
<a href="<?= url('/admin/blog') ?>" class="back-link"><?= admin_icon('arrow-left') ?> All posts</a>

<?php if ($message): ?>
<p class="notice notice-ok" role="status"><?= admin_icon('check') ?> <?= e($message) ?></p>
<?php endif; ?>
<?php if ($error): ?>
<p class="alert" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<div class="grid-2 categories-layout">
    <div class="card">
        <div class="card-head"><h3>Categories</h3><span class="muted"><?= count($categories) ?> total</span></div>
<?php if ($categories): ?>
        <div class="category-list">
<?php foreach ($categories as $slug => $cat): ?>
            <form method="post" class="category-row">
                <?= csrf_field() ?>
                <input type="hidden" name="slug" value="<?= e($slug) ?>">
                <div class="category-fields">
                    <input class="text-input" name="name" value="<?= e($cat['name']) ?>" maxlength="60" aria-label="Name" required>
                    <input class="text-input" name="description" value="<?= e($cat['description'] ?? '') ?>" maxlength="300" placeholder="Short description (shown on the category page and used as its meta description)" aria-label="Description">
                    <small class="muted"><a class="link" href="<?= e(blog_category_url($slug)) ?>" target="_blank" rel="noopener">/blog/category/<?= e($slug) ?></a> · <?= $counts[$slug] ?? 0 ?> <?= ($counts[$slug] ?? 0) === 1 ? 'post' : 'posts' ?></small>
                </div>
                <div class="category-actions">
                    <button type="submit" name="action" value="update" class="btn btn-ghost btn-sm">Save</button>
                    <button type="submit" name="action" value="delete" class="icon-btn icon-btn-sm icon-btn-danger" title="Delete category" aria-label="Delete category" data-confirm="Delete “<?= e($cat['name']) ?>”? Its posts will become uncategorised."><?= admin_icon('trash') ?></button>
                </div>
            </form>
<?php endforeach; ?>
        </div>
<?php else: ?>
        <div class="empty"><h3>No categories yet</h3><p class="muted">Group posts by topic, e.g. “KDP Basics”, “Amazon Ads” or “Book Covers”.</p></div>
<?php endif; ?>
    </div>

    <form method="post" class="card">
        <?= csrf_field() ?>
        <div class="card-head"><h3>Add a category</h3></div>
        <div class="seo-card-body side-fields">
            <div class="seo-field"><label for="cat-name">Name</label><input class="text-input" id="cat-name" name="name" maxlength="60" required placeholder="e.g. Amazon Ads"></div>
            <div class="seo-field"><label for="cat-desc">Description <span class="muted">(optional)</span></label><textarea class="text-input" id="cat-desc" name="description" rows="3" maxlength="300"></textarea></div>
            <button type="submit" name="action" value="add" class="btn btn-primary"><?= admin_icon('plus') ?> Add category</button>
        </div>
    </form>
</div>
