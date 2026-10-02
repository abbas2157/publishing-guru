<?php
/**
 * POST /api/contact: store a contact-form submission in pg_queries (shown in /admin/queries).
 * Called by app.js alongside the Supabase email function. Responds with JSON.
 */

const CONTACT_RATE_LIMIT = 5;        // submissions per IP ...
const CONTACT_RATE_WINDOW = 600;     // ... per this many seconds

function contact_respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

function contact_handle(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        contact_respond(['error' => 'Method not allowed'], 405);
    }
    // Only accept posts from this site's own pages.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        contact_respond(['error' => 'Forbidden'], 403);
    }

    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in)) {
        $in = $_POST;
    }
    $field = fn(string $key, int $max) => mb_substr(trim((string) (is_scalar($in[$key] ?? null) ? $in[$key] : '')), 0, $max);

    // Honeypot: real visitors never see or fill this field.
    if ($field('website', 200) !== '') {
        contact_respond(['ok' => true]);
    }

    $name = $field('name', 150);
    $email = $field('email', 190);
    $message = $field('message', 5000);
    if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        contact_respond(['error' => 'Please fill in your name, a valid email and a message.'], 422);
    }

    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $since = date('Y-m-d H:i:s', time() - CONTACT_RATE_WINDOW);
    if ((int) db_value('SELECT COUNT(*) FROM pg_queries WHERE ip = ? AND created_at >= ?', [$ip, $since]) >= CONTACT_RATE_LIMIT) {
        contact_respond(['error' => 'Too many messages. Please try again in a few minutes.'], 429);
    }

    db_query(
        'INSERT INTO pg_queries (source, service, name, email, phone, message, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $field('source', 20) === 'home' ? 'home' : 'contact',
            $field('service', 100),
            $name,
            $email,
            $field('phone', 50),
            $message,
            $ip,
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            date('Y-m-d H:i:s'),
        ]
    );
    contact_respond(['ok' => true]);
}
