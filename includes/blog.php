<?php
/**
 * Blog: storage, queries, content cleaning and image uploads.
 *
 * storage/blog.json:
 *   posts: [ { id, slug, title, excerpt, content (HTML), image (path under BLOG_UPLOADS), image_alt,
 *              category (slug), tags [..], author, status (draft|published), published_at,
 *              meta_title, meta_description, created_at, updated_at } ]
 *   categories: [ { slug, name, description } ]
 *
 * A post is live when it is published and its publish date has passed, so a future date schedules it.
 * Root-relative links and image URLs in post content are stored without BASE_PATH (see cms.php).
 */

const BLOG_RESERVED_SLUGS = ['category', 'feed', 'feed-xml', 'page', 'search', 'tag'];
const BLOG_IMAGE_MAX_WIDTH = 1600;
const BLOG_IMAGE_MAX_BYTES = 8 * 1024 * 1024;

// ---------------------------------------------------------------------------
// Storage
// ---------------------------------------------------------------------------

function blog_data(): array
{
    static $data = null;
    if ($data === null || !empty($GLOBALS['blog_reload'])) {
        unset($GLOBALS['blog_reload']);
        $json = is_file(BLOG_FILE) ? json_decode((string) file_get_contents(BLOG_FILE), true) : null;
        $data = (is_array($json) ? $json : []) + ['posts' => [], 'categories' => []];
    }
    return $data;
}

function blog_save(array $data): bool
{
    $dir = dirname(BLOG_FILE);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return false;
    }
    $data['posts'] = array_values($data['posts']);
    $data['categories'] = array_values($data['categories']);
    $tmp = BLOG_FILE . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    if (!rename($tmp, BLOG_FILE)) {
        @unlink($tmp);
        return false;
    }
    $GLOBALS['blog_reload'] = true;
    return true;
}

// ---------------------------------------------------------------------------
// Queries
// ---------------------------------------------------------------------------

function blog_is_live(array $post): bool
{
    return ($post['status'] ?? '') === 'published' && strtotime($post['published_at'] ?? '') <= time();
}

/** "published", "scheduled" or "draft". */
function blog_status(array $post): string
{
    if (($post['status'] ?? '') !== 'published') {
        return 'draft';
    }
    return blog_is_live($post) ? 'published' : 'scheduled';
}

/** Posts newest first; only live ones unless $all. */
function blog_posts(bool $all = false): array
{
    $posts = blog_data()['posts'];
    if (!$all) {
        $posts = array_filter($posts, 'blog_is_live');
    }
    usort($posts, fn($a, $b) => strcmp($b['published_at'] ?? $b['created_at'] ?? '', $a['published_at'] ?? $a['created_at'] ?? ''));
    return array_values($posts);
}

function blog_find_post(string $field, string $value): ?array
{
    foreach (blog_data()['posts'] as $post) {
        if (($post[$field] ?? null) === $value) {
            return $post;
        }
    }
    return null;
}

/** Categories keyed by slug. */
function blog_categories(): array
{
    $out = [];
    foreach (blog_data()['categories'] as $cat) {
        $out[$cat['slug']] = $cat;
    }
    return $out;
}

function blog_category_name(?string $slug): string
{
    return blog_categories()[$slug]['name'] ?? '';
}

/** Up to $limit live posts sharing the post's category (then tags), topped up with the latest. */
function blog_related(array $post, int $limit = 3): array
{
    $scored = [];
    foreach (blog_posts() as $i => $other) {
        if ($other['id'] === $post['id']) {
            continue;
        }
        $score = ($other['category'] && $other['category'] === $post['category'] ? 3 : 0)
            + count(array_intersect($other['tags'] ?? [], $post['tags'] ?? []));
        $scored[] = [$score, -$i, $other];
    }
    rsort($scored);
    return array_column(array_slice($scored, 0, $limit), 2);
}

// ---------------------------------------------------------------------------
// Formatting helpers
// ---------------------------------------------------------------------------

function blog_slugify(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (function_exists('iconv')) {
        $text = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    }
    $text = strtolower(str_replace(['&', '+'], [' and ', ' plus '], $text));
    $text = trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
    return substr($text, 0, 80) ?: 'post';
}

/** $slug made unique among posts (ignoring $exceptId) and not a reserved word. */
function blog_unique_slug(string $slug, ?string $exceptId = null): string
{
    $taken = [];
    foreach (blog_data()['posts'] as $post) {
        if ($post['id'] !== $exceptId) {
            $taken[$post['slug']] = true;
        }
    }
    $base = in_array($slug, BLOG_RESERVED_SLUGS, true) ? $slug . '-post' : $slug;
    $candidate = $base;
    for ($n = 2; isset($taken[$candidate]); $n++) {
        $candidate = $base . '-' . $n;
    }
    return $candidate;
}

