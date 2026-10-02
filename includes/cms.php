<?php
/**
 * Content overrides managed from /admin/seo.
 *
 * Page meta (title, description, JSON-LD) is stored per route. Visible text and image
 * alt text are discovered automatically from the rendered HTML, so templates stay plain
 * HTML: every run of text (a heading, paragraph, list item, button label, ...) and every
 * <img> becomes an editable "unit" with a stable key. Overrides replace just that slice
 * of the output; everything else is served byte-for-byte as the template renders it.
 *
 * Scopes: a route path ("/", "/contact") for page templates, "section:<name>" for the
 * shared partials in includes/sections, and "global:header" / "global:footer".
 *
 * storage/content.json:
 *   meta:    { "/path": { title, description, schema: [ {...JSON-LD...} ] } }
 *   content: { scope: { key: value } }   text units store plain text, HTML units inline HTML,
 *                                        alt units ("a..." keys) the alt attribute
 *   faqs:    { "/path": { title, intro, items: [ { q, a } ] } }   answers are inline HTML
 *   updated: { scope: ISO 8601 }
 */

const CMS_VOID_TAGS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];
const CMS_INLINE_TAGS = ['span', 'strong', 'em', 'b', 'i', 'u', 'br', 'small', 'sup', 'sub', 'a', 'mark', 'abbr'];
const CMS_INLINE_ATTRS = ['class', 'href', 'target', 'rel', 'title', 'aria-label'];
const CMS_SKIP_OPEN = '<!--cms:skip-->';
const CMS_SKIP_CLOSE = '<!--/cms:skip-->';

// ---------------------------------------------------------------------------
// Storage
// ---------------------------------------------------------------------------

function cms_data(): array
{
    static $data = null;
    if ($data === null || !empty($GLOBALS['cms_reload'])) {
        unset($GLOBALS['cms_reload']);
        $json = is_file(CONTENT_FILE) ? json_decode((string) file_get_contents(CONTENT_FILE), true) : null;
        $data = is_array($json) ? $json : [];
        $data += ['meta' => [], 'content' => [], 'faqs' => [], 'updated' => []];
    }
    return $data;
}

function cms_save(array $data): bool
{
    $dir = dirname(CONTENT_FILE);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return false;
    }
    $tmp = CONTENT_FILE . '.' . bin2hex(random_bytes(4)) . '.tmp';
    // Browsers post UTF-8; anything else is replaced rather than failing the whole save.
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    if (!rename($tmp, CONTENT_FILE)) {
        @unlink($tmp);
        return false;
    }
    $GLOBALS['cms_reload'] = true;
    return true;
}

/** Stored meta for a route: any of title, description, schema (list of JSON-LD objects). */
function cms_page_meta(string $path): array
{
    return cms_data()['meta'][$path] ?? [];
}

const CMS_FAQ_TITLE = 'Frequently Asked Questions';

/** FAQs for a route: title, intro and items ([q, a]); empty when the page has none. */
function cms_page_faq(string $path): array
{
    $faq = cms_data()['faqs'][$path] ?? [];
    return empty($faq['items']) ? [] : $faq + ['title' => CMS_FAQ_TITLE, 'intro' => ''];
}

/** Stored FAQ answer → HTML: blank lines start a new paragraph, single line breaks become <br>. */
function cms_faq_answer_html(string $answer): string
{
    $paragraphs = preg_split('/\n\s*\n/', trim($answer));
    return implode('', array_map(fn($p) => '<p>' . nl2br(cms_html_out(trim($p)), false) . '</p>', $paragraphs));
}

/** FAQPage JSON-LD for a page's FAQs. */
function cms_faq_schema(array $faq): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn($item) => [
            '@type' => 'Question',
            'name' => $item['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => cms_plain($item['a'])],
        ], $faq['items']),
    ];
}

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

/** Run $render with output buffering and apply the overrides for $scope to what it printed. */
function cms_render(string $scope, callable $render): void
{
    ob_start();
    $render();
    echo cms_apply($scope, ob_get_clean());
}

