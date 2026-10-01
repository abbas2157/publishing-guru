<?php
/**
 * Front controller: maps clean URLs (/contact, /services/amazon-ads, ...) to page templates.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

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

// XML sitemap generated from the route table; lastmod follows the page template.
if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
    foreach ($routes as $routePath => $route) {
        $loc = SITE_URL . ($routePath === '/' ? '/' : $routePath);
        $lastmod = date('Y-m-d', filemtime(__DIR__ . '/pages/' . $route['view'] . '.php'));
        $priority = $routePath === '/' ? '1.0' : (substr_count($routePath, '/') > 1 ? '0.7' : '0.8');
        echo "  <url><loc>", e($loc), "</loc><lastmod>$lastmod</lastmod><priority>$priority</priority></url>\n";
    }
    echo '</urlset>', "\n";
    exit;
}

if (isset($routes[$path])) {
    $page = $routes[$path];
} else {
    http_response_code(404);
    $page = ['view' => '404'];
}

require __DIR__ . '/includes/layout.php';