function blog_url(array $post): string
{
    return url('/blog/' . $post['slug']);
}

function blog_category_url(string $slug): string
{
    return url('/blog/category/' . $slug);
}

/** Minutes to read at ~220 words per minute. */
function blog_reading_time(string $html): int
{
    return max(1, (int) round(blog_word_count($html) / 220));
}

function blog_word_count(string $html): int
{
    return str_word_count(strip_tags(str_replace('<', ' <', $html)));
}

function blog_excerpt(array $post, int $length = 160): string
{
    $text = trim($post['excerpt'] ?? '') ?: cms_plain($post['content'] ?? '');
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1), " ,.;:-") . '…' : $text;
}

function blog_date(?string $iso, string $format = 'F j, Y'): string
{
    $ts = strtotime((string) $iso);
    return $ts ? date($format, $ts) : '';
}

/** [width, height] of an uploaded image, or [null, null]. */
function blog_image_size(?string $path): array
{
    $file = $path ? dirname(__DIR__) . '/' . $path : '';
    $size = $file && is_file($file) ? @getimagesize($file) : false;
    return $size ? [$size[0], $size[1]] : [null, null];
}

// ---------------------------------------------------------------------------
// Post content HTML
// ---------------------------------------------------------------------------

/**
 * Clean editor HTML for storage: an allowlist of tags/attributes, safe URLs only, YouTube/Vimeo
 * embeds only, h1 demoted to h2, ids on h2/h3 for the table of contents, external links in a
 * new tab, lazy images with dimensions.
 */
