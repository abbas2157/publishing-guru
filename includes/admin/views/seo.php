<?php
/** SEO & content overview. Expects $targets, $routes. */
$data = cms_data();
$rows = ['page' => [], 'section' => [], 'global' => []];
$totals = ['issues' => 0, 'edits' => 0, 'missing_alt' => 0];

foreach ($targets as $scope => $target) {
    $units = seo_units($scope, $target);
    $overrides = $data['content'][$scope] ?? [];
    $images = [];
    foreach ($units as $unit) {
        if ($unit['type'] === 'alt') {
            $images[$unit['key']] = $unit['missing'] && !isset($overrides[$unit['key']]);
        }
    }
    $row = [
        'scope' => $scope,
        'target' => $target,
        'fields' => count(array_filter($units, fn($u) => $u['type'] !== 'alt')),
        'images' => count($images),
        'missing_alt' => count(array_filter($images)),
        'edits' => count($overrides) + count($data['meta'][$scope] ?? []) + count($data['faqs'][$scope]['items'] ?? []),
        'faqs' => count($data['faqs'][$scope]['items'] ?? []),
        'updated' => $data['updated'][$scope] ?? null,
    ];
    if ($target['type'] === 'page') {
        $meta = cms_page_meta($scope);
        $row['title'] = ($meta['title'] ?? '') ?: ($target['route']['title'] ?? DEFAULT_TITLE);
        $row['description'] = ($meta['description'] ?? '') ?: ($target['route']['description'] ?? DEFAULT_DESCRIPTION);
        $row['title_state'] = seo_length_state(mb_strlen($row['title']), SEO_TITLE_RANGE);
        $row['description_state'] = seo_length_state(mb_strlen($row['description']), SEO_DESCRIPTION_RANGE);
        $row['h1'] = count(array_filter($units, fn($u) => $u['tag'] === 'h1'));
        $row['schema'] = count($meta['schema'] ?? seo_default_schema($target['route']));
        $totals['issues'] += ($row['title_state'] !== 'ok') + ($row['description_state'] !== 'ok') + ($row['h1'] !== 1);
    }
    $totals['edits'] += $row['edits'];
    $totals['missing_alt'] += $row['missing_alt'];
    $rows[$target['type']][] = $row;
}

$editUrl = fn(string $scope) => url('/admin/seo/edit?scope=' . rawurlencode($scope));
$lengthBadge = function (string $state, int $length, array $range): string {
    $hint = $state === 'ok' ? 'Good length' : "Aim for {$range[0]}–{$range[1]} characters";
    return '<span class="len len-' . $state . '" title="' . e($hint) . '">' . $length . '</span>';
};
?>
<div class="stats stats-3">
    <div class="card stat">
        <span class="stat-icon"><?= admin_icon('file-text') ?></span>
        <div><p class="stat-label">Pages</p><p class="stat-value"><?= count($rows['page']) ?></p><p class="stat-hint"><?= count($rows['section']) ?> shared sections · header &amp; footer</p></div>
    </div>
    <div class="card stat">
        <span class="stat-icon<?= $totals['issues'] ? ' stat-icon-warn' : '' ?>"><?= admin_icon($totals['issues'] ? 'alert' : 'check') ?></span>
        <div><p class="stat-label">SEO checks to review</p><p class="stat-value"><?= $totals['issues'] ?></p><p class="stat-hint">Title &amp; description length, one H1 per page</p></div>
    </div>
    <div class="card stat">
        <span class="stat-icon"><?= admin_icon('type') ?></span>
        <div><p class="stat-label">Customised fields</p><p class="stat-value"><?= $totals['edits'] ?></p><p class="stat-hint"><?= $totals['missing_alt'] ? $totals['missing_alt'] . ' images without alt text' : 'Every image has alt text' ?></p></div>
    </div>
</div>

