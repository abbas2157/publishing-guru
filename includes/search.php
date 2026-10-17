<?php
/**
 * Site search (/search): every page in the route table plus live blog posts.
 * Pages are searched on their rendered text (including /admin/seo edits), so results
 * always match what visitors see. Small site, so the "index" is built per request.
 */

/** Visible text of a page, rendered the same way layout.php renders it. */
function search_page_text(string $path, array $page, array $routes): string
{
    ob_start();
    try {
        include __DIR__ . '/../pages/' . $page['view'] . '.php';
    } catch (Throwable $e) {
        ob_end_clean();
        return '';
    }
    $html = ob_get_clean();
    $html = ($page['cms'] ?? true) ? cms_apply($path, $html) : str_replace([CMS_SKIP_OPEN, CMS_SKIP_CLOSE], '', $html);
    $html = preg_replace('~<(script|style|svg|template|form)\b.*?</\1>~is', ' ', $html);
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES, 'UTF-8')));
}

/** Short excerpt of $text around the first search term, or its start. */
function search_snippet(string $text, array $terms, int $length = 180): string
{
    $pos = false;
    foreach ($terms as $term) {
        if (preg_match(search_term_pattern($term), $text, $m, PREG_OFFSET_CAPTURE)) {
            $p = mb_strlen(substr($text, 0, $m[0][1]));
            $pos = $pos === false ? $p : min($pos, $p);
        }
    }
    $start = $pos === false ? 0 : max(0, $pos - 60);
    $snippet = mb_substr($text, $start, $length);
    return ($start > 0 ? '…' : '') . trim($snippet) . ($start + $length < mb_strlen($text) ? '…' : '');
}

/** Escape $text and wrap each search term in <mark>. */
function search_highlight(string $text, array $terms): string
{
    $html = e($text);
    if (!$terms) {
        return $html;
    }
    $pattern = '/(?<![\p{L}\p{N}])(' . implode('|', array_map(fn($t) => preg_quote(e($t), '/'), $terms)) . ')/iu';
    return preg_replace($pattern, '<mark>$1</mark>', $html);
}

/** Regex matching $term at the start of a word, so "format" finds "formatting" but "cover" skips "discover". */
function search_term_pattern(string $term): string
{
    return "/(?<![\p{L}\p{N}])" . preg_quote($term, "/") . "/iu";
}

/** Search terms from the query: lower-case words of 2+ characters, at most 8. */
function search_terms(string $q): array
{
    $words = preg_split('/[^\p{L}\p{N}+&]+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY);
    return array_slice(array_values(array_unique(array_filter($words, fn($w) => mb_strlen($w) >= 2 || is_numeric($w)))), 0, 8);
}

/**
 * Results for $q, best first: [['title', 'url', 'snippet', 'type'], ...].
 * A result must contain every term; title hits rank above description and body hits.
 */
function site_search(string $q, array $routes): array
{
    $terms = search_terms($q);
    if (!$terms) {
        return [];
    }
    $results = [];
    $score = function (array $fields) use ($terms): int {
        $total = 0;
        foreach ($terms as $term) {
            $hit = 0;
            foreach ($fields as [$text, $weight]) {
                $count = preg_match_all(search_term_pattern($term), $text);
                $hit += min($count, 5) * $weight;
            }
            if ($hit === 0) {
                return 0; // every term must appear somewhere
            }
            $total += $hit;
        }
        return $total;
    };

    foreach ($routes as $path => $page) {
        if (($page['search'] ?? true) === false || in_array($page['view'], ['blog', '404'], true)) {
            continue;
        }
        $meta = cms_page_meta($path);
        $title = ($meta['title'] ?? '') ?: ($page['title'] ?? $page['name']);
        $description = ($meta['description'] ?? '') ?: ($page['description'] ?? '');
        $body = search_page_text($path, $page, $routes);
        $points = $score([[$page['name'] . ' ' . $title, 10], [$description, 4], [$body, 1]]);
        if ($points) {
            $results[] = [
                'title' => $page['name'],
                'url' => url($path),
                'snippet' => search_snippet($body !== '' ? $body : $description, $terms),
                'type' => str_starts_with($path, '/services/') ? 'Service' : 'Page',
                'score' => $points,
            ];
        }
    }

    [$posts] = blog_search(null, $terms[0], 50, 0);
    foreach ($posts as $post) {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($post['content'] ?? ''), ENT_QUOTES, 'UTF-8')));
        $points = $score([[$post['title'], 10], [($post['excerpt'] ?? '') . ' ' . implode(' ', $post['tags'] ?? []), 4], [$text, 1]]);
        if ($points) {
            $results[] = [
                'title' => $post['title'],
                'url' => blog_url($post),
                'snippet' => search_snippet(($post['excerpt'] ?? '') ?: $text, $terms),
                'type' => 'Article',
                'score' => $points,
            ];
        }
    }

    usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
    return $results;
}