function blog_clean_html(string $html): string
{
    $allowed = [
        'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'strong' => [], 'em' => [], 'u' => [], 's' => [],
        'sub' => [], 'sup' => [], 'a' => ['href', 'title'], 'ul' => [], 'ol' => ['start'], 'li' => [],
        'blockquote' => [], 'pre' => [], 'code' => [], 'hr' => [], 'img' => ['src', 'alt', 'title'],
        'figure' => [], 'figcaption' => [], 'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'], 'iframe' => ['src'],
    ];
    $rename = ['h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4', 'b' => 'strong', 'i' => 'em', 'strike' => 's', 'del' => 's'];
    $drop = ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'noscript',
        'template', 'svg', 'math', 'head', 'meta', 'link', 'title', 'canvas', 'video', 'audio', 'frame', 'frameset'];
    $alignClasses = ['ql-align-center', 'ql-align-right', 'ql-align-justify'];

    $html = cms_strip_base(str_replace(['&nbsp;', "\u{00A0}"], ' ', $html));
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="blog-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('blog-root');
    if (!$root) {
        return '';
    }

    $siteHost = (string) parse_url(SITE_URL, PHP_URL_HOST);
    $ids = [];
    $clean = function (DOMNode $node) use (&$clean, $doc, $allowed, $rename, $drop, $alignClasses, $siteHost, &$ids) {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, $drop, true)) {
                $node->removeChild($child);
                continue;
            }
            if (isset($rename[$tag])) {
                $new = $doc->createElement($rename[$tag]);
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $new->setAttribute($attr->name, $attr->value);
                }
                while ($child->firstChild) {
                    $new->appendChild($child->firstChild);
                }
                $node->replaceChild($new, $child);
                $child = $new;
                $tag = $rename[$tag];
            }
            if (!isset($allowed[$tag])) {
                // Unknown wrapper (div, span, font, ...): keep its contents.
                $clean($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $classes = array_intersect(preg_split('/\s+/', $child->getAttribute('class')), $alignClasses);
            foreach (iterator_to_array($child->attributes) as $attr) {
                if (!in_array($attr->name, $allowed[$tag], true)) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($classes && in_array($tag, ['p', 'h2', 'h3', 'h4', 'li', 'blockquote'], true)) {
                $child->setAttribute('class', implode(' ', $classes));
            }

            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if ($href === '' || !preg_match('~^(https?://|mailto:|tel:|/|#)~i', $href)) {
                    $child->removeAttribute('href');
                } elseif (preg_match('~^https?://~i', $href) && strcasecmp((string) parse_url($href, PHP_URL_HOST), $siteHost) !== 0) {
                    $child->setAttribute('target', '_blank');
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
            } elseif ($tag === 'img') {
                $src = trim($child->getAttribute('src'));
                if (!preg_match('~^(https?://|/)~i', $src)) {
                    $node->removeChild($child);
                    continue;
                }
                $child->setAttribute('alt', trim($child->getAttribute('alt')));
                if (str_starts_with($src, '/' . BLOG_UPLOADS . '/')) {
                    [$w, $h] = blog_image_size(ltrim($src, '/'));
                    if ($w) {
                        $child->setAttribute('width', (string) $w);
                        $child->setAttribute('height', (string) $h);
                    }
                }
                $child->setAttribute('loading', 'lazy');
                $child->setAttribute('decoding', 'async');
            } elseif ($tag === 'iframe') {
                $src = trim($child->getAttribute('src'));
                if (!preg_match('~^https://(www\.)?(youtube\.com/embed/|youtube-nocookie\.com/embed/|player\.vimeo\.com/video/)[\w\-/?=&;%.]+$~i', $src)) {
                    $node->removeChild($child);
                    continue;
                }
                while ($child->firstChild) {
                    $child->removeChild($child->firstChild);
                }
                foreach (['class' => 'ql-video', 'title' => 'Embedded video', 'loading' => 'lazy', 'allowfullscreen' => 'allowfullscreen', 'frameborder' => '0'] as $k => $v) {
                    $child->setAttribute($k, $v);
                }
                continue;
            } elseif ($tag === 'h2' || $tag === 'h3') {
                $base = blog_slugify($child->textContent);
                $id = $base;
                for ($n = 2; isset($ids[$id]); $n++) {
                    $id = $base . '-' . $n;
                }
                $ids[$id] = true;
                $child->setAttribute('id', $id);
            }
            $clean($child);
        }
    };
    $clean($root);

    // Drop empty paragraphs the editor leaves behind (but keep intentional line breaks inside text).
    foreach (iterator_to_array($root->getElementsByTagName('p')) as $p) {
        if (trim(str_replace("\u{00A0}", '', $p->textContent)) === '' && !$p->getElementsByTagName('img')->length) {
            $p->parentNode->removeChild($p);
        }
    }

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

/** Stored post HTML → output: root-relative links and images get BASE_PATH back. */
function blog_content_html(string $html): string
{
    if (BASE_PATH === '') {
        return $html;
    }
    return preg_replace('~(\s(?:href|src)=["\'])(?=/(?!/))~i', '$1' . str_replace(['\\', '$'], ['\\\\', '\$'], BASE_PATH), $html);
}

/** Table of contents from the h2/h3 ids added by blog_clean_html(): [[level, id, text], ...]. */
function blog_toc(string $html): array
{
    preg_match_all('~<h([23])\b[^>]*\sid="([^"]+)"[^>]*>(.*?)</h\1>~is', $html, $m, PREG_SET_ORDER);
    return array_map(fn($h) => [(int) $h[1], $h[2], cms_plain($h[3])], $m);
}

// ---------------------------------------------------------------------------
// Uploads
// ---------------------------------------------------------------------------

/**
 * Store an uploaded image under BLOG_UPLOADS/YYYY/MM. JPEG/PNG/WebP are resized to at most
 * BLOG_IMAGE_MAX_WIDTH and saved as WebP; GIFs are kept as-is (animation). Returns the path
 * relative to the site root, or null with $error set.
 */
function blog_store_upload(array $file, ?string &$error): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        $error = ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'The image is too large.' : 'The upload failed. Please try again.';
        return null;
    }
    if ($file['size'] > BLOG_IMAGE_MAX_BYTES) {
        $error = 'Images must be ' . (BLOG_IMAGE_MAX_BYTES / 1048576) . ' MB or smaller.';
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    $type = $info[2] ?? null;
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        $error = 'Please upload a JPG, PNG, WebP or GIF image.';
        return null;
    }

    $root = dirname(__DIR__);
    $dir = BLOG_UPLOADS . '/' . date('Y/m');
    if (!is_dir("$root/$dir") && !mkdir("$root/$dir", 0775, true)) {
        $error = 'Could not create the uploads folder. Check that ' . BLOG_UPLOADS . ' is writable.';
        return null;
    }
    blog_protect_uploads($root . '/' . explode('/', BLOG_UPLOADS)[0]);
    $name = blog_slugify(pathinfo((string) ($file['name'] ?? 'image'), PATHINFO_FILENAME));
    $name = substr($name === 'post' ? 'image' : $name, 0, 60) . '-' . bin2hex(random_bytes(3));

    if ($type === IMAGETYPE_GIF || !function_exists('imagewebp')) {
        $ext = image_type_to_extension($type, false);
        $path = "$dir/$name." . ($ext === 'jpeg' ? 'jpg' : $ext);
        if (!move_uploaded_file($file['tmp_name'], "$root/$path")) {
            $error = 'Could not save the image.';
            return null;
        }
        return $path;
    }

    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
    };
    if (!$src) {
        $error = 'That image could not be read.';
        return null;
    }
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $orientation = (@exif_read_data($file['tmp_name'])['Orientation'] ?? 1);
        $rotated = match ($orientation) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => null };
        if ($rotated) {
            imagedestroy($src);
            $src = $rotated;
        }
    }
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w > BLOG_IMAGE_MAX_WIDTH) {
        $nh = (int) round($h * BLOG_IMAGE_MAX_WIDTH / $w);
        $dst = imagecreatetruecolor(BLOG_IMAGE_MAX_WIDTH, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, BLOG_IMAGE_MAX_WIDTH, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    } else {
        imagepalettetotruecolor($src);
        imagealphablending($src, false);
        imagesavealpha($src, true);
    }
    $path = "$dir/$name.webp";
    $ok = imagewebp($src, "$root/$path", 82);
    imagedestroy($src);
    if (!$ok) {
        $error = 'Could not save the image.';
        return null;
    }
    return $path;
}