<div class="card seo-table-card">
    <div class="card-head">
        <h3>Pages</h3>
        <span class="muted hide-sm">Meta title, description, schema, image alt text and page content</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Page</th>
                    <th class="hide-md">Meta title</th>
                    <th title="Title length">Title</th>
                    <th title="Description length">Desc.</th>
                    <th class="hide-sm">H1</th>
                    <th class="hide-sm">Schema</th>
                    <th class="hide-sm">FAQs</th>
                    <th class="hide-sm">Images</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($rows['page'] as $row): ?>
                <tr>
                    <td>
                        <a href="<?= e($editUrl($row['scope'])) ?>" class="page-cell">
                            <strong><?= e($row['target']['label']) ?></strong>
                            <small><?= e($row['scope']) ?></small>
                        </a>
                    </td>
                    <td class="hide-md"><span class="excerpt"><?= e($row['title']) ?></span></td>
                    <td><?= $lengthBadge($row['title_state'], mb_strlen($row['title']), SEO_TITLE_RANGE) ?></td>
                    <td><?= $lengthBadge($row['description_state'], mb_strlen($row['description']), SEO_DESCRIPTION_RANGE) ?></td>
                    <td class="hide-sm"><span class="len len-<?= $row['h1'] === 1 ? 'ok' : 'warn' ?>" title="<?= $row['h1'] === 1 ? 'One H1' : 'A page should have exactly one H1' ?>"><?= $row['h1'] ?></span></td>
                    <td class="hide-sm"><?= $row['schema'] ? '<span class="chip">' . $row['schema'] . ' ' . ($row['schema'] === 1 ? 'block' : 'blocks') . '</span>' : '<span class="muted">—</span>' ?></td>
                    <td class="hide-sm"><?= $row['faqs'] ?: '<span class="muted">—</span>' ?></td>
                    <td class="hide-sm nowrap"><?= $row['images'] ?><?= $row['missing_alt'] ? ' <span class="len len-warn" title="Images without alt text">' . $row['missing_alt'] . ' no alt</span>' : '' ?></td>
                    <td class="nowrap actions-cell">
<?php if ($row['edits']): ?>
                        <span class="chip chip-accent" title="Customised fields"><?= $row['edits'] ?> edited</span>
<?php endif; ?>
                        <a href="<?= e($editUrl($row['scope'])) ?>" class="btn btn-ghost btn-sm">Edit</a>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2 seo-shared">
    <div class="card seo-table-card">
        <div class="card-head">
            <h3>Shared sections</h3>
            <span class="muted hide-sm">One edit updates every page</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Section</th><th>Used on</th><th class="hide-sm">Fields</th><th></th></tr></thead>
                <tbody>
<?php foreach ($rows['section'] as $row): ?>
                    <tr>
                        <td><a href="<?= e($editUrl($row['scope'])) ?>" class="page-cell"><strong><?= e($row['target']['label']) ?></strong></a></td>
                        <td><?php foreach ($row['target']['used_on'] as $p): ?><span class="chip"><?= e($routes[$p]['name']) ?></span> <?php endforeach; ?></td>
                        <td class="hide-sm"><?= $row['fields'] ?> text · <?= $row['images'] ?> img</td>
                        <td class="nowrap actions-cell">
<?php if ($row['edits']): ?>
                            <span class="chip chip-accent"><?= $row['edits'] ?> edited</span>
<?php endif; ?>
                            <a href="<?= e($editUrl($row['scope'])) ?>" class="btn btn-ghost btn-sm">Edit</a>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card seo-table-card">
        <div class="card-head"><h3>Site-wide</h3></div>
        <div class="table-wrap">
            <table class="table">
                <tbody>
<?php foreach ($rows['global'] as $row): ?>
                    <tr>
                        <td><a href="<?= e($editUrl($row['scope'])) ?>" class="page-cell"><strong><?= e($row['target']['label']) ?></strong><small><?= $row['fields'] ?> text · <?= $row['images'] ?> images</small></a></td>
                        <td class="nowrap actions-cell">
<?php if ($row['edits']): ?>
                            <span class="chip chip-accent"><?= $row['edits'] ?> edited</span>
<?php endif; ?>
                            <a href="<?= e($editUrl($row['scope'])) ?>" class="btn btn-ghost btn-sm">Edit</a>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
