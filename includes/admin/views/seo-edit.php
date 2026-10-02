<?php
/**
 * Editor for one content scope. Expects $scope, $target, $units, $routes, $error, $posted (form values after a failed save).
 */
$isPage = $target['type'] === 'page';
$overrides = cms_data()['content'][$scope] ?? [];
$groups = seo_unit_groups($units, $target['type'] === 'page' ? 'Page content' : $target['label']);

/** Current value of a unit as shown in the form. */
$current = function (array $unit) use ($overrides, $posted): string {
    $field = $unit['type'] === 'alt' ? 'alt' : 'content';
    if (isset($posted[$field][$unit['key']]) && is_string($posted[$field][$unit['key']])) {
        return $posted[$field][$unit['key']];
    }
    if (!array_key_exists($unit['key'], $overrides)) {
        return $unit['default'];
    }
    return $unit['type'] === 'html' ? cms_html_out($overrides[$unit['key']]) : $overrides[$unit['key']];
};

if ($isPage) {
    $route = $target['route'];
    $meta = cms_page_meta($scope);
    $defaultSchema = seo_default_schema($route);
    $prettyJson = fn(array $v) => $v ? json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    $title = $posted['meta']['title'] ?? (($meta['title'] ?? '') ?: $route['title']);
    $description = $posted['meta']['description'] ?? (($meta['description'] ?? '') ?: $route['description']);
    $schema = $posted['meta']['schema'] ?? $prettyJson($meta['schema'] ?? $defaultSchema);
    $liveUrl = SITE_URL . ($scope === '/' ? '/' : $scope);

    $storedFaq = cms_data()['faqs'][$scope] ?? [];
    $faqInput = $posted['faq'] ?? null;
    $faqTitle = $faqInput['title'] ?? ($storedFaq['title'] ?? CMS_FAQ_TITLE);
    $faqIntro = $faqInput['intro'] ?? ($storedFaq['intro'] ?? '');
    $faqItems = $faqInput !== null
        ? array_values(array_filter(is_array($faqInput['items'] ?? null) ? $faqInput['items'] : [], 'is_array'))
        : array_map(fn($item) => ['q' => $item['q'], 'a' => cms_html_out($item['a'])], $storedFaq['items'] ?? []);
}

/** One FAQ row in the editor; $i is the form index ("__i__" in the template). */
$faqRow = function ($i, string $q = '', string $a = ''): string {
    ob_start(); ?>
                <div class="faq-row" data-faq-item>
                    <div class="faq-row-head">
                        <span class="faq-num" data-faq-num>Q</span>
                        <span class="faq-row-actions">
                            <button type="button" class="icon-btn icon-btn-sm" data-faq-move="-1" title="Move up" aria-label="Move up"><?= admin_icon('chevron-up') ?></button>
                            <button type="button" class="icon-btn icon-btn-sm" data-faq-move="1" title="Move down" aria-label="Move down"><?= admin_icon('chevron-down') ?></button>
                            <button type="button" class="icon-btn icon-btn-sm icon-btn-danger" data-faq-remove title="Delete question" aria-label="Delete question"><?= admin_icon('trash') ?></button>
                        </span>
                    </div>
                    <input class="text-input faq-question" name="faq[items][<?= e((string) $i) ?>][q]" value="<?= e($q) ?>" placeholder="Question, e.g. How long does KDP publishing take?" maxlength="300" aria-label="Question">
                    <textarea class="text-input" name="faq[items][<?= e((string) $i) ?>][a]" rows="3" data-autosize placeholder="Answer. Leave a blank line between paragraphs; links like &lt;a href=&quot;/contact&quot;&gt;contact us&lt;/a&gt; are allowed." aria-label="Answer"><?= e($a) ?></textarea>
                </div>
<?php return ob_get_clean();
};

