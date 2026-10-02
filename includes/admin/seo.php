<?php
/**
 * SEO & content editor helpers (/admin/seo). Storage and rendering live in includes/cms.php.
 */

const SEO_TITLE_RANGE = [30, 60];
const SEO_DESCRIPTION_RANGE = [70, 160];

/**
 * Everything that can be edited, keyed by content scope:
 * pages (route path), shared sections ("section:<name>") and the site-wide header/footer.
 */
function seo_targets(array $routes): array
{
    $root = dirname(__DIR__, 2);
    $targets = [];
    $templates = [];
    foreach ($routes as $path => $route) {
        $targets[$path] = ['type' => 'page', 'label' => $route['name'], 'route' => $route];
        $templates[$path] = (string) file_get_contents($root . '/pages/' . $route['view'] . '.php');
    }
    foreach (glob($root . '/includes/sections/*.php') as $file) {
        $name = basename($file, '.php');
        $needle = "partial('sections/$name')";
        $targets['section:' . $name] = [
            'type' => 'section',
            'label' => ucwords(str_replace('-', ' ', $name)),
            'name' => $name,
            'used_on' => array_keys(array_filter($templates, fn($tpl) => strpos($tpl, $needle) !== false)),
        ];
    }
    $targets['global:header'] = ['type' => 'global', 'label' => 'Header & mobile menu', 'partial' => 'header'];
    $targets['global:footer'] = ['type' => 'global', 'label' => 'Footer', 'partial' => 'footer'];
    return $targets;
}

/** Render a target's template (output discarded) and return its editable units. */
function seo_units(string $scope, array $target): array
{
    unset($GLOBALS['cms_units'][$scope]);
    ob_start();
    if ($target['type'] === 'page') {
        (static function (string $__file) {
            include $__file;
        })(dirname(__DIR__, 2) . '/pages/' . $target['route']['view'] . '.php');
        cms_apply($scope, ob_get_clean());
    } elseif ($target['type'] === 'section') {
        partial('sections/' . $target['name']);
        ob_end_clean();
    } else {
        partial($target['partial']);
        cms_apply($scope, ob_get_clean());
    }
    return $GLOBALS['cms_units'][$scope] ?? [];
}

/** Units grouped for the editor: [group label => [unit, ...]], images listed once per key. */
function seo_unit_groups(array $units, string $fallback): array
{
    $groups = [];
    $seen = [];
    foreach ($units as $unit) {
        if (isset($seen[$unit['key']])) {
            $seen[$unit['key']]++;
            continue;
        }
        $seen[$unit['key']] = 1;
        $label = $unit['group'] ? ($unit['group_label'] ?: 'Section ' . $unit['group']) : $fallback;
        $groups[$unit['group'] . '|' . $label][] = $unit['key'];
    }
    $byKey = [];
    foreach ($units as $unit) {
        $byKey[$unit['key']] ??= $unit + ['uses' => $seen[$unit['key']]];
    }
    $out = [];
    foreach ($groups as $id => $keys) {
        $out[] = ['label' => substr($id, strpos($id, '|') + 1), 'units' => array_map(fn($k) => $byKey[$k], $keys)];
    }
    return $out;
}

/** Default JSON-LD for a route as a list of objects. */
function seo_default_schema(array $route): array
{
    return json_decode(json_encode(array_values($route['schema'] ?? [])), true);
}

/**
 * Parse admin-entered JSON-LD into a list of schema objects (a single object is wrapped).
 * Returns null and sets $error when it is not usable.
 */
function seo_parse_schema(string $json, ?string &$error): ?array
{
    $value = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error = 'Schema is not valid JSON (' . json_last_error_msg() . '). Nothing was saved.';
        return null;
    }
    if (!is_array($value)) {
        $error = 'Schema must be a JSON object or a list of objects. Nothing was saved.';
        return null;
    }
    if ($value && !array_is_list($value)) {
        $value = [$value];
    }
    foreach ($value as $i => $item) {
        if (!is_array($item) || ($item && array_is_list($item)) || empty($item['@type'])) {
            $error = 'Schema item ' . ($i + 1) . ' needs an "@type" (for example "Organization" or "Service"). Nothing was saved.';
            return null;
        }
        $value[$i] = ['@context' => $item['@context'] ?? 'https://schema.org'] + $item;
    }
    return $value;
}

/**
 * Submitted FAQ form → stored shape { title?, intro?, items: [{q, a}] } in the submitted order.
 * Rows left completely empty are dropped; a half-filled row is an error.
 */
