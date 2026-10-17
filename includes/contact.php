<?php
/**
 * POST /api/contact: store a contact-form submission in pg_queries (shown in /admin/queries).
 * Called by the contact forms in app.js. Responds with JSON.
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

/**
 * UTM tags, ad click type, referrer and landing page sent by app.js (captured when the visit
 * started), cleaned and cut to the column sizes in DB_QUERY_ATTRIBUTION_COLUMNS.
 */
function contact_attribution(array $in): array
{
    $text = fn(string $key, int $max) => mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) (is_scalar($in[$key] ?? null) ? $in[$key] : ''))), 0, $max);
    $url = function (string $key) use ($text): string {
        $value = $text($key, 255);
        return preg_match('~^(https?://|/)~i', $value) ? $value : '';
    };
    return [
        'utm_source' => $text('utm_source', 100),
        'utm_medium' => $text('utm_medium', 100),
        'utm_campaign' => $text('utm_campaign', 150),
        'utm_term' => $text('utm_term', 150),
        'utm_content' => $text('utm_content', 150),
        'click_id' => in_array($in['click_id'] ?? '', ['gclid', 'fbclid', 'msclkid', 'ttclid'], true) ? $in['click_id'] : '',
        'referrer' => $url('referrer'),
        'landing_page' => $url('landing_page'),
    ];
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
    $phone = $field('phone', 50);
    $message = $field('message', 5000);
    // Same rules and wording as the browser-side checks in app.js; returned per field.
    $errors = [];
    if ($name === '') {
        $errors['name'] = 'Please enter your name.';
    }
    if ($email === '') {
        $errors['email'] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That email doesn’t look right. Please check it (e.g. name@example.com).';
    }
    if ($phone !== '' && strlen(preg_replace('/\D/', '', $phone)) < 7) {
        $errors['phone'] = 'Please enter a valid phone number, or leave it blank.';
    }
    if (mb_strlen($message) < 10) {
        $errors['message'] = $message === '' ? 'Please tell us a little about your project.' : 'Please add a few more details (at least 10 characters).';
    }
    if ($errors) {
        contact_respond(['error' => 'Please fix the highlighted fields.', 'fields' => $errors], 422);
    }

    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $since = date('Y-m-d H:i:s', time() - CONTACT_RATE_WINDOW);
    if ((int) db_value('SELECT COUNT(*) FROM pg_queries WHERE ip = ? AND created_at >= ?', [$ip, $since]) >= CONTACT_RATE_LIMIT) {
        contact_respond(['error' => 'Too many messages. Please try again in a few minutes.'], 429);
    }

    $attribution = contact_attribution(is_array($in['attribution'] ?? null) ? $in['attribution'] : []);
    db_query(
        'INSERT INTO pg_queries (source, service, name, email, phone, message, ip, user_agent, '
            . implode(', ', array_keys($attribution)) . ', created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '
            . implode(', ', array_fill(0, count($attribution), '?')) . ', ?)',
        [
            $field('source', 20) === 'home' ? 'home' : 'contact',
            $field('service', 100),
            $name,
            $email,
            $phone,
            $message,
            $ip,
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ...array_values($attribution),
            date('Y-m-d H:i:s'),
        ]
    );
    contact_respond(['ok' => true]);
}