/** Make sure nothing in the uploads folder can run as a script. */
function blog_protect_uploads(string $dir): void
{
    $file = $dir . '/.htaccess';
    if (!is_file($file)) {
        file_put_contents($file, "# Uploaded images only: never run or render scripts from here.\nOptions -Indexes -ExecCGI\n<FilesMatch \"\\.(?i:php[0-9]*|phtml|phar|pl|py|cgi|sh|html?|svg|js)$\">\n    Require all denied\n</FilesMatch>\n");
    }
}

// ---------------------------------------------------------------------------
// Routing
// ---------------------------------------------------------------------------

/**
 * Route definition (see routes.php) for /blog/category/<slug> or /blog/<post-slug>, or null.
 * The /blog index itself is a normal route in routes.php.
 */
function blog_route(string $path): ?array
{
    if (!preg_match('~^/blog/(?:category/([a-z0-9-]+)|([a-z0-9-]+))$~', $path, $m)) {
        return null;
    }
    if (($m[1] ?? '') !== '') {
        $cat = blog_categories()[$m[1]] ?? null;
        if (!$cat) {
            return null;
        }
        return [
            'view' => 'blog',
            'name' => $cat['name'],
            'parent' => '/blog',
            'title' => $cat['name'] . ' Articles | ' . SITE_NAME . ' Blog',
            'description' => ($cat['description'] ?? '') ?: 'Amazon KDP publishing articles about ' . $cat['name'] . ' from ' . SITE_NAME . '.',
            'blog_category' => $cat['slug'],
            'cms' => false,
        ];
    }
    $post = blog_find_post('slug', $m[2]);
    if (!$post) {
        // A post whose slug was changed: send old links to the new URL.
        foreach (blog_data()['posts'] as $candidate) {
            if (in_array($m[2], $candidate['old_slugs'] ?? [], true) && blog_is_live($candidate)) {
                header('Location: ' . blog_url($candidate), true, 301);
                exit;
            }
        }
    }
    if (!$post || !blog_is_live($post)) {
        return null;
    }
    return blog_post_route($post);
}

/**
 * Listing state for /blog and category pages from the query string (?q=, ?page=):
 * q, category, base (path), total, pages, current, posts (this page only). Null for an
 * out-of-range page number.
 */
function blog_listing(array $page): ?array
{
    $q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
    $category = $page['blog_category'] ?? null;
    $posts = blog_posts();
    if ($category) {
        $posts = array_filter($posts, fn($p) => ($p['category'] ?? '') === $category);
    }
    if ($q !== '') {
        $posts = array_filter($posts, function ($p) use ($q) {
            $haystack = $p['title'] . ' ' . ($p['excerpt'] ?? '') . ' ' . implode(' ', $p['tags'] ?? []) . ' ' . strip_tags($p['content'] ?? '');
            return mb_stripos($haystack, $q) !== false;
        });
    }
    $posts = array_values($posts);
    $pages = max(1, (int) ceil(count($posts) / BLOG_PER_PAGE));
    $current = max(1, (int) ($_GET['page'] ?? 1));
    if ($current > $pages) {
        return null;
    }
    return [
        'q' => $q,
        'category' => $category,
        'base' => $category ? '/blog/category/' . $category : '/blog',
        'total' => count($posts),
        'pages' => $pages,
        'current' => $current,
        'posts' => array_slice($posts, ($current - 1) * BLOG_PER_PAGE, BLOG_PER_PAGE),
    ];
}

