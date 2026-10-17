<?php
/**
 * Site search results (/search?q=...). See includes/search.php.
 */
$q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
$terms = search_terms($q);
$results = $q !== '' ? site_search($q, $routes) : [];
$suggestions = ['Book Cover Design' => '/services/book-cover-design', 'Amazon Ads' => '/services/amazon-ads', 'Book Formatting' => '/services/book-formatting', 'KDP Course Book' => '/amazon-kdp-course-book'];
?>
<main>
    <section class="pt-20 sm:pt-24 md:pt-32 pb-6 sm:pb-8 bg-gradient-to-b from-primary/10 to-background">
        <div class="container mx-auto px-4 sm:px-6 text-center">
            <p class="blog-eyebrow">Search</p>
            <h1 class="text-3xl sm:text-4xl lg:text-6xl font-bold text-foreground mb-4 sm:mb-6"><?= $q !== '' ? 'Results for “' . e($q) . '”' : 'Search Publishing Guru' ?></h1>
            <form class="blog-search" role="search" method="get" action="<?= url('/search') ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search services, pages and articles…" aria-label="Search the site"<?= $q === '' ? ' autofocus' : '' ?>>
                <button type="submit">Search</button>
            </form>
        </div>
    </section>

    <section class="blog-listing">
        <div class="container mx-auto px-4 sm:px-6 search-results">
<?php if ($q !== '' && $results): ?>
            <p class="blog-results" role="status"><?= count($results) ?> <?= count($results) === 1 ? 'result' : 'results' ?></p>
            <ol class="search-list">
<?php foreach ($results as $result): ?>
                <li class="search-item">
                    <span class="search-type"><?= e($result['type']) ?></span>
                    <h2><a href="<?= e($result['url']) ?>"><?= search_highlight($result['title'], $terms) ?></a></h2>
                    <p><?= search_highlight($result['snippet'], $terms) ?></p>
                </li>
<?php endforeach; ?>
            </ol>
<?php else: ?>
            <div class="blog-empty" role="status">
                <img src="<?= asset('assets/book-stack-v0McbyqJ.svg') ?>" width="494" height="494" alt="" loading="lazy" decoding="async">
<?php if ($q !== ''): ?>
                <h2>No results for “<?= e($q) ?>”</h2>
                <p>Try a different word, or <a href="<?= url('/contact') ?>">ask us directly</a>. We reply within 24 hours.</p>
<?php else: ?>
                <h2>What are you looking for?</h2>
<?php endif; ?>
                <p class="search-suggest">Popular: <?php foreach ($suggestions as $label => $href): ?><a href="<?= url($href) ?>"><?= e($label) ?></a><?php endforeach; ?></p>
            </div>
<?php endif; ?>
        </div>
    </section>
</main>