$images = [];
$texts = [];
foreach ($groups as $group) {
    foreach ($group['units'] as $unit) {
        if ($unit['type'] === 'alt') {
            $images[] = $unit + ['group_name' => $group['label']];
        } else {
            $texts[$group['label']][] = $unit;
        }
    }
}
$tagLabel = fn(string $tag) => ['li' => 'List item', 'p' => 'Paragraph', 'span' => 'Text', 'a' => 'Link', 'button' => 'Button', 'label' => 'Form label', 'div' => 'Text', 'td' => 'Table cell', 'th' => 'Table heading'][$tag] ?? strtoupper($tag);
?>
<a href="<?= url('/admin/seo') ?>" class="back-link"><?= admin_icon('arrow-left') ?> SEO &amp; Content</a>

<div class="editor-head">
    <div>
<?php if ($isPage): ?>
        <p class="muted"><?= e($scope) ?></p>
<?php elseif ($target['type'] === 'section'): ?>
        <p class="muted">Shared section, used on
            <?php foreach ($target['used_on'] as $p): ?><a class="chip" href="<?= e(url('/admin/seo/edit?scope=' . rawurlencode($p))) ?>"><?= e($routes[$p]['name']) ?></a> <?php endforeach; ?>
            Changes here apply to all of them.</p>
<?php else: ?>
        <p class="muted">Shown on every page.</p>
<?php endif; ?>
    </div>
    <div class="editor-tools">
        <label class="input-wrap filter-box"><?= admin_icon('search') ?><input type="search" placeholder="Find a field…" aria-label="Find a field" data-field-filter></label>
<?php if ($isPage): ?>
        <a class="btn btn-ghost" href="<?= url($scope) ?>" target="_blank" rel="noopener"><?= admin_icon('external') ?> View page</a>
<?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['saved']) && !$error): ?>
<p class="notice notice-ok" role="status"><?= admin_icon('check') ?> Changes saved and live on the website.</p>
<?php endif; ?>
<?php if ($error): ?>
<p class="alert" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" class="seo-form" data-dirty-form>
    <?= csrf_field() ?>

