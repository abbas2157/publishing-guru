<?php
/**
 * Blog post card. Expects $post; optional $featured (large horizontal card) and $eager (no lazy-loading).
 */
$featured ??= false;
$eager ??= false;
$link = blog_url($post);
$category = blog_category_name($post['category'] ?? '');
[$imgW, $imgH] = blog_image_size($post['image'] ?? null);
?>
<article class="blog-card<?= $featured ? ' blog-card-featured' : '' ?>">
    <a href="<?= e($link) ?>" class="blog-card-media" tabindex="-1" aria-hidden="true">
<?php if (!empty($post['image'])): ?>
        <img src="<?= asset($post['image']) ?>" alt=""<?= $imgW ? ' width="' . $imgW . '" height="' . $imgH . '"' : '' ?><?= $eager ? ' fetchpriority="high"' : ' loading="lazy"' ?> decoding="async">
<?php else: ?>
        <span class="blog-card-placeholder"><img src="<?= asset('assets/petals-ByR-N01G.svg') ?>" width="81" height="84" alt="" loading="lazy" decoding="async"></span>
<?php endif; ?>
    </a>
    <div class="blog-card-body">
        <div class="blog-card-meta">
<?php if ($category): ?>
            <a class="blog-chip" href="<?= e(blog_category_url($post['category'])) ?>"><?= e($category) ?></a>
<?php endif; ?>
            <span><time datetime="<?= e(date('Y-m-d', strtotime($post['published_at']))) ?>"><?= e(blog_date($post['published_at'], 'M j, Y')) ?></time> · <?= blog_reading_time($post['content'] ?? '') ?> min read</span>
        </div>
        <<?= $featured ? 'h2' : 'h3' ?> class="blog-card-title"><a href="<?= e($link) ?>"><?= e($post['title']) ?></a></<?= $featured ? 'h2' : 'h3' ?>>
        <p class="blog-card-excerpt"><?= e(blog_excerpt($post, $featured ? 220 : 140)) ?></p>
        <a href="<?= e($link) ?>" class="blog-card-more" aria-label="Read more: <?= e($post['title']) ?>">Read article <img src="<?= asset('assets/Arrow-black-AVsc0Ug-.svg') ?>" width="37" height="10" alt="" loading="lazy" decoding="async"></a>
    </div>
</article>
