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
$ogImageFile = $page['og_image'] ?? DEFAULT_OG_IMAGE;
$ogImage = SITE_URL . '/' . $ogImageFile;
// Declared size/type lets Facebook and WhatsApp render the preview on the very first share.
$ogImageInfo = @getimagesize(__DIR__ . '/../' . $ogImageFile) ?: null;

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
    // Fallback contact options shown when a form can't be sent.
    'whatsapp' => WHATSAPP_NUMBER,
    'email' => CONTACT_EMAIL,
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
<?php if (GOOGLE_SITE_VERIFICATION !== ''): ?>
    <meta name="google-site-verification" content="<?= e(GOOGLE_SITE_VERIFICATION) ?>" />
<?php endif; ?>

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
<?php if ($ogImageInfo): ?>
    <meta property="og:image:width" content="<?= $ogImageInfo[0] ?>" />
    <meta property="og:image:height" content="<?= $ogImageInfo[1] ?>" />
    <meta property="og:image:type" content="<?= e($ogImageInfo['mime']) ?>" />
<?php endif; ?>
    <meta property="og:image:alt" content="<?= e($page['og_image_alt'] ?? SITE_NAME) ?>" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= e($title) ?>" />
    <meta name="twitter:description" content="<?= e($description) ?>" />
    <meta name="twitter:image" content="<?= e($ogImage) ?>" />

    <!-- Self-hosted variable fonts (latin + latin-ext); no third-party round trips before text renders. -->
    <link rel="preload" as="font" type="font/woff2" href="<?= asset('assets/fonts/playfair-display-latin.woff2') ?>" crossorigin>
    <style>
<?php foreach (['Playfair Display' => 'playfair-display', 'Open Sans' => 'open-sans'] as $family => $file): ?>
    @font-face{font-family:'<?= $family ?>';font-style:normal;font-weight:400 700;font-display:swap;src:url(<?= asset("assets/fonts/$file-latin-ext.woff2") ?>) format('woff2');unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C4,U+2113,U+2C60-2C7F,U+A720-A7FF}
    @font-face{font-family:'<?= $family ?>';font-style:normal;font-weight:400 700;font-display:swap;src:url(<?= asset("assets/fonts/$file-latin.woff2") ?>) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
<?php endforeach; ?>
    </style>
<?php
// 'preload' is a file path, or a list of ['href' => ..., 'media' => ..., 'srcset' => ..., 'sizes' => ...].
$preloads = $page['preload'] ?? [];
foreach (is_string($preloads) ? [['href' => $preloads]] : $preloads as $preload):
?>
    <link rel="preload" as="image" href="<?= asset($preload['href']) ?>"<?php if (!empty($preload['srcset'])): ?> imagesrcset="<?= e($preload['srcset']) ?>" imagesizes="<?= e($preload['sizes'] ?? '100vw') ?>"<?php endif; ?><?php if (!empty($preload['media'])): ?> media="<?= e($preload['media']) ?>"<?php endif; ?> fetchpriority="high">
<?php endforeach; ?>

    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('assets/apple-touch-icon-B6wJdpC3.png') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('assets/favicon-32x32-DOg4SlHu.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset('assets/favicon-16x16-CMm4sjXd.png') ?>">

    <!-- Facebook Pixel Code. fbq() calls are queued; fbevents.js (and its cookies) only loads once the
         visitor accepts the cookie banner (assets/js/app.js), and after the page so it never slows it. -->
    <script>
      !function(f,b,e,v,n,t,s)
      {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};
      if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
      n.queue=[];f.loadPixel=function(){if(t)return;t=b.createElement(e);t.async=!0;
      t.src=v;s=b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t,s)};
      try{if(localStorage.getItem('pg-cookie-consent')==='granted')f.addEventListener('load',f.loadPixel)}catch(x){}
      }(window, document,'script',
      'https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', '<?= FB_PIXEL_ID ?>');
      fbq('track', 'PageView');
    </script>
    <!-- End Facebook Pixel Code -->

    <!-- Google Analytics 4 + Microsoft Clarity. gtag()/clarity() calls are queued; their scripts (and cookies)
         only load once the visitor accepts the cookie banner, the same way as the Pixel above. -->
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?= GA_MEASUREMENT_ID ?>');
      (function(c,l,a,r,i,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        var loaded=false;
        c.loadAnalytics=function(){if(loaded)return;loaded=true;
          y=l.getElementsByTagName(r)[0];
          ["https://www.googletagmanager.com/gtag/js?id=<?= GA_MEASUREMENT_ID ?>","https://www.clarity.ms/tag/"+i].forEach(function(src){
            var t=l.createElement(r);t.async=1;t.src=src;y.parentNode.insertBefore(t,y);
          });
        };
        try{if(localStorage.getItem('pg-cookie-consent')==='granted')c.addEventListener('load',c.loadAnalytics)}catch(x){}
      })(window, document, "clarity", "script", "<?= CLARITY_PROJECT_ID ?>");
    </script>

    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/sonner.css') ?>">
<?php if (in_array($page['view'], ['blog', 'blog-post'], true)): ?>
    <link rel="alternate" type="application/rss+xml" title="<?= e(SITE_NAME) ?> Blog" href="<?= url('/blog/feed.xml') ?>">
<?php endif; ?>
<?php foreach ($schemas as $id => $schema): ?>
    <script type="application/ld+json" id="<?= e($id) ?>"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endforeach; ?>
<?php if ($page['view'] === 'book-call'): ?>
    <!-- defer (not async): runs after pages/book-call.php has added the visit's UTM tags to the booking URL. -->
    <script src="https://assets.calendly.com/assets/external/widget.js" defer></script>
<?php endif; ?>
</head>

<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>
<?php if (!empty($page['scroll_progress'])): ?>
    <div class="scroll-progress" aria-hidden="true" data-scroll-progress><span></span></div>
<?php endif; ?>
    <div id="root">
        <?php partial('toasters'); ?>
<?php if ($isNotFound): ?>
        <?php ob_start(); include __DIR__ . '/../pages/404.php'; echo main_landmark(ob_get_clean()); ?>
<?php else: ?>
        <div class="<?= e($page['wrapper'] ?? 'min-h-screen') ?>">
            <?php cms_render('global:header', fn() => partial('header')); ?>
            <?php ob_start(); include __DIR__ . '/../pages/' . $page['view'] . '.php'; $html = ob_get_clean(); echo main_landmark(($page['cms'] ?? true) ? cms_apply($path, $html) : str_replace([CMS_SKIP_OPEN, CMS_SKIP_CLOSE], '', $html)); ?>
<?php if ($faq): ?>
            <?php include __DIR__ . '/faq.php'; ?>
<?php endif; ?>
            <?php cms_render('global:footer', fn() => partial('footer')); ?>
        </div>
<?php endif; ?>
    </div>

    <div class="cookie-banner" role="region" aria-label="Cookie consent" hidden data-cookie-banner>
        <p>We use cookies to measure our ads and improve your experience. You can accept or decline.</p>
        <div class="cookie-banner-actions">
            <button type="button" class="cookie-btn cookie-btn-secondary" data-cookie-choice="denied">Decline</button>
            <button type="button" class="cookie-btn cookie-btn-primary" data-cookie-choice="granted">Accept</button>
        </div>
    </div>

    <script>window.APP =<?= json_encode($appConfig, JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="<?= asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
