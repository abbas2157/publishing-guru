<?php
/**
 * Single blog post (/blog/<slug>). Expects $page['post'] (see blog_post_route()).
 */
$post = $page['post'];
$content = blog_content_html($post['content'] ?? '');
$toc = blog_toc($post['content'] ?? '');
$category = blog_category_name($post['category'] ?? '');
$author = ($post['author'] ?? '') ?: BLOG_DEFAULT_AUTHOR;
[$imgW, $imgH] = blog_image_size($post['image'] ?? null);
$published = strtotime($post['published_at']);
$updated = strtotime($post['updated_at'] ?? $post['published_at']);
$shareUrl = SITE_URL . '/blog/' . $post['slug'];
$share = [
    'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareUrl),
    'X' => 'https://twitter.com/intent/tweet?url=' . rawurlencode($shareUrl) . '&text=' . rawurlencode($post['title']),
    'LinkedIn' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($shareUrl),
    'WhatsApp' => 'https://wa.me/?text=' . rawurlencode($post['title'] . ' ' . $shareUrl),
];
$related = blog_related($post);
$tocList = function (string $class) use ($toc): void { ?>
                    <ol class="<?= $class ?>">
<?php foreach ($toc as [$level, $id, $text]): ?>
                        <li class="toc-h<?= $level ?>"><a href="#<?= e($id) ?>"><?= e($text) ?></a></li>
<?php endforeach; ?>
                    </ol>
<?php };
?>
<main>
    <article class="blog-article">
        <header class="blog-hero pt-20 sm:pt-24 md:pt-32 bg-gradient-to-b from-primary/10 to-background">
            <div class="container mx-auto px-4 sm:px-6">
                <nav class="blog-crumbs" aria-label="Breadcrumb">
                    <a href="<?= url('/') ?>">Home</a><span aria-hidden="true">›</span><a href="<?= url('/blog') ?>">Blog</a>
<?php if ($category): ?>
                    <span aria-hidden="true">›</span><a href="<?= e(blog_category_url($post['category'])) ?>"><?= e($category) ?></a>
<?php endif; ?>
                </nav>
                <h1 class="blog-title"><?= e($post['title']) ?></h1>
<?php if (!empty($post['excerpt'])): ?>
                <p class="blog-lead"><?= e($post['excerpt']) ?></p>
<?php endif; ?>
                <div class="blog-byline">
                    <span class="blog-avatar" aria-hidden="true"><img src="<?= asset('assets/apple-touch-icon-B6wJdpC3.png') ?>" width="180" height="180" alt=""></span>
                    <span>
                        <strong><?= e($author) ?></strong>
                        <span class="blog-byline-meta">
                            <time datetime="<?= e(date('c', $published)) ?>"><?= e(date('F j, Y', $published)) ?></time>
                            · <?= blog_reading_time($post['content'] ?? '') ?> min read
<?php if ($updated - $published > 86400): ?>
                            · Updated <time datetime="<?= e(date('c', $updated)) ?>"><?= e(date('M j, Y', $updated)) ?></time>
<?php endif; ?>
                        </span>
                    </span>
                </div>
            </div>
        </header>

<?php if (!empty($post['image'])): ?>
        <figure class="blog-cover container mx-auto px-4 sm:px-6">
            <img src="<?= asset($post['image']) ?>" alt="<?= e(($post['image_alt'] ?? '') ?: $post['title']) ?>"<?= $imgW ? ' width="' . $imgW . '" height="' . $imgH . '"' : '' ?> fetchpriority="high" decoding="async">
        </figure>
<?php endif; ?>

        <div class="blog-layout container mx-auto px-4 sm:px-6">
            <div class="blog-main">
<?php if (count($toc) >= 2): ?>
                <details class="blog-toc blog-toc-mobile">
                    <summary>In this article</summary>
                    <?php $tocList('blog-toc-list'); ?>
                </details>
<?php endif; ?>
                <div class="blog-content">
                    <?= $content ?>
                </div>

<?php if (!empty($post['tags'])): ?>
                <ul class="blog-tags" aria-label="Tags">
<?php foreach ($post['tags'] as $tag): ?>
                    <li><a href="<?= url('/blog') ?>?q=<?= rawurlencode($tag) ?>">#<?= e($tag) ?></a></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>

                <div class="blog-share">
                    <span>Share this article</span>
<?php foreach ($share as $network => $href): ?>
                    <a href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer"><?= e($network) ?></a>
<?php endforeach; ?>
                    <button type="button" data-copy-link="<?= e($shareUrl) ?>">Copy link</button>
                </div>
            </div>

            <aside class="blog-aside">
<?php if (count($toc) >= 2): ?>
                <nav class="blog-toc" aria-label="Table of contents">
                    <p class="blog-aside-title">In this article</p>
                    <?php $tocList('blog-toc-list'); ?>
                </nav>
<?php endif; ?>
                <div class="blog-cta">
                    <img src="<?= asset('assets/hero-hand-writing-BtVw-6Hy.svg') ?>" width="489" height="378" alt="" loading="lazy" decoding="async">
                    <p class="blog-cta-title">Ready to publish on Amazon KDP?</p>
                    <p>Get a free strategy call with our publishing team. No upfront payments or generic packages.</p>
                    <a href="<?= url('/book-call') ?>" class="blog-cta-btn">Book a free call <img src="<?= asset('assets/Arrow-dH2l6ufH.svg') ?>" width="37" height="9" alt="" loading="lazy" decoding="async"></a>
                </div>
            </aside>
        </div>
    </article>

<?php if ($related): ?>
    <section class="blog-related" aria-labelledby="related-title">
        <div class="container mx-auto px-4 sm:px-6">
            <h2 id="related-title" class="blog-related-title">Keep reading</h2>
            <div class="blog-grid">
<?php foreach ($related as $item): ?>
                <?php partial('blog-card', ['post' => $item]); ?>
<?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
    <?php partial('sections/launch-journey'); ?>
</main>
