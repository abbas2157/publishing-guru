<?php
/**
 * Blog admin helpers (/admin/blog). Storage and rendering live in includes/blog.php.
 */

const BLOG_STATUS_LABELS = ['published' => 'Published', 'scheduled' => 'Scheduled', 'draft' => 'Draft'];

function blog_status_badge(array $post): string
{
    $status = blog_status($post);
    return '<span class="badge badge-' . $status . '">' . BLOG_STATUS_LABELS[$status] . '</span>';
}

/** One-line text from a form field: whitespace collapsed, trimmed, at most $max characters. */
function blog_field(array $in, string $key, int $max = 300): string
{
    $value = trim((string) preg_replace('/\s+/u', ' ', (string) ($in[$key] ?? '')));
    return mb_substr($value, 0, $max);
}

/**
 * Build a post from the editor form. Returns null and sets $error when it can't be saved.
 * A new category typed into "new_category" is created on the fly.
 */
function blog_admin_build_post(array $in, ?array $existing, ?string &$error): ?array
{
    $title = blog_field($in, 'title', 200);
    if ($title === '') {
        $error = 'Add a title for the post.';
        return null;
    }
    $content = blog_clean_html((string) ($in['content'] ?? ''));
    $status = ($in['status'] ?? '') === 'published' ? 'published' : 'draft';
    if ($status === 'published' && cms_plain($content) === '') {
        $error = 'Add some content before publishing (or save as a draft).';
        return null;
    }

    $date = trim((string) ($in['published_at'] ?? ''));
    $ts = $date !== '' ? strtotime($date) : null;
    if ($ts === false) {
        $error = 'The publish date is not valid.';
        return null;
    }
    $publishedAt = $ts ? date('c', $ts) : ($existing['published_at'] ?? null);
    if ($status === 'published' && !$publishedAt) {
        $publishedAt = date('c');
    }

    $category = (string) ($in['category'] ?? '');
    if ($newCategory = blog_field($in, 'new_category', 60)) {
        $category = blog_add_category($newCategory);
    } elseif (!isset(blog_categories()[$category])) {
        $category = '';
    }

    $tags = [];
    foreach (explode(',', (string) ($in['tags'] ?? '')) as $tag) {
        $tag = mb_substr(trim(preg_replace('/\s+/u', ' ', ltrim($tag, " #"))), 0, 40);
        if ($tag !== '' && !in_array(mb_strtolower($tag), array_map('mb_strtolower', $tags), true)) {
            $tags[] = $tag;
        }
    }

    $image = trim((string) ($in['image'] ?? ''));
    if ($image !== '' && (!preg_match('~^' . preg_quote(BLOG_UPLOADS, '~') . '/[\w/.-]+$~', $image) || str_contains($image, '..') || !is_file(dirname(__DIR__, 2) . '/' . $image))) {
        $image = '';
    }

    // Renaming a live post's slug keeps the old URL redirecting (blog_store_post()).
    $slugInput = trim((string) ($in['slug'] ?? ''));
    $now = date('c');
    return [
        'id' => $existing['id'] ?? bin2hex(random_bytes(6)),
        'slug' => blog_unique_slug(blog_slugify($slugInput !== '' ? $slugInput : $title), $existing['id'] ?? null),
        'title' => $title,
        'excerpt' => blog_field($in, 'excerpt', 300),
        'content' => $content,
        'image' => $image,
        'image_alt' => $image ? blog_field($in, 'image_alt', 250) : '',
        'category' => $category,
        'tags' => array_slice($tags, 0, 15),
        'author' => blog_field($in, 'author', 80),
        'status' => $status,
        'published_at' => $publishedAt,
        'meta_title' => blog_field($in, 'meta_title', 200),
        'meta_description' => blog_field($in, 'meta_description', 400),
        'created_at' => $existing['created_at'] ?? $now,
        'updated_at' => $now,
    ];
}

/** Apply a POST from the categories page (add / update / delete). Returns a status message. */
function blog_admin_categories_action(array $in, ?string &$error): ?string
{
    $action = (string) ($in['action'] ?? '');
    $slug = (string) ($in['slug'] ?? '');
    $name = blog_field($in, 'name', 60);
    $description = blog_field($in, 'description', 300);

    if (in_array($action, ['add', 'update'], true) && $name === '') {
        $error = 'Give the category a name.';
        return null;
    }
    if ($action === 'add') {
        blog_add_category($name, $description);
        return 'Category “' . $name . '” added.';
    }
    if ($action === 'update') {
        blog_update_category($slug, $name, $description);
        return 'Category updated.';
    }
    if ($action === 'delete') {
        blog_delete_category($slug);
        return 'Category deleted. Its posts are now uncategorised.';
    }
    return null;
}

/** Value for a datetime-local input. */
function blog_input_datetime(?string $iso): string
{
    $ts = $iso ? strtotime($iso) : false;
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

/** JSON response for the upload endpoint. */
function blog_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
