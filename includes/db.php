<?php
/**
 * MySQL / MariaDB access (PDO) and schema.
 *
 * Tables are created on first use: db() checks pg_settings.schema_version once per request
 * and runs db_migrate() when the schema is missing or older than DB_SCHEMA_VERSION. The first
 * migration also imports the JSON files used before the database (storage/*.json).
 *
 * All values reach SQL through prepared statements. DATETIME columns hold server-local time.
 */

const DB_SCHEMA_VERSION = 1;

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        db_unavailable($e);
    }
    // Keep NOW() in step with PHP's date() so scheduled posts go live on time.
    $pdo->exec("SET time_zone = '" . date('P') . "'");

    try {
        $version = (int) $pdo->query("SELECT value FROM pg_settings WHERE name = 'schema_version'")->fetchColumn();
    } catch (PDOException $e) {
        $version = 0;
    }
    if ($version < DB_SCHEMA_VERSION) {
        db_migrate($pdo, $version);
    }
    return $pdo;
}

/** The database can't be reached: log the real error, show visitors a short 503 page. */
function db_unavailable(Throwable $e): void
{
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    header('Retry-After: 120');
    header('Cache-Control: no-store');
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Service temporarily unavailable. Please try again shortly.']);
        exit;
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">',
        '<meta name="robots" content="noindex"><title>Back shortly | ', e(SITE_NAME), '</title></head>',
        '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#f2eee8;color:#222;font-family:Open Sans,Arial,sans-serif;text-align:center;padding:24px">',
        '<div><h1 style="font-family:Playfair Display,Georgia,serif;font-size:32px;margin:0 0 12px">We\'ll be right back</h1>',
        '<p style="margin:0;opacity:.8">The site is having a brief technical issue. Please try again in a few minutes.</p></div></body></html>';
    exit;
}

/** Run a statement; returns it for fetching. */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    return db_query($sql, $params)->fetch() ?: null;
}

function db_value(string $sql, array $params = [])
{
    return db_query($sql, $params)->fetchColumn();
}

/** Run $fn inside a transaction; rolled back if it throws. */
function db_transaction(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** ISO 8601 → DATETIME string (server-local), or null. */
function db_datetime(?string $iso): ?string
{
    $ts = $iso ? strtotime($iso) : false;
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

/** DATETIME → ISO 8601, or null. */
function db_iso(?string $datetime): ?string
{
    $ts = $datetime ? strtotime($datetime) : false;
    return $ts ? date('c', $ts) : null;
}

// ---------------------------------------------------------------------------
// Schema
// ---------------------------------------------------------------------------

function db_migrate(PDO $pdo, int $from): void
{
    $table = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $statements = [
        "CREATE TABLE IF NOT EXISTS pg_settings (
            name VARCHAR(64) NOT NULL PRIMARY KEY,
            value TEXT NOT NULL
        ) $table",

        // Contact-form submissions.
        "CREATE TABLE IF NOT EXISTS pg_queries (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(20) NOT NULL DEFAULT 'contact',
            service VARCHAR(100) NOT NULL DEFAULT '',
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(50) NOT NULL DEFAULT '',
            message TEXT NOT NULL,
            status ENUM('new','read','replied') NOT NULL DEFAULT 'new',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            KEY idx_created (created_at),
            KEY idx_status (status),
            KEY idx_ip_created (ip, created_at)
        ) $table",

        // Per-page SEO meta and FAQ section heading (path = route, e.g. '/', '/contact').
        "CREATE TABLE IF NOT EXISTS pg_page_meta (
            path VARCHAR(191) NOT NULL PRIMARY KEY,
            title VARCHAR(255) NULL,
            description TEXT NULL,
            schema_json MEDIUMTEXT NULL,
            faq_title VARCHAR(255) NULL,
            faq_intro VARCHAR(500) NULL,
            updated_at DATETIME NOT NULL
        ) $table",

        // Text / alt overrides discovered by includes/cms.php (scope = page path, section:x, global:x).
        "CREATE TABLE IF NOT EXISTS pg_content (
            scope VARCHAR(191) NOT NULL,
            item_key VARCHAR(64) NOT NULL,
            value TEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (scope, item_key)
        ) $table",

        "CREATE TABLE IF NOT EXISTS pg_faqs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            path VARCHAR(191) NOT NULL,
            position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            question VARCHAR(300) NOT NULL,
            answer TEXT NOT NULL,
            KEY idx_path (path, position)
        ) $table",

        "CREATE TABLE IF NOT EXISTS pg_categories (
            slug VARCHAR(80) NOT NULL PRIMARY KEY,
            name VARCHAR(60) NOT NULL,
            description VARCHAR(300) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL
        ) $table",

        "CREATE TABLE IF NOT EXISTS pg_posts (
            id CHAR(12) NOT NULL PRIMARY KEY,
            slug VARCHAR(80) NOT NULL,
            title VARCHAR(200) NOT NULL,
            excerpt VARCHAR(300) NOT NULL DEFAULT '',
            content MEDIUMTEXT NOT NULL,
            image VARCHAR(255) NOT NULL DEFAULT '',
            image_alt VARCHAR(250) NOT NULL DEFAULT '',
            category VARCHAR(80) NULL,
            tags VARCHAR(1000) NOT NULL DEFAULT '[]',
            author VARCHAR(80) NOT NULL DEFAULT '',
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            meta_title VARCHAR(200) NOT NULL DEFAULT '',
            meta_description VARCHAR(400) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_slug (slug),
            KEY idx_live (status, published_at),
            KEY idx_category (category),
            CONSTRAINT fk_post_category FOREIGN KEY (category) REFERENCES pg_categories (slug)
                ON UPDATE CASCADE ON DELETE SET NULL
        ) $table",

        // Old slugs of posts whose URL changed; /blog/<old_slug> 301-redirects to the post.
        "CREATE TABLE IF NOT EXISTS pg_post_redirects (
            old_slug VARCHAR(80) NOT NULL PRIMARY KEY,
            post_id CHAR(12) NOT NULL,
            KEY idx_post (post_id),
            CONSTRAINT fk_redirect_post FOREIGN KEY (post_id) REFERENCES pg_posts (id) ON DELETE CASCADE
        ) $table",
    ];
    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }

    if ($from === 0) {
        db_import_legacy_json($pdo);
    }
    $pdo->prepare("REPLACE INTO pg_settings (name, value) VALUES ('schema_version', ?)")->execute([(string) DB_SCHEMA_VERSION]);
}

