<?php
/**
 * Admin area front controller. Included from index.php for every /admin* request.
 * Expects $path (the request path relative to BASE_PATH).
 */
require __DIR__ . '/functions.php';
require __DIR__ . '/seo.php';
require __DIR__ . '/blog.php';

admin_session_start();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$adminPath = substr($path, strlen('/admin')) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- Public routes -----------------------------------------------------------
if ($adminPath === '/login') {
    if (admin_user()) {
        redirect('/admin');
    }
    $error = null;
    $username = '';
    if ($method === 'POST') {
        $username = trim((string) ($_POST['username'] ?? ''));
        if (!csrf_valid($_POST['csrf'] ?? '')) {
            $error = 'Your session expired. Please try again.';
        } elseif (admin_attempt_login($username, (string) ($_POST['password'] ?? ''))) {
            redirect('/admin');
        } else {
            $error = 'Incorrect username or password.';
        }
    }
    include __DIR__ . '/views/login.php';
    return;
}

// --- Everything below requires a signed-in admin -----------------------------
if (!admin_user()) {
    redirect('/admin/login');
}

if ($adminPath === '/logout' && $method === 'POST') {
    if (csrf_valid($_POST['csrf'] ?? '')) {
        admin_logout();
    }
    redirect('/admin/login');
}

// --- Blog endpoints that don't render the admin shell -------------------------
if ($adminPath === '/blog/upload' && $method === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? '')) {
        blog_json(['error' => 'Your session expired. Reload the page and try again.'], 403);
    }
    $uploaded = blog_store_upload($_FILES['image'] ?? [], $uploadError);
    if (!$uploaded) {
        blog_json(['error' => $uploadError], 422);
    }
    blog_json(['path' => $uploaded, 'url' => asset($uploaded)]);
}
if (preg_match('#^/blog/preview/([a-f0-9]+)$#', $adminPath, $m) && ($previewPost = blog_find_post('id', $m[1]))) {
    // Render the post exactly as the public page would, but never indexable.
    $previewPost['published_at'] ??= date('c');
    $path = '/blog/' . $previewPost['slug'];
    $page = ['robots' => 'noindex, nofollow'] + blog_post_route($previewPost);
    $routes[$path] = $page;
    ob_start();
    require dirname(__DIR__) . '/layout.php';
    $banner = '<div style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:9999;padding:10px 18px;border-radius:999px;background:#222;color:#f2eee8;font:600 14px/1.4 \'Open Sans\',sans-serif;box-shadow:0 10px 30px rgb(0 0 0/.3)">Preview · '
        . e(BLOG_STATUS_LABELS[blog_status($previewPost)]) . ' · <a href="' . e(url('/admin/blog/edit/' . $previewPost['id'])) . '" style="color:#f0c93e;text-decoration:underline">Back to editor</a></div>';
    echo preg_replace('~</body>~', $banner . '</body>', ob_get_clean(), 1);
    return;
}
if (preg_match('#^/blog/delete/([a-f0-9]+)$#', $adminPath, $m) && $method === 'POST') {
    if (csrf_valid($_POST['csrf'] ?? '')) {
        $data = blog_data();
        $data['posts'] = array_values(array_filter($data['posts'], fn($p) => $p['id'] !== $m[1]));
        blog_save($data);
    }
    redirect('/admin/blog?deleted=1');
}

$queries = admin_queries();

if ($adminPath === '/') {
    $view = 'dashboard';
    $pageTitle = 'Dashboard';
} elseif ($adminPath === '/queries') {
    $view = 'queries';
    $pageTitle = 'Queries';
} elseif (preg_match('#^/queries/([A-Za-z0-9_-]+)$#', $adminPath, $m) && ($query = admin_find_query($queries, $m[1]))) {
    $view = 'query';
    $pageTitle = 'Query from ' . $query['name'];
} elseif ($adminPath === '/seo') {
    $targets = seo_targets($routes);
    $view = 'seo';
    $pageTitle = 'SEO & Content';
} elseif ($adminPath === '/seo/edit'
    && ($targets = seo_targets($routes))
    && isset($targets[$scope = (string) ($_GET['scope'] ?? '')])) {
    $target = $targets[$scope];
    $units = seo_units($scope, $target);
    $error = null;
    $posted = null;
    if ($method === 'POST') {
        if (!csrf_valid($_POST['csrf'] ?? '')) {
            $error = 'Your session expired. Please try again.';
        } elseif (seo_save($scope, $target, $units, $_POST, $error)) {
            redirect('/admin/seo/edit?scope=' . rawurlencode($scope) . '&saved=1');
        }
        $posted = $_POST;
    }
    $view = 'seo-edit';
    $pageTitle = ($target['type'] === 'page' ? '' : ucfirst($target['type']) . ': ') . $target['label'];
} elseif ($adminPath === '/blog') {
    $view = 'blog-list';
    $pageTitle = 'Blog';
} elseif ($adminPath === '/blog/categories') {
    $error = null;
    $message = null;
    if ($method === 'POST') {
        if (!csrf_valid($_POST['csrf'] ?? '')) {
            $error = 'Your session expired. Please try again.';
        } elseif ($message = blog_admin_categories_action($_POST, $error)) {
            $_SESSION['flash'] = $message;
            redirect('/admin/blog/categories');
        }
    }
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    $view = 'blog-categories';
    $pageTitle = 'Blog categories';
} elseif ($adminPath === '/blog/new'
    || (preg_match('#^/blog/edit/([a-f0-9]+)$#', $adminPath, $m) && ($editing = blog_find_post('id', $m[1])))) {
    $editing ??= null;
    $error = null;
    $posted = null;
    if ($method === 'POST') {
        $data = blog_data();
        if (!csrf_valid($_POST['csrf'] ?? '')) {
            $error = 'Your session expired. Please try again.';
        } elseif ($built = blog_admin_build_post($_POST, $editing, $data, $error)) {
            $found = false;
            foreach ($data['posts'] as $i => $p) {
                if ($p['id'] === $built['id']) {
                    $data['posts'][$i] = $built;
                    $found = true;
                }
            }
            if (!$found) {
                $data['posts'][] = $built;
            }
            if (blog_save($data)) {
                redirect('/admin/blog/edit/' . $built['id'] . '?saved=1');
            }
            $error = 'Could not write ' . basename(BLOG_FILE) . '. Check that the storage/ folder is writable.';
        }
        $posted = $_POST;
    }
    $view = 'blog-edit';
    $pageTitle = $editing ? 'Edit post' : 'New post';
    $extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">'
        . "\n    " . '<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js" defer></script>';
} else {
    http_response_code(404);
    $view = 'not-found';
    $pageTitle = 'Page not found';
}

include __DIR__ . '/layout.php';
