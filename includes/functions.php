<?php
/**
 * Template helpers.
 */

/** Escape a value for HTML output. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Site-relative URL for a route, e.g. url('/contact'). */
function url(string $path = '/'): string
{
    return BASE_PATH . ($path === '' ? '/' : $path);
}

/** URL for a static file, e.g. asset('assets/Arrow-dH2l6ufH.svg'). */
function asset(string $file): string
{
    $file = ltrim($file, '/');
    // Un-hashed CSS/JS get a version query so they can be cached long-term (see .htaccess).
    if (preg_match('/\.(css|js)$/', $file) && is_file($full = __DIR__ . '/../' . $file)) {
        $file .= '?v=' . filemtime($full);
    }
    return BASE_PATH . '/' . $file;
}

/**
 * Render a template from includes/, e.g. partial('sections/pricing').
 * Shared sections get their own content scope ("section:pricing", see cms.php), so an edit
 * made in /admin/seo applies on every page that uses the section.
 */
function partial(string $name, array $vars = []): void
{
    $file = __DIR__ . '/' . $name . '.php';
    if (strncmp($name, 'sections/', 9) !== 0) {
        extract($vars);
        include $file;
        return;
    }
    echo CMS_SKIP_OPEN;
    cms_render('section:' . substr($name, 9), function () use ($file, $vars) {
        extract($vars);
        include $file;
    });
    echo CMS_SKIP_CLOSE;
}

/** Inline SVG icons that the original build embeds as data: URIs. */
function icon(string $name): string
{
    static $icons = null;
    $icons ??= require __DIR__ . '/icons.php';
    return $icons[$name];
}