/** One-time import of storage/{queries,content,blog}.json; imported files are renamed *.imported. */
function db_import_legacy_json(PDO $pdo): void
{
    $read = function (string $name) {
        $file = LEGACY_STORAGE . '/' . $name;
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return is_array($data) ? [$file, $data] : [null, null];
    };
    $now = date('Y-m-d H:i:s');
    $done = [];

    [$file, $queries] = $read('queries.json');
    if ($file) {
        $stmt = $pdo->prepare('INSERT INTO pg_queries (source, service, name, email, phone, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($queries as $q) {
            $stmt->execute([$q['source'] ?? 'contact', $q['service'] ?? '', $q['name'] ?? '', $q['email'] ?? '', $q['phone'] ?? '', $q['message'] ?? '',
                in_array($q['status'] ?? '', ['new', 'read', 'replied'], true) ? $q['status'] : 'new', db_datetime($q['created_at'] ?? null) ?? $now]);
        }
        $done[] = $file;
    }

    [$file, $content] = $read('content.json');
    if ($file) {
        $meta = $pdo->prepare('INSERT INTO pg_page_meta (path, title, description, schema_json, faq_title, faq_intro, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), schema_json = VALUES(schema_json), faq_title = VALUES(faq_title), faq_intro = VALUES(faq_intro)');
        $paths = array_unique(array_merge(array_keys($content['meta'] ?? []), array_keys($content['faqs'] ?? [])));
        foreach ($paths as $path) {
            $m = $content['meta'][$path] ?? [];
            $f = $content['faqs'][$path] ?? [];
            $meta->execute([$path, $m['title'] ?? null, $m['description'] ?? null, isset($m['schema']) ? json_encode($m['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                $f['title'] ?? null, $f['intro'] ?? null, db_datetime($content['updated'][$path] ?? null) ?? $now]);
        }
        $item = $pdo->prepare('REPLACE INTO pg_content (scope, item_key, value, updated_at) VALUES (?, ?, ?, ?)');
        foreach ($content['content'] ?? [] as $scope => $values) {
            foreach ($values as $key => $value) {
                $item->execute([$scope, $key, (string) $value, $now]);
            }
        }
        $faq = $pdo->prepare('INSERT INTO pg_faqs (path, position, question, answer) VALUES (?, ?, ?, ?)');
        foreach ($content['faqs'] ?? [] as $path => $f) {
            foreach ($f['items'] ?? [] as $i => $row) {
                $faq->execute([$path, $i, $row['q'], $row['a']]);
            }
        }
        $done[] = $file;
    }

    [$file, $blog] = $read('blog.json');
    if ($file) {
        $cat = $pdo->prepare('INSERT IGNORE INTO pg_categories (slug, name, description, created_at) VALUES (?, ?, ?, ?)');
        foreach ($blog['categories'] ?? [] as $c) {
            $cat->execute([$c['slug'], $c['name'], $c['description'] ?? '', $now]);
        }
        foreach ($blog['posts'] ?? [] as $p) {
            blog_store_post($p, $pdo);
        }
        $done[] = $file;
    }

    foreach ($done as $file) {
        @rename($file, $file . '.imported');
    }
}
