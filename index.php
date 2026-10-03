<?php
/**
 * Front controller: maps clean URLs (/contact, /services/amazon-ads, ...) to page templates.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/cms.php';
require __DIR__ . '/includes/blog.php';

if (NOINDEX) {
    header('X-Robots-Tag: noindex, nofollow');
}

$routes = require __DIR__ . '/includes/routes.php';

// Resolve the request path relative to the base path.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (BASE_PATH !== '' && strpos($path, BASE_PATH) === 0) {
    $path = substr($path, strlen(BASE_PATH));
}
if ($path === '' || $path === '/index.php') {
    $path = '/';
}
// Like React Router, treat "/contact/" the same as "/contact".
if ($path !== '/') {
    $path = rtrim($path, '/');
}

// Admin dashboard (/admin, /admin/login, /admin/queries, ...).
if ($path === '/admin' || strpos($path, '/admin/') === 0) {
    require __DIR__ . '/includes/admin/bootstrap.php';
    exit;
}

// Contact-form submissions (stored for /admin/queries).
if ($path === '/api/contact') {
    require __DIR__ . '/includes/contact.php';
    contact_handle();
}

// XML sitemap generated from the route table; lastmod follows the page template.
if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
    foreach ($routes as $routePath => $route) {
        $loc = SITE_URL . ($routePath === '/' ? '/' : $routePath);
        // Newer of the template and the last edit made in /admin/seo.
        $edited = strtotime(cms_data()['updated'][$routePath] ?? '') ?: 0;
        if ($routePath === '/blog' && ($latest = blog_posts())) {
            $edited = max($edited, strtotime($latest[0]['updated_at'] ?? $latest[0]['published_at']));
        }
        $lastmod = date('Y-m-d', max($edited, filemtime(__DIR__ . '/pages/' . $route['view'] . '.php')));
        $priority = $routePath === '/' ? '1.0' : (substr_count($routePath, '/') > 1 ? '0.7' : '0.8');
        echo "  <url><loc>", e($loc), "</loc><lastmod>$lastmod</lastmod><priority>$priority</priority></url>\n";
    }
    // Blog categories with live posts, then every live post.
    $posts = blog_posts();
    foreach (array_unique(array_filter(array_column($posts, 'category'))) as $slug) {
        if (isset(blog_categories()[$slug])) {
            echo "  <url><loc>", e(SITE_URL . '/blog/category/' . $slug), "</loc><priority>0.5</priority></url>\n";
        }
    }
    foreach ($posts as $post) {
        $lastmod = date('Y-m-d', strtotime($post['updated_at'] ?? $post['published_at']));
        echo "  <url><loc>", e(SITE_URL . '/blog/' . $post['slug']), "</loc><lastmod>$lastmod</lastmod><priority>0.6</priority></url>\n";
    }
    echo '</urlset>', "\n";
    exit;
}

if ($path === '/blog/feed.xml') {
    blog_feed();
    exit;
}

// Blog posts and categories are added to the route table so breadcrumbs work like any page.
if (!isset($routes[$path]) && ($blogRoute = blog_route($path))) {
    $routes[$path] = $blogRoute;
}

if (isset($routes[$path])) {
    $page = $routes[$path];
} else {
    http_response_code(404);
    $page = ['view' => '404'];
}

// Blog index/category: resolve ?q= and ?page= before the <head> is rendered.
if ($page['view'] === 'blog') {
    $blogListing = blog_listing($page);
    if ($blogListing) {
        $page = blog_listing_route($page, $blogListing);
    } else {
        http_response_code(404);
        $page = ['view' => '404'];
    }
}

require __DIR__ . '/includes/layout.php';