<?php if ($isPage): ?>
    <section class="card seo-card" id="search">
        <div class="card-head"><h3><?= admin_icon('search') ?> Search appearance</h3></div>
        <div class="seo-card-body seo-meta">
            <div class="seo-meta-fields">
                <div class="seo-field" data-field>
                    <div class="field-top">
                        <label for="meta-title">Meta title</label>
                        <span class="counter" data-counter-for="meta-title" data-min="<?= SEO_TITLE_RANGE[0] ?>" data-max="<?= SEO_TITLE_RANGE[1] ?>"></span>
                    </div>
                    <input class="text-input" id="meta-title" name="meta[title]" value="<?= e($title) ?>" data-default="<?= e($route['title']) ?>" data-serp="title" maxlength="200">
                    <div class="field-foot"><span class="muted">Shown as the clickable headline in Google. Aim for <?= SEO_TITLE_RANGE[0] ?>–<?= SEO_TITLE_RANGE[1] ?> characters.</span><button type="button" class="reset-btn" data-reset><?= admin_icon('reset', 'icon icon-sm') ?> Default</button></div>
                </div>
                <div class="seo-field" data-field>
                    <div class="field-top">
                        <label for="meta-description">Meta description</label>
                        <span class="counter" data-counter-for="meta-description" data-min="<?= SEO_DESCRIPTION_RANGE[0] ?>" data-max="<?= SEO_DESCRIPTION_RANGE[1] ?>"></span>
                    </div>
                    <textarea class="text-input" id="meta-description" name="meta[description]" rows="3" data-default="<?= e($route['description']) ?>" data-serp="description" data-autosize maxlength="400"><?= e($description) ?></textarea>
                    <div class="field-foot"><span class="muted">The summary under the title in search results. Aim for <?= SEO_DESCRIPTION_RANGE[0] ?>–<?= SEO_DESCRIPTION_RANGE[1] ?> characters.</span><button type="button" class="reset-btn" data-reset><?= admin_icon('reset', 'icon icon-sm') ?> Default</button></div>
                </div>
            </div>
            <div class="serp" aria-label="Google search preview">
                <p class="serp-label">Google preview</p>
                <div class="serp-site">
                    <span class="serp-favicon"><img src="<?= asset('assets/favicon-32x32-DOg4SlHu.png') ?>" alt="" width="18" height="18"></span>
                    <span><strong><?= e(SITE_NAME) ?></strong><small><?= e(seo_breadcrumb_url($scope)) ?></small></span>
                </div>
                <p class="serp-title" data-serp-out="title"><?= e($title) ?></p>
                <p class="serp-desc" data-serp-out="description"><?= e($description) ?></p>
            </div>
        </div>
    </section>

    <section class="card seo-card" id="schema">
        <div class="card-head">
            <h3><?= admin_icon('code') ?> Schema markup (JSON-LD)</h3>
            <a class="link hide-sm" href="https://search.google.com/test/rich-results?url=<?= rawurlencode($liveUrl) ?>" target="_blank" rel="noopener">Test live page <?= admin_icon('external', 'icon icon-sm') ?></a>
        </div>
        <div class="seo-card-body">
            <div class="seo-field" data-field>
                <div class="field-top">
                    <label for="meta-schema">Structured data for this page</label>
                    <span class="json-state" data-json-state></span>
                </div>
                <textarea class="text-input code-input" id="meta-schema" name="meta[schema]" rows="12" spellcheck="false" data-json data-autosize data-default="<?= e($prettyJson($defaultSchema)) ?>" placeholder='{ "@context": "https://schema.org", "@type": "WebPage", "name": "…" }'><?= e($schema) ?></textarea>
                <div class="field-foot">
                    <span class="muted">One JSON object or a list of objects (Organization, Service, Product, FAQPage…). Leave empty to use the default; enter <code>[]</code> for no schema. A BreadcrumbList is added automatically on inner pages.</span>
                    <button type="button" class="reset-btn" data-reset><?= admin_icon('reset', 'icon icon-sm') ?> Default</button>
                </div>
            </div>
        </div>
    </section>

    <section class="card seo-card" id="faqs" data-faq>
        <div class="card-head">
            <h3><?= admin_icon('help') ?> FAQs</h3>
            <span class="chip" data-faq-count></span>
        </div>
        <div class="seo-card-body">
            <p class="muted section-hint">Shown as an accordion at the end of the page, with FAQPage schema added automatically so Google can show the questions in search results. Reorder with the arrows; empty rows are ignored.</p>
            <div class="faq-settings">
                <div class="seo-field">
                    <div class="field-top"><label for="faq-title">Section heading</label></div>
                    <input class="text-input" id="faq-title" name="faq[title]" value="<?= e($faqTitle) ?>" placeholder="<?= e(CMS_FAQ_TITLE) ?>" maxlength="150">
                </div>
                <div class="seo-field">
                    <div class="field-top"><label for="faq-intro">Intro <span class="muted">(optional)</span></label></div>
                    <input class="text-input" id="faq-intro" name="faq[intro]" value="<?= e($faqIntro) ?>" placeholder="Answers to the questions authors ask us most." maxlength="300">
                </div>
            </div>
            <div class="faq-rows" data-faq-list>
<?php foreach ($faqItems as $i => $item): ?>
<?= $faqRow($i, (string) ($item['q'] ?? ''), (string) ($item['a'] ?? '')) ?>
<?php endforeach; ?>
            </div>
            <p class="faq-empty muted" data-faq-empty<?= $faqItems ? ' hidden' : '' ?>>No FAQs on this page yet.</p>
            <template data-faq-template><?= $faqRow('__i__') ?></template>
            <button type="button" class="btn btn-ghost faq-add" data-faq-add><?= admin_icon('plus') ?> Add question</button>
        </div>
    </section>
<?php endif; ?>

<?php if ($images): ?>
    <section class="card seo-card" id="images">
        <div class="card-head">
            <h3><?= admin_icon('image') ?> Image alt text</h3>
            <span class="muted hide-sm"><?= count($images) ?> <?= count($images) === 1 ? 'image' : 'images' ?></span>
        </div>
        <div class="seo-card-body">
            <p class="muted section-hint">Describe what the image shows, using keywords naturally. Leave empty for purely decorative icons (arrows, bullets) so screen readers skip them.</p>
            <div class="image-list">
