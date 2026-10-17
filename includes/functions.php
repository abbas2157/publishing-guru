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

/** "Copy" button that copies $text to the clipboard (behaviour in assets/js/app.js). */
function copy_button(string $text, string $label): string
{
    return '<button type="button" class="copy-btn" data-copy="' . e($text) . '" aria-label="' . e($label) . '" title="' . e($label) . '">'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="14" height="14" x="8" y="8" rx="2"></rect><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path></svg>'
        . '<span>Copy</span></button>';
}

/** Give a page's <main> the "Skip to main content" target, wrapping the page in one if it has none. */
function main_landmark(string $html): string
{
    if (preg_match('/<main\b/i', $html)) {
        return preg_replace('/<main\b/i', '<main id="main-content" tabindex="-1"', $html, 1);
    }
    return '<main id="main-content" tabindex="-1">' . $html . '</main>';
}
