<?php
/**
 * Admin area front controller. Included from index.php for every /admin* request.
 * Expects $path (the request path relative to BASE_PATH).
 */
require __DIR__ . '/functions.php';

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
} else {
    http_response_code(404);
    $view = 'not-found';
    $pageTitle = 'Page not found';
}

include __DIR__ . '/layout.php';