/** Route tweaks for a listing page: page number in title/canonical, search results not indexed. */
function blog_listing_route(array $page, array $listing): array
{
    if ($listing['current'] > 1) {
        $page['title'] = preg_replace('/ \| /', ' – Page ' . $listing['current'] . ' | ', $page['title'], 1);
        $page['canonical'] = SITE_URL . $listing['base'] . '?page=' . $listing['current'];
    }
    if ($listing['q'] !== '') {
        $page['robots'] = 'noindex, follow';
        $page['canonical'] = SITE_URL . $listing['base'];
    }
    return $page;
}

/** Route definition for a single post (also used by the admin preview). */
function blog_post_route(array $post): array
{
    $route = [
        'view' => 'blog-post',
        'name' => $post['title'],
        'parent' => '/blog',
        'title' => ($post['meta_title'] ?? '') ?: $post['title'] . ' | ' . SITE_NAME,
        'description' => ($post['meta_description'] ?? '') ?: blog_excerpt($post, 160),
        'og_type' => 'article',
        'article' => [
            'published_time' => date('c', strtotime($post['published_at'])),
            'modified_time' => date('c', strtotime($post['updated_at'] ?? $post['published_at'])),
            'section' => blog_category_name($post['category'] ?? ''),
            'tag' => $post['tags'] ?? [],
        ],
        'schema' => ['article-schema' => blog_post_schema($post)],
        'cms' => false,
        'post' => $post,
    ];
    if (!empty($post['image'])) {
        $route['og_image'] = $post['image'];
        $route['og_image_alt'] = ($post['image_alt'] ?? '') ?: $post['title'];
        $route['preload'] = $post['image'];
    }
    return $route;
}

// ---------------------------------------------------------------------------
// Structured data, feed
// ---------------------------------------------------------------------------

function blog_post_schema(array $post): array
{
    $url = SITE_URL . '/blog/' . $post['slug'];
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        '@id' => $url . '#article',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        'url' => $url,
        'headline' => mb_substr($post['title'], 0, 110),
        'description' => ($post['meta_description'] ?? '') ?: blog_excerpt($post, 300),
        'datePublished' => date('c', strtotime($post['published_at'])),
        'dateModified' => date('c', strtotime($post['updated_at'] ?? $post['published_at'])),
        'author' => ['@type' => 'Organization', 'name' => ($post['author'] ?? '') ?: BLOG_DEFAULT_AUTHOR, 'url' => SITE_URL . '/'],
        'publisher' => ['@id' => SITE_URL . '/#organization', '@type' => 'Organization', 'name' => SITE_NAME, 'logo' => ['@type' => 'ImageObject', 'url' => SITE_URL . '/assets/publishing-guru-logo.png']],
        'inLanguage' => 'en-US',
        'wordCount' => blog_word_count($post['content'] ?? ''),
    ];
    if (!empty($post['image'])) {
        $schema['image'] = SITE_URL . '/' . $post['image'];
    }
    if ($section = blog_category_name($post['category'] ?? '')) {
        $schema['articleSection'] = $section;
    }
    if (!empty($post['tags'])) {
        $schema['keywords'] = implode(', ', $post['tags']);
    }
    return $schema;
}

function blog_feed(): void
{
    header('Content-Type: application/rss+xml; charset=UTF-8');
    $posts = array_slice(blog_posts(), 0, 20);
    $x = fn($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
    echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>', "\n";
    echo '<title>', $x(SITE_NAME . ' Blog'), '</title><link>', $x(SITE_URL . '/blog'), '</link>';
    echo '<atom:link href="', $x(SITE_URL . '/blog/feed.xml'), '" rel="self" type="application/rss+xml"/>';
    echo '<description>', $x('Amazon KDP publishing tips, guides and case studies from ' . SITE_NAME . '.'), '</description><language>en-us</language>';
    if ($posts) {
        echo '<lastBuildDate>', date(DATE_RSS, strtotime($posts[0]['updated_at'] ?? $posts[0]['published_at'])), '</lastBuildDate>';
    }
    echo "\n";
    foreach ($posts as $post) {
        $link = SITE_URL . '/blog/' . $post['slug'];
        echo '<item><title>', $x($post['title']), '</title><link>', $x($link), '</link><guid isPermaLink="true">', $x($link), '</guid>';
        echo '<pubDate>', date(DATE_RSS, strtotime($post['published_at'])), '</pubDate>';
        if ($cat = blog_category_name($post['category'] ?? '')) {
            echo '<category>', $x($cat), '</category>';
        }
        echo '<description>', $x(blog_excerpt($post, 300)), '</description></item>', "\n";
    }
    echo '</channel></rss>', "\n";
}
