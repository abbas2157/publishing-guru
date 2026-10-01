<?php
/** All queries with search and filters. Expects $queries. */
$search = trim((string) ($_GET['q'] ?? ''));
$service = (string) ($_GET['service'] ?? '');
$status = (string) ($_GET['status'] ?? '');

$rows = array_values(array_filter($queries, function ($q) use ($search, $service, $status) {
    if ($service !== '' && service_label($q['service'] ?? null) !== $service) return false;
    if ($status !== '' && ($q['status'] ?? 'new') !== $status) return false;
    if ($search !== '') {
        $haystack = implode(' ', [$q['name'] ?? '', $q['email'] ?? '', $q['phone'] ?? '', $q['message'] ?? '']);
        if (mb_stripos($haystack, $search) === false) return false;
    }
    return true;
}));
$filtered = $search !== '' || $service !== '' || $status !== '';
if ($filtered) {
    $emptyTitle = 'No matching queries';
    $emptyText = 'Try a different search or clear the filters.';
}
?>
<div class="card">
    <form class="filters" method="get" action="<?= url('/admin/queries') ?>">
        <label class="input-wrap grow"><?= admin_icon('search') ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, phone or message" aria-label="Search"></label>
        <select name="service" aria-label="Service">
            <option value="">All services</option>
<?php foreach (INQUIRY_SERVICES as $label): ?>
            <option<?= $service === $label ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
        <select name="status" aria-label="Status">
            <option value="">All statuses</option>
<?php foreach (QUERY_STATUSES as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
<?php if ($filtered): ?>
        <a href="<?= url('/admin/queries') ?>" class="btn btn-ghost">Clear</a>
<?php endif; ?>
    </form>
    <p class="result-count muted"><?= count($rows) ?> of <?= count($queries) ?> <?= count($queries) === 1 ? 'query' : 'queries' ?></p>
    <?php include __DIR__ . '/_table.php'; ?>
</div>