/**
 * Apply stored overrides for $scope to $html and record every unit found in
 * $GLOBALS['cms_units'][$scope] (used by the admin editor).
 * Regions wrapped in CMS_SKIP_* markers belong to another scope and are left alone.
 */
function cms_apply(string $scope, string $html): string
{
    $units = cms_units($html);
    $GLOBALS['cms_units'][$scope] = $units;
    $overrides = cms_data()['content'][$scope] ?? [];

    $edits = [];
    foreach ($units as $unit) {
        if (!array_key_exists($unit['key'], $overrides)) {
            continue;
        }
        $value = (string) $overrides[$unit['key']];
        if ($unit['type'] === 'alt') {
            $tag = substr($html, $unit['start'], $unit['end'] - $unit['start']);
            $edits[] = [$unit['start'], $unit['end'], cms_set_alt($tag, $value)];
        } else {
            $edits[] = [$unit['start'], $unit['end'], $unit['type'] === 'html' ? cms_html_out($value) : e($value)];
        }
    }
    usort($edits, fn($a, $b) => $b[0] <=> $a[0]);
    foreach ($edits as [$start, $end, $replacement]) {
        $html = substr_replace($html, $replacement, $start, $end - $start);
    }
    return str_replace([CMS_SKIP_OPEN, CMS_SKIP_CLOSE], '', $html);
}

function cms_set_alt(string $imgTag, string $alt): string
{
    $attr = 'alt="' . e($alt) . '"';
    $count = 0;
    $tag = preg_replace('~(?<=\s)alt\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)~i', str_replace(['\\', '$'], ['\\\\', '\$'], $attr), $imgTag, 1, $count);
    return $count ? $tag : preg_replace('~^<img\b~i', '<img ' . $attr, $imgTag);
}

// ---------------------------------------------------------------------------
// Unit discovery
// ---------------------------------------------------------------------------

/**
 * Minimal HTML tree with byte offsets. Element nodes: tag, start, end, innerStart, innerEnd,
 * children. Text nodes: start, end. Comments, skip regions and the contents of script/style/
 * svg/noscript/select/textarea/template are opaque "raw" nodes.
 */
