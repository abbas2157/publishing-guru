<?php
/**
 * Blog index (/blog) and category pages (/blog/category/<slug>).
 * $blogListing is prepared in index.php; computed here when rendered elsewhere (admin content editor).
 */
$listing = $blogListing ?? blog_listing($page ?? []) ?? blog_listing([]);
$categories = blog_categories();
$counts = blog_category_counts();
$activeCategory = $listing['category'] ? $categories[$listing['category']] : null;
$cards = $listing['posts'];
$featured = !$activeCategory && $listing['q'] === '' && $listing['current'] === 1 && $cards ? array_shift($cards) : null;
$pageUrl = function (int $n) use ($listing): string {
    $query = http_build_query(array_filter(['q' => $listing['q'], 'page' => $n > 1 ? $n : null]));
    return url($listing['base']) . ($query ? '?' . $query : '');
};
?>
<main>
    <section class="pt-20 sm:pt-24 md:pt-32 pb-6 sm:pb-8 bg-gradient-to-b from-primary/10 to-background">
        <div class="container mx-auto px-4 sm:px-6 text-center">
<?php if ($activeCategory): ?>
            <p class="blog-eyebrow"><a href="<?= url('/blog') ?>">Blog</a> <span aria-hidden="true">/</span> Category</p>
            <h1 class="text-3xl sm:text-4xl lg:text-6xl font-bold text-foreground mb-4 sm:mb-6"><?= e($activeCategory['name']) ?></h1>
<?php if (!empty($activeCategory['description'])): ?>
            <p class="text-base sm:text-lg text-foreground max-w-2xl mx-auto px-2 sm:px-0"><?= e($activeCategory['description']) ?></p>
<?php endif; ?>
<?php else: ?>
            <p class="blog-eyebrow">Publishing Guru Blog</p>
            <h1 class="text-3xl sm:text-4xl lg:text-6xl font-bold text-foreground mb-4 sm:mb-6">Amazon KDP Publishing Tips &amp; Guides</h1>
            <p class="text-base sm:text-lg text-foreground max-w-2xl mx-auto px-2 sm:px-0">Practical, no-fluff advice on niches, keywords, formatting, covers, A+ content and Amazon ads, from the team that has launched 100+ KDP books.</p>
<?php endif; ?>
            <?= CMS_SKIP_OPEN ?>
            <form class="blog-search" role="search" method="get" action="<?= url($listing['base']) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input type="search" name="q" value="<?= e($listing['q']) ?>" placeholder="<?= $activeCategory ? 'Search ' . e($activeCategory['name']) . '…' : 'Search articles…' ?>" aria-label="Search articles">
                <button type="submit">Search</button>
            </form>
            <?= CMS_SKIP_CLOSE ?>
        </div>
    </section>

    <?= CMS_SKIP_OPEN ?>
    <section class="blog-listing">
        <div class="container mx-auto px-4 sm:px-6">
<?php if ($counts): ?>
            <nav class="blog-cats" aria-label="Blog categories">
                <a href="<?= url('/blog') ?>" class="blog-cat<?= $activeCategory ? '' : ' is-active' ?>"<?= $activeCategory ? '' : ' aria-current="page"' ?>>All articles</a>
<?php foreach ($categories as $slug => $cat): if (empty($counts[$slug])) continue; $active = $listing['category'] === $slug; ?>
                <a href="<?= e(blog_category_url($slug)) ?>" class="blog-cat<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($cat['name']) ?> <span><?= $counts[$slug] ?></span></a>
<?php endforeach; ?>
            </nav>
<?php endif; ?>

<?php if ($listing['q'] !== ''): ?>
            <p class="blog-results"><?= $listing['total'] ?> <?= $listing['total'] === 1 ? 'article' : 'articles' ?> for “<?= e($listing['q']) ?>” · <a href="<?= url($listing['base']) ?>">Clear search</a></p>
<?php endif; ?>

<?php if ($featured): ?>
            <?php partial('blog-card', ['post' => $featured, 'featured' => true, 'eager' => true]); ?>
<?php endif; ?>

<?php if ($cards): ?>
            <div class="blog-grid">
<?php foreach ($cards as $post): ?>
                <?php partial('blog-card', ['post' => $post]); ?>
<?php endforeach; ?>
            </div>
<?php elseif (!$featured): ?>
            <div class="blog-empty">
                <img src="<?= asset('assets/book-stack-v0McbyqJ.svg') ?>" width="494" height="494" alt="" loading="lazy" decoding="async">
<?php if ($listing['q'] !== ''): ?>
                <h2>No articles match your search</h2>
                <p>Try a different word, or <a href="<?= url($listing['base']) ?>">browse all articles</a>.</p>
<?php else: ?>
                <h2>New articles are on the way</h2>
                <p>We're writing practical guides on publishing with Amazon KDP. In the meantime, <a href="<?= url('/book-call') ?>">book a free strategy call</a>.</p>
<?php endif; ?>
            </div>
<?php endif; ?>

<?php if ($listing['pages'] > 1): ?>
            <nav class="blog-pager" aria-label="Pagination">
<?php if ($listing['current'] > 1): ?>
                <a href="<?= e($pageUrl($listing['current'] - 1)) ?>" rel="prev">← Newer</a>
<?php endif; ?>
<?php for ($n = 1; $n <= $listing['pages']; $n++): ?>
                <a href="<?= e($pageUrl($n)) ?>"<?= $n === $listing['current'] ? ' class="is-active" aria-current="page"' : '' ?>><?= $n ?></a>
<?php endfor; ?>
<?php if ($listing['current'] < $listing['pages']): ?>
                <a href="<?= e($pageUrl($listing['current'] + 1)) ?>" rel="next">Older →</a>
<?php endif; ?>
            </nav>
<?php endif; ?>
        </div>
    </section>
    <?= CMS_SKIP_CLOSE ?>
    <?php partial('sections/launch-journey'); ?>
</main>
