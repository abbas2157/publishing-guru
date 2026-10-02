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
function blog_admin_build_post(array $in, ?array $existing, array &$data, ?string &$error): ?array
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
        $category = blog_admin_add_category($data, $newCategory, '');
    } elseif (!in_array($category, array_column($data['categories'], 'slug'), true)) {
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

    $slugInput = trim((string) ($in['slug'] ?? ''));
    $slug = blog_unique_slug(blog_slugify($slugInput !== '' ? $slugInput : $title), $existing['id'] ?? null);
    // Remember URLs this post was live under so they keep redirecting (see blog_route()).
    $oldSlugs = $existing['old_slugs'] ?? [];
    if ($existing && $existing['slug'] !== $slug && blog_is_live($existing)) {
        $oldSlugs[] = $existing['slug'];
    }
    $oldSlugs = array_values(array_diff(array_unique($oldSlugs), [$slug]));
    $now = date('c');
    return [
        'id' => $existing['id'] ?? bin2hex(random_bytes(6)),
        'slug' => $slug,
        'old_slugs' => $oldSlugs,
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

/** Add a category (or reuse one with the same name) and return its slug. */
function blog_admin_add_category(array &$data, string $name, string $description): string
{
    foreach ($data['categories'] as $cat) {
        if (mb_strtolower($cat['name']) === mb_strtolower($name)) {
            return $cat['slug'];
        }
    }
    $taken = array_flip(array_column($data['categories'], 'slug'));
    $base = blog_slugify($name);
    $slug = $base;
    for ($n = 2; isset($taken[$slug]); $n++) {
        $slug = $base . '-' . $n;
    }
    $data['categories'][] = ['slug' => $slug, 'name' => $name, 'description' => $description];
    return $slug;
}

/** Apply a POST from the categories page (add / update / delete). Returns a status message. */
function blog_admin_categories_action(array $in, ?string &$error): ?string
{
    $data = blog_data();
    $action = (string) ($in['action'] ?? '');
    $slug = (string) ($in['slug'] ?? '');
    $name = blog_field($in, 'name', 60);
    $description = blog_field($in, 'description', 300);

    if ($action === 'add') {
        if ($name === '') {
            $error = 'Give the category a name.';
            return null;
        }
        blog_admin_add_category($data, $name, $description);
        $message = 'Category “' . $name . '” added.';
    } elseif ($action === 'update') {
        if ($name === '') {
            $error = 'A category needs a name.';
            return null;
        }
        foreach ($data['categories'] as &$cat) {
            if ($cat['slug'] === $slug) {
                $cat['name'] = $name;
                $cat['description'] = $description;
            }
        }
        unset($cat);
        $message = 'Category updated.';
    } elseif ($action === 'delete') {
        $data['categories'] = array_values(array_filter($data['categories'], fn($c) => $c['slug'] !== $slug));
        foreach ($data['posts'] as &$post) {
            if (($post['category'] ?? '') === $slug) {
                $post['category'] = '';
            }
        }
        unset($post);
        $message = 'Category deleted. Its posts are now uncategorised.';
    } else {
        return null;
    }
    if (!blog_save($data)) {
        $error = 'Could not write ' . basename(BLOG_FILE) . '. Check that the storage/ folder is writable.';
        return null;
    }
    return $message;
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