function cms_parse(string $html): object
{
    $len = strlen($html);
    $root = (object) ['type' => 'el', 'tag' => '#root', 'start' => 0, 'end' => $len, 'innerStart' => 0, 'innerEnd' => $len, 'children' => []];
    $stack = [$root];
    $pos = 0;
    $re = '~' . preg_quote(CMS_SKIP_OPEN, '~') . '.*?' . preg_quote(CMS_SKIP_CLOSE, '~')
        . '|<!--.*?-->'
        . '|<(script|style|svg|noscript|select|textarea|template)\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>.*?</\1\s*>'
        . '|</?([a-zA-Z][a-zA-Z0-9-]*)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>~is';
    preg_match_all($re, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

    foreach ($matches as $m) {
        [$token, $offset] = $m[0];
        $top = end($stack);
        if ($offset > $pos) {
            $top->children[] = (object) ['type' => 'text', 'start' => $pos, 'end' => $offset];
        }
        $tokenEnd = $offset + strlen($token);
        $pos = $tokenEnd;

        if (!isset($m[2][0])) {
            $top->children[] = (object) ['type' => 'raw', 'start' => $offset, 'end' => $tokenEnd];
            continue;
        }
        $tag = strtolower($m[2][0]);
        if ($token[1] === '/') {
            for ($i = count($stack) - 1; $i > 0 && $stack[$i]->tag !== $tag; $i--);
            if ($i === 0) {
                continue; // stray closing tag
            }
            for ($j = count($stack) - 1; $j >= $i; $j--) {
                $stack[$j]->innerEnd = $offset;
                $stack[$j]->end = $j === $i ? $tokenEnd : $offset;
            }
            array_splice($stack, $i);
            continue;
        }
        $node = (object) ['type' => 'el', 'tag' => $tag, 'start' => $offset, 'end' => $tokenEnd, 'innerStart' => $tokenEnd, 'innerEnd' => $tokenEnd, 'children' => []];
        $top->children[] = $node;
        if (!in_array($tag, CMS_VOID_TAGS, true) && substr($token, -2) !== '/>') {
            $stack[] = $node;
        }
    }
    if ($pos < $len) {
        end($stack)->children[] = (object) ['type' => 'text', 'start' => $pos, 'end' => $len];
    }
    for ($j = count($stack) - 1; $j > 0; $j--) {
        $stack[$j]->innerEnd = $stack[$j]->end = $len;
    }
    return $root;
}

/**
 * Editable units in document order. Each unit:
 *   key (unique for text, shared by identical images), type (text|html|alt), start/end (byte range replaced by an override),
 *   default (plain text for text units, inline HTML for html units, alt text for alt units),
 *   tag (containing element), group (section number, 0 = outside any <section>),
 *   group_label, and src for images.
 */
function cms_units(string $html): array
{
    $ctx = (object) ['html' => $html, 'units' => [], 'sections' => [], 'seen' => []];
    cms_walk(cms_parse($html), $ctx, 0);

    $labels = [];
    foreach ($ctx->sections as $n => $section) {
        $heading = cms_find_heading($section);
        $labels[$n] = $heading ? cms_plain(substr($html, $heading->innerStart, $heading->innerEnd - $heading->innerStart)) : '';
    }
    foreach ($ctx->units as &$unit) {
        $unit['group_label'] = $labels[$unit['group']] ?? '';
    }
    return $ctx->units;
}

function cms_walk(object $node, object $ctx, int $group): void
{
    $run = [];
    foreach ($node->children as $child) {
        if ($child->type === 'text' || cms_is_inline($child)) {
            $run[] = $child;
            continue;
        }
        cms_flush_run($run, $node, $ctx, $group);
        $run = [];
        if ($child->type !== 'el') {
            continue;
        }
        if ($child->tag === 'img') {
            cms_add_image($child, $ctx, $group);
        }
        $childGroup = $group;
        if ($child->tag === 'section' && $group === 0) {
            $childGroup = count($ctx->sections) + 1;
            $ctx->sections[$childGroup] = $child;
        }
        cms_walk($child, $ctx, $childGroup);
    }
    cms_flush_run($run, $node, $ctx, $group);
}

/** Inline formatting element containing only text and other inline elements. */
function cms_is_inline(object $node): bool
{
    if ($node->type !== 'el' || !in_array($node->tag, CMS_INLINE_TAGS, true)) {
        return false;
    }
    foreach ($node->children as $child) {
        if ($child->type === 'raw' || ($child->type === 'el' && !cms_is_inline($child))) {
            return false;
        }
    }
    return true;
}

function cms_flush_run(array $run, object $parent, object $ctx, int $group): void
{
    $html = $ctx->html;
    $members = array_values(array_filter($run, fn($n) => $n->type === 'el' || trim(substr($html, $n->start, $n->end - $n->start)) !== ''));
    if (!$members) {
        return;
    }
    // A lone wrapper such as <span>Label</span>: edit its contents, keep the wrapper.
    if (count($members) === 1 && $members[0]->type === 'el') {
        cms_walk($members[0], $ctx, $group);
        return;
    }
    $start = $members[0]->start;
    $end = end($members)->end;
    $slice = substr($html, $start, $end - $start);
    $start += strlen($slice) - strlen(ltrim($slice));
    $end -= strlen($slice) - strlen(rtrim($slice));
    $slice = trim($slice);
    if (!preg_match('/[\p{L}\p{N}]/u', cms_plain($slice))) {
        return;
    }
    $isHtml = strpos($slice, '<') !== false;
    $ctx->units[] = [
        'key' => cms_unique_key($ctx, 't' . substr(md5(preg_replace('/\s+/', ' ', cms_strip_base($slice))), 0, 10)),
        'type' => $isHtml ? 'html' : 'text',
        'start' => $start,
        'end' => $end,
        'default' => $isHtml ? $slice : html_entity_decode($slice, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'tag' => $parent->tag,
        'group' => $group,
    ];
}

function cms_add_image(object $img, object $ctx, int $group): void
{
    $tag = substr($ctx->html, $img->start, $img->end - $img->start);
    $alt = cms_attr($tag, 'alt');
    $src = (string) cms_attr($tag, 'src');
    $srcKey = strncmp($src, 'data:', 5) === 0 ? md5($src) : basename((string) parse_url($src, PHP_URL_PATH));
    $ctx->units[] = [
        // The same image with the same alt shares one key, so one edit covers every use of it.
        'key' => 'a' . substr(md5($srcKey . '|' . $alt), 0, 10),
        'type' => 'alt',
        'start' => $img->start,
        'end' => $img->end,
        'default' => (string) $alt,
        'missing' => $alt === null,
        'src' => $src,
        'tag' => 'img',
        'group' => $group,
    ];
}

function cms_unique_key(object $ctx, string $key): string
{
    $n = $ctx->seen[$key] = ($ctx->seen[$key] ?? 0) + 1;
    return $n === 1 ? $key : $key . '-' . $n;
}

function cms_find_heading(object $node): ?object
{
    foreach ($node->children as $child) {
        if ($child->type !== 'el') {
            continue;
        }
        if (in_array($child->tag, ['h1', 'h2', 'h3', 'h4'], true)) {
            return $child;
        }
        if ($found = cms_find_heading($child)) {
            return $found;
        }
    }
    return null;
}

/** Decoded attribute value from a single tag, or null when absent. */
function cms_attr(string $tag, string $name): ?string
{
    if (!preg_match('~(?<=\s)' . preg_quote($name, '~') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i', $tag, $m)) {
        return null;
    }
    return html_entity_decode($m[1] !== '' ? $m[1] : (($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Visible text of an HTML fragment, whitespace collapsed. */
function cms_plain(string $html): string
{
    $text = html_entity_decode(strip_tags(preg_replace('~<br\s*/?>~i', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text));
}

// ---------------------------------------------------------------------------
// Inline HTML values
// ---------------------------------------------------------------------------

/** Remove BASE_PATH from root-relative links so stored content works on any install path. */
function cms_strip_base(string $html): string
{
    if (BASE_PATH === '') {
        return $html;
    }
    return preg_replace('~(\s(?:href|src)=["\'])' . preg_quote(BASE_PATH, '~') . '(?=/)~i', '$1', $html);
}

/** Stored inline HTML → output (root-relative links get BASE_PATH back). */
function cms_html_out(string $html): string
{
    if (BASE_PATH === '') {
        return $html;
    }
    return preg_replace('~(\shref=["\'])(?=/(?!/))~i', '$1' . str_replace(['\\', '$'], ['\\\\', '\$'], BASE_PATH), $html);
}

/**
 * Clean admin-entered inline HTML for storage: only CMS_INLINE_TAGS with CMS_INLINE_ATTRS,
 * no script-capable URLs, tags balanced. Text is kept as typed.
 */
function cms_clean_html(string $html): string
{
    $out = '';
    $open = [];
    $parts = preg_split('~(<!--.*?-->|<(?:script|style)\b.*?</(?:script|style)\s*>|</?[a-zA-Z][^>]*>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => $part) {
        if ($i % 2 === 0) {
            $out .= str_replace('<', '&lt;', $part);
            continue;
        }
        if (!preg_match('~^<(/?)([a-zA-Z][a-zA-Z0-9]*)~', $part, $m) || !in_array($tag = strtolower($m[2]), CMS_INLINE_TAGS, true)) {
            continue;
        }
        if ($m[1] === '/') {
            $i2 = array_search($tag, array_reverse($open, true), true);
            if ($i2 === false) {
                continue;
            }
            while (count($open) > $i2) {
                $out .= '</' . array_pop($open) . '>';
            }
            continue;
        }
        $attrs = '';
        foreach (CMS_INLINE_ATTRS as $name) {
            $value = cms_attr($part, $name);
            if ($value === null || ($name === 'href' && preg_match('~^\s*(?:javascript|data|vbscript):~i', $value))) {
                continue;
            }
            $attrs .= ' ' . $name . '="' . e($value) . '"';
        }
        $out .= '<' . $tag . $attrs . '>';
        if ($tag !== 'br') {
            $open[] = $tag;
        }
    }
    while ($open) {
        $out .= '</' . array_pop($open) . '>';
    }
    return cms_strip_base(trim($out));
}