<?php foreach ($images as $unit): $value = $current($unit); $edited = array_key_exists($unit['key'], $overrides); ?>
                <div class="seo-field image-field<?= $edited ? ' is-edited' : '' ?>" data-field>
                    <span class="thumb"><img src="<?= e($unit['src']) ?>" alt="" loading="lazy"></span>
                    <div class="image-meta">
                        <div class="field-top">
                            <label for="alt-<?= e($unit['key']) ?>"><?= e(strncmp($unit['src'], 'data:', 5) === 0 ? 'Inline icon' : basename((string) parse_url($unit['src'], PHP_URL_PATH))) ?></label>
                            <span class="field-tags">
<?php if ($unit['uses'] > 1): ?>
                                <span class="chip" title="The same image appears <?= $unit['uses'] ?> times; this alt text applies to all of them">×<?= $unit['uses'] ?></span>
<?php endif; ?>
<?php if ($unit['missing'] && !$edited): ?>
                                <span class="len len-warn">No alt</span>
<?php endif; ?>
                                <span class="edited-badge">Edited</span>
                            </span>
                        </div>
                        <div class="input-row">
                            <input class="text-input" id="alt-<?= e($unit['key']) ?>" name="alt[<?= e($unit['key']) ?>]" value="<?= e($value) ?>" data-default="<?= e($unit['default']) ?>" placeholder="Describe the image…" maxlength="250">
                            <button type="button" class="reset-btn" data-reset title="Restore the default alt text"><?= admin_icon('reset', 'icon icon-sm') ?></button>
                        </div>
                        <small class="muted"><?= e($unit['group_name']) ?></small>
                    </div>
                </div>
<?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php foreach ($texts as $label => $list): ?>
    <section class="card seo-card">
        <div class="card-head">
            <h3><?= admin_icon('type') ?> <?= e($label) ?></h3>
            <span class="muted hide-sm"><?= count($list) ?> <?= count($list) === 1 ? 'field' : 'fields' ?></span>
        </div>
        <div class="seo-card-body text-fields">
<?php foreach ($list as $unit): $value = $current($unit); $edited = array_key_exists($unit['key'], $overrides); $isHeading = preg_match('/^h[1-6]$/', $unit['tag']); ?>
            <div class="seo-field<?= $edited ? ' is-edited' : '' ?>" data-field>
                <div class="field-top">
                    <label for="c-<?= e($unit['key']) ?>"><span class="tag-chip<?= $isHeading ? ' tag-heading' : '' ?>"><?= e($tagLabel($unit['tag'])) ?></span></label>
                    <span class="field-tags">
<?php if ($unit['type'] === 'html'): ?>
                        <span class="chip" title="Inline HTML allowed: &lt;span&gt; &lt;strong&gt; &lt;em&gt; &lt;a&gt; &lt;br&gt;. Keep the class names to keep the styling.">HTML</span>
<?php endif; ?>
                        <span class="edited-badge">Edited</span>
                        <button type="button" class="reset-btn" data-reset title="Restore the original text"><?= admin_icon('reset', 'icon icon-sm') ?></button>
                    </span>
                </div>
                <textarea class="text-input<?= $unit['type'] === 'html' ? ' code-input' : '' ?><?= $isHeading ? ' heading-input' : '' ?>" id="c-<?= e($unit['key']) ?>" name="content[<?= e($unit['key']) ?>]" rows="1" data-autosize data-default="<?= e($unit['default']) ?>"><?= e($value) ?></textarea>
            </div>
<?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php if (!$images && !$texts && !$isPage): ?>
    <div class="card"><div class="empty"><h3>Nothing to edit here</h3><p class="muted">This section has no text or images.</p></div></div>
<?php endif; ?>

    <div class="save-bar">
        <span class="save-state" data-dirty-label>All changes saved</span>
        <a href="<?= url('/admin/seo') ?>" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary"><?= admin_icon('check') ?> Save changes</button>
    </div>
</form>
<p class="muted filter-empty" data-filter-empty hidden>No fields match your search.</p>
