<?php /** Single query detail. Expects $query. */ ?>
<a href="<?= url('/admin/queries') ?>" class="back-link"><?= admin_icon('arrow-left') ?> All queries</a>

<div class="grid-2 detail">
    <article class="card">
        <div class="card-head">
            <div class="person">
                <span class="avatar"><?= e(initials($query['name'] ?? '')) ?></span>
                <span><strong><?= e($query['name'] ?? '') ?></strong><small><?= e(date('l, M j, Y \a\t g:i A', strtotime($query['created_at'] ?? 'now'))) ?></small></span>
            </div>
            <?= status_badge($query['status'] ?? null) ?>
        </div>
        <div class="message"><?= nl2br(e($query['message'] ?? '')) ?></div>
        <div class="card-actions">
            <a class="btn btn-primary" href="mailto:<?= e($query['email'] ?? '') ?>?subject=<?= rawurlencode('Re: your ' . service_label($query['service'] ?? null) . ' inquiry') ?>"><?= admin_icon('mail') ?> Reply by email</a>
<?php if (!empty($query['phone'])): ?>
            <a class="btn btn-ghost" href="tel:<?= e(preg_replace('/[^\d+]/', '', $query['phone'])) ?>"><?= admin_icon('phone') ?> Call</a>
<?php endif; ?>
        </div>
    </article>

    <aside class="card">
        <div class="card-head"><h3>Details</h3></div>
        <dl class="details">
            <dt>Service</dt><dd><span class="chip"><?= e(service_label($query['service'] ?? null)) ?></span></dd>
            <dt>Email</dt><dd><a href="mailto:<?= e($query['email'] ?? '') ?>" class="link"><?= e($query['email'] ?? '') ?></a></dd>
            <dt>Phone</dt><dd><?= e(($query['phone'] ?? '') ?: '—') ?></dd>
            <dt>Source</dt><dd><?= e(source_label($query['source'] ?? null)) ?></dd>
            <dt>Received</dt><dd><?= e(time_ago($query['created_at'] ?? '')) ?></dd>
        </dl>
        <form method="post" action="<?= url('/admin/queries/' . $query['id'] . '/status') ?>" class="status-form">
            <?= csrf_field() ?>
            <span class="muted">Status</span>
            <div class="segmented segmented-3" role="radiogroup" aria-label="Status">
<?php foreach (QUERY_STATUSES as $value => $label): ?>
                <label><input type="radio" name="status" value="<?= $value ?>"<?= ($query['status'] ?? 'new') === $value ? ' checked' : '' ?> onchange="this.form.submit()"><span><?= $label ?></span></label>
<?php endforeach; ?>
            </div>
            <noscript><button type="submit" class="btn btn-ghost btn-sm">Update</button></noscript>
        </form>
    </aside>
</div>
