<?php
/**
 * Page shell. Expects $page (route definition) and $path (current route).
 */
$isNotFound = $page['view'] === '404';
// Meta edited in /admin/seo wins over the route table.
$meta = $isNotFound ? [] : cms_page_meta($path);
$title = ($meta['title'] ?? '') ?: ($page['title'] ?? DEFAULT_TITLE);
$description = ($meta['description'] ?? '') ?: ($page['description'] ?? DEFAULT_DESCRIPTION);
$canonical = $page['canonical'] ?? SITE_URL . ($path === '/' ? '/' : $path);
$ogImage = SITE_URL . '/' . ($page['og_image'] ?? DEFAULT_OG_IMAGE);
$fontsUrl = 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Open+Sans:wght@400;500;600;700&display=swap';

// Breadcrumb trail (Home > [parent] > page) for JSON-LD on inner pages.
$schemas = $page['schema'] ?? [];
if (isset($meta['schema'])) {
    $schemas = [];
    foreach ($meta['schema'] as $i => $schema) {
        $schemas['schema-' . ($i + 1)] = $schema;
    }
}
// FAQs edited in /admin/seo get FAQPage markup, unless the page schema already has one.
$faq = $isNotFound ? [] : cms_page_faq($path);
if ($faq && !in_array('FAQPage', array_column($schemas, '@type'), true)) {
    $schemas['faq-schema'] = cms_faq_schema($faq);
}
if (!$isNotFound && $path !== '/') {
    $trail = [];
    for ($p = $path; $p !== '/' && isset($routes[$p]); $p = $routes[$p]['parent'] ?? '/') {
        array_unshift($trail, $p);
    }
    array_unshift($trail, '/');
    $schemas['breadcrumb-schema'] = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => array_map(fn($p, $i) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $routes[$p]['name'],
            'item' => SITE_URL . ($p === '/' ? '/' : $p),
        ], $trail, array_keys($trail)),
    ];
}
$appConfig = [
    'basePath' => BASE_PATH,
    'track' => $page['track'] ?? null,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>" />
    <meta name="author" content="Publishing Guru" />
<?php if ($isNotFound): ?>
    <meta name="robots" content="<?= NOINDEX ? 'noindex, nofollow' : 'noindex, follow' ?>" />
<?php else: ?>
    <meta name="robots" content="<?= NOINDEX ? 'noindex, nofollow' : e($page['robots'] ?? 'index, follow, max-image-preview:large') ?>" />
    <link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
    <meta name="theme-color" content="#f2eee8" />

    <meta property="og:site_name" content="<?= e(SITE_NAME) ?>" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="<?= e($page['og_type'] ?? 'website') ?>" />
    <meta property="og:title" content="<?= e($title) ?>" />
    <meta property="og:description" content="<?= e($description) ?>" />
<?php if (!$isNotFound): ?>
    <meta property="og:url" content="<?= e($canonical) ?>" />
<?php endif; ?>
<?php foreach ($page['article'] ?? [] as $property => $values): ?>
<?php foreach ((array) $values as $value): if ($value === '') continue; ?>
    <meta property="article:<?= e($property) ?>" content="<?= e($value) ?>" />
<?php endforeach; ?>
<?php endforeach; ?>
    <meta property="og:image" content="<?= e($ogImage) ?>" />
    <meta property="og:image:alt" content="<?= e($page['og_image_alt'] ?? SITE_NAME) ?>" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= e($title) ?>" />
    <meta name="twitter:description" content="<?= e($description) ?>" />
    <meta name="twitter:image" content="<?= e($ogImage) ?>" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Fonts load without blocking first paint (display=swap). -->
    <link rel="preload" as="style" href="<?= e($fontsUrl) ?>">
    <link rel="stylesheet" href="<?= e($fontsUrl) ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?= e($fontsUrl) ?>"></noscript>
<?php if (!empty($page['preload'])): ?>
    <link rel="preload" as="image" href="<?= asset($page['preload']) ?>" fetchpriority="high">
<?php endif; ?>

    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('assets/apple-touch-icon-B6wJdpC3.png') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('assets/favicon-32x32-DOg4SlHu.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset('assets/favicon-16x16-CMm4sjXd.png') ?>">

    <!-- Facebook Pixel Code -->
    <script>
      !function(f,b,e,v,n,t,s)
      {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};
      if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
      n.queue=[];t=b.createElement(e);t.async=!0;
      t.src=v;s=b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t,s)}(window, document,'script',
      'https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', '<?= FB_PIXEL_ID ?>');
      fbq('track', 'PageView');
    </script>
    <!-- End Facebook Pixel Code -->

    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/sonner.css') ?>">
<?php if (in_array($page['view'], ['blog', 'blog-post'], true)): ?>
    <link rel="alternate" type="application/rss+xml" title="<?= e(SITE_NAME) ?> Blog" href="<?= url('/blog/feed.xml') ?>">
<?php endif; ?>
<?php foreach ($schemas as $id => $schema): ?>
    <script type="application/ld+json" id="<?= e($id) ?>"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endforeach; ?>
<?php if ($page['view'] === 'book-call'): ?>
    <script src="https://assets.calendly.com/assets/external/widget.js" async></script>
<?php endif; ?>
</head>

<body>
    <noscript>
        <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?= FB_PIXEL_ID ?>&ev=PageView&noscript=1"/>
    </noscript>
    <div id="root">
        <?php partial('toasters'); ?>
<?php if ($isNotFound): ?>
        <?php include __DIR__ . '/../pages/404.php'; ?>
<?php else: ?>
        <div class="<?= e($page['wrapper'] ?? 'min-h-screen') ?>">
            <?php cms_render('global:header', fn() => partial('header')); ?>
            <?php ob_start(); include __DIR__ . '/../pages/' . $page['view'] . '.php'; $html = ob_get_clean(); echo ($page['cms'] ?? true) ? cms_apply($path, $html) : str_replace([CMS_SKIP_OPEN, CMS_SKIP_CLOSE], '', $html); ?>
<?php if ($faq): ?>
            <?php include __DIR__ . '/faq.php'; ?>
<?php endif; ?>
            <?php cms_render('global:footer', fn() => partial('footer')); ?>
        </div>
<?php endif; ?>
    </div>

    <script>window.APP = <?= json_encode($appConfig, JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="<?= asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
