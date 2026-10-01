<?php
/** Dashboard overview. Expects $queries. */
$weekAgo = strtotime('-7 days');
$byService = [];
foreach ($queries as $q) {
    $label = service_label($q['service'] ?? null);
    $byService[$label] = ($byService[$label] ?? 0) + 1;
}
arsort($byService);
$stats = [
    ['Total queries', count($queries), 'inbox', 'All time'],
    ['New', $newCount, 'sparkle', 'Awaiting a reply'],
    ['This week', count(array_filter($queries, fn($q) => strtotime($q['created_at'] ?? '') >= $weekAgo)), 'calendar', 'Last 7 days'],
    ['Top service', $byService ? array_key_first($byService) : '—', 'tag', $byService ? reset($byService) . (reset($byService) === 1 ? ' query' : ' queries') : 'No data yet'],
];
$rows = array_slice($queries, 0, 6);
$compact = true;
?>
<section class="welcome">
    <div>
        <h2>Welcome back</h2>
        <p class="muted">Here’s what’s happening with your customer queries.</p>
    </div>
    <a href="<?= url('/admin/queries') ?>" class="btn btn-primary">View all queries <img src="<?= asset('assets/Arrow-dH2l6ufH.svg') ?>" width="37" height="9" alt="" class="btn-arrow"></a>
</section>

<section class="stats">
<?php foreach ($stats as [$label, $value, $icon, $hint]): ?>
    <div class="card stat">
        <span class="stat-icon"><?= admin_icon($icon) ?></span>
        <div>
            <p class="stat-label"><?= e($label) ?></p>
            <p class="stat-value<?= is_string($value) ? ' stat-value-text' : '' ?>"><?= e($value) ?></p>
            <p class="stat-hint"><?= e($hint) ?></p>
        </div>
    </div>
<?php endforeach; ?>
</section>

<section class="grid-2">
    <div class="card">
        <div class="card-head">
            <h3>Recent queries</h3>
            <a href="<?= url('/admin/queries') ?>" class="link">See all <?= admin_icon('arrow-right', 'icon icon-sm') ?></a>
        </div>
        <?php include __DIR__ . '/_table.php'; ?>
    </div>

    <div class="card">
        <div class="card-head"><h3>Queries by service</h3></div>
<?php if (!$byService): ?>
        <p class="muted pad">No queries yet.</p>
<?php else: $max = max($byService); ?>
        <ul class="bars">
<?php foreach ($byService as $label => $count): ?>
            <li>
                <span class="bar-label"><?= e($label) ?><strong><?= $count ?></strong></span>
                <span class="bar"><span style="width: <?= round($count / $max * 100) ?>%"></span></span>
            </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
    </div>
</section>
