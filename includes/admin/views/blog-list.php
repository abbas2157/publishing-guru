<?php
/** Blog posts list with search and filters. */
$all = blog_posts(true);
$categories = blog_categories();
$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$category = (string) ($_GET['category'] ?? '');

$rows = array_values(array_filter($all, function ($p) use ($search, $status, $category) {
    if ($status !== '' && blog_status($p) !== $status) return false;
    if ($category !== '' && ($p['category'] ?? '') !== $category) return false;
    if ($search !== '' && mb_stripos($p['title'] . ' ' . ($p['excerpt'] ?? '') . ' ' . implode(' ', $p['tags'] ?? []), $search) === false) return false;
    return true;
}));
$counts = array_count_values(array_map('blog_status', $all));
$filtered = $search !== '' || $status !== '' || $category !== '';
?>
<?php if (isset($_GET['deleted'])): ?>
<p class="notice notice-ok" role="status"><?= admin_icon('check') ?> Post deleted.</p>
<?php endif; ?>

<div class="stats stats-3">
    <div class="card stat">
        <span class="stat-icon"><?= admin_icon('pen') ?></span>
        <div><p class="stat-label">Published</p><p class="stat-value"><?= $counts['published'] ?? 0 ?></p><p class="stat-hint">Live on <a class="link" href="<?= url('/blog') ?>" target="_blank" rel="noopener">/blog</a></p></div>
    </div>
    <div class="card stat">
        <span class="stat-icon"><?= admin_icon('calendar') ?></span>
        <div><p class="stat-label">Scheduled</p><p class="stat-value"><?= $counts['scheduled'] ?? 0 ?></p><p class="stat-hint">Go live automatically on their date</p></div>
    </div>
    <div class="card stat">
        <span class="stat-icon"><?= admin_icon('file-text') ?></span>
        <div><p class="stat-label">Drafts</p><p class="stat-value"><?= $counts['draft'] ?? 0 ?></p><p class="stat-hint"><a class="link" href="<?= url('/admin/blog/categories') ?>"><?= count($categories) ?> <?= count($categories) === 1 ? 'category' : 'categories' ?></a></p></div>
    </div>
</div>

<div class="card">
    <form class="filters" method="get" action="<?= url('/admin/blog') ?>">
        <label class="input-wrap grow"><?= admin_icon('search') ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search title, excerpt or tags" aria-label="Search posts"></label>
        <select name="status" aria-label="Status">
            <option value="">All statuses</option>
<?php foreach (BLOG_STATUS_LABELS as $value => $label): ?>
            <option value="<?= $value ?>"<?= $status === $value ? ' selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
        </select>
<?php if ($categories): ?>
        <select name="category" aria-label="Category">
            <option value="">All categories</option>
<?php foreach ($categories as $slug => $cat): ?>
            <option value="<?= e($slug) ?>"<?= $category === $slug ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
<?php endforeach; ?>
        </select>
<?php endif; ?>
        <button type="submit" class="btn btn-ghost">Filter</button>
<?php if ($filtered): ?>
        <a href="<?= url('/admin/blog') ?>" class="btn btn-ghost">Clear</a>
<?php endif; ?>
        <a href="<?= url('/admin/blog/new') ?>" class="btn btn-primary filters-end"><?= admin_icon('plus') ?> New post</a>
    </form>

<?php if ($rows): ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Post</th><th class="hide-sm">Category</th><th>Status</th><th class="hide-md">Date</th><th></th></tr>
            </thead>
            <tbody>
<?php foreach ($rows as $post): $state = blog_status($post); ?>
                <tr>
                    <td>
                        <a href="<?= url('/admin/blog/edit/' . $post['id']) ?>" class="post-cell">
                            <span class="post-thumb"><?php if (!empty($post['image'])): ?><img src="<?= asset($post['image']) ?>" alt="" loading="lazy"><?php else: ?><?= admin_icon('image') ?><?php endif; ?></span>
                            <span class="page-cell"><strong><?= e($post['title']) ?></strong><small>/blog/<?= e($post['slug']) ?></small></span>
                        </a>
                    </td>
                    <td class="hide-sm"><?= ($name = blog_category_name($post['category'] ?? '')) ? '<span class="chip">' . e($name) . '</span>' : '<span class="muted">—</span>' ?></td>
                    <td><?= blog_status_badge($post) ?></td>
                    <td class="hide-md nowrap muted"><?= $post['published_at'] ? e(blog_date($post['published_at'], $state === 'scheduled' ? 'M j, Y · g:i A' : 'M j, Y')) : 'Edited ' . e(time_ago($post['updated_at'])) ?></td>
                    <td class="nowrap actions-cell">
<?php if ($state === 'published'): ?>
                        <a href="<?= e(blog_url($post)) ?>" class="btn btn-ghost btn-sm" target="_blank" rel="noopener">View</a>
<?php else: ?>
                        <a href="<?= url('/admin/blog/preview/' . $post['id']) ?>" class="btn btn-ghost btn-sm" target="_blank" rel="noopener">Preview</a>
<?php endif; ?>
                        <a href="<?= url('/admin/blog/edit/' . $post['id']) ?>" class="btn btn-ghost btn-sm">Edit</a>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="empty">
        <img src="<?= asset('assets/book-stack-v0McbyqJ.svg') ?>" alt="" class="empty-art">
<?php if ($filtered): ?>
        <h3>No matching posts</h3>
        <p class="muted">Try a different search or clear the filters.</p>
<?php else: ?>
        <h3>Write your first post</h3>
        <p class="muted">Share KDP tips and guides. Posts are optimised for search automatically.</p>
        <a href="<?= url('/admin/blog/new') ?>" class="btn btn-primary"><?= admin_icon('plus') ?> New post</a>
<?php endif; ?>
    </div>
<?php endif; ?>
</div>
