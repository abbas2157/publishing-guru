<?php /** Queries table. Expects $rows; optional $emptyTitle, $emptyText, $compact (hides the message column). */
$compact ??= false; ?>
<?php if (!$rows): ?>
<div class="empty">
    <img src="<?= asset('assets/contact-illustration.webp') ?>" width="808" height="844" alt="" class="empty-art" loading="lazy">
    <h3><?= e($emptyTitle ?? 'No queries yet') ?></h3>
    <p class="muted"><?= e($emptyText ?? 'Messages sent through the contact and homepage forms will appear here.') ?></p>
</div>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>From</th>
                <th>Service</th>
<?php if (!$compact): ?>
                <th class="hide-md">Message</th>
<?php endif; ?>
                <th class="hide-sm">Received</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($rows as $q): $href = url('/admin/queries/' . rawurlencode($q['id'])); ?>
            <tr class="<?= ($q['status'] ?? 'new') === 'new' ? 'is-new' : '' ?>">
                <td>
                    <a href="<?= $href ?>" class="person">
                        <span class="avatar avatar-sm"><?= e(initials($q['name'] ?? '')) ?></span>
                        <span><strong><?= e($q['name'] ?? '') ?></strong><small><?= e($q['email'] ?? '') ?></small></span>
                    </a>
                </td>
                <td><span class="chip"><?= e(service_label($q['service'] ?? null)) ?></span></td>
<?php if (!$compact): ?>
                <td class="hide-md"><a href="<?= $href ?>" class="excerpt"><?= e(mb_strimwidth($q['message'] ?? '', 0, 90, '…')) ?></a></td>
<?php endif; ?>
                <td class="hide-sm nowrap muted" title="<?= e(date('M j, Y g:i A', strtotime($q['created_at'] ?? 'now'))) ?>"><?= e(time_ago($q['created_at'] ?? '')) ?></td>
                <td><?= status_badge($q['status'] ?? null) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