function seo_parse_faq($input, ?string &$error): ?array
{
    $input = is_array($input) ? $input : [];
    $faq = [];
    $title = trim(preg_replace('/\s+/u', ' ', (string) ($input['title'] ?? '')));
    if ($title !== '' && $title !== CMS_FAQ_TITLE) {
        $faq['title'] = $title;
    }
    $intro = trim(preg_replace('/\s+/u', ' ', (string) ($input['intro'] ?? '')));
    if ($intro !== '') {
        $faq['intro'] = $intro;
    }
    $faq['items'] = [];
    foreach (is_array($input['items'] ?? null) ? $input['items'] : [] as $row) {
        $q = trim(preg_replace('/\s+/u', ' ', (string) ($row['q'] ?? '')));
        $a = cms_clean_html(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", (string) ($row['a'] ?? ''))));
        if ($q === '' && $a === '') {
            continue;
        }
        if ($q === '' || $a === '') {
            $error = 'FAQ ' . (count($faq['items']) + 1) . ' needs both a question and an answer. Nothing was saved.';
            return null;
        }
        $faq['items'][] = ['q' => $q, 'a' => $a];
    }
    return $faq;
}

function seo_same_text(string $a, string $b): bool
{
    return preg_replace('/\s+/u', ' ', trim($a)) === preg_replace('/\s+/u', ' ', trim($b));
}

/**
 * Apply a submitted editor form to the stored content for $scope.
 * Values equal to the template default are dropped, so the template stays the source of truth.
 */
function seo_save(string $scope, array $target, array $units, array $post, ?string &$error): bool
{
    $data = cms_data();

    if ($target['type'] === 'page') {
        $route = $target['route'];
        $meta = $data['meta'][$scope] ?? [];
        foreach (['title', 'description'] as $field) {
            $value = trim(preg_replace('/\s+/u', ' ', (string) ($post['meta'][$field] ?? '')));
            if ($value === '' || $value === ($route[$field] ?? '')) {
                unset($meta[$field]);
            } else {
                $meta[$field] = $value;
            }
        }
        $json = trim((string) ($post['meta']['schema'] ?? ''));
        $schema = $json === '' ? seo_default_schema($route) : seo_parse_schema($json, $error);
        if ($schema === null) {
            return false;
        }
        if ($schema == seo_default_schema($route)) {
            unset($meta['schema']);
        } else {
            $meta['schema'] = $schema;
        }
        if ($meta) {
            $data['meta'][$scope] = $meta;
        } else {
            unset($data['meta'][$scope]);
        }

        $faq = seo_parse_faq($post['faq'] ?? [], $error);
        if ($faq === null) {
            return false;
        }
        if ($faq['items']) {
            $data['faqs'][$scope] = $faq;
        } else {
            unset($data['faqs'][$scope]);
        }
    }

    $content = $data['content'][$scope] ?? [];
    foreach ($units as $unit) {
        $key = $unit['key'];
        $field = $unit['type'] === 'alt' ? 'alt' : 'content';
        if (!isset($post[$field][$key]) || !is_string($post[$field][$key])) {
            continue;
        }
        $value = trim(str_replace("\r\n", "\n", $post[$field][$key]));
        if ($unit['type'] === 'alt') {
            $value = preg_replace('/\s+/u', ' ', $value);
            // An image without alt stays flagged as missing until real text is entered.
            $keep = $unit['missing'] ? $value !== '' : $value !== $unit['default'];
        } elseif ($unit['type'] === 'html') {
            $value = cms_clean_html($value);
            $keep = $value !== '' && $value !== cms_clean_html($unit['default']);
        } else {
            $keep = $value !== '' && !seo_same_text($value, $unit['default']);
        }
        if ($keep) {
            $content[$key] = $value;
        } else {
            unset($content[$key]);
        }
    }
    if ($content) {
        $data['content'][$scope] = $content;
    } else {
        unset($data['content'][$scope]);
    }
    $data['updated'][$scope] = date('c');

    if (!cms_save($data)) {
        $error = 'Could not write ' . basename(CONTENT_FILE) . '. Check that the storage/ folder is writable.';
        return false;
    }
    return true;
}

/** Status for a length check: ok, warn (outside the range) or empty. */
function seo_length_state(int $length, array $range): string
{
    if ($length === 0) {
        return 'empty';
    }
    return $length >= $range[0] && $length <= $range[1] ? 'ok' : 'warn';
}

/** Page URL as shown in search results, e.g. publishinguru.com › services › amazon-ads */
function seo_breadcrumb_url(string $path): string
{
    $host = (string) parse_url(SITE_URL, PHP_URL_HOST);
    $host = preg_replace('/^www\./', '', $host);
    $parts = array_filter(explode('/', $path));
    return implode(' › ', array_merge([$host], $parts));
}
