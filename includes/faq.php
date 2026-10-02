<?php
/**
 * FAQ accordion shown after the page content when the page has FAQs (managed in /admin/seo).
 * Expects $faq from cms_page_faq(). Uses native <details>, so it works without JavaScript.
 */
?>
<section class="faq-section" id="faq" aria-labelledby="faq-title">
    <div class="container mx-auto px-4 sm:px-6">
        <div class="faq-head">
            <img src="<?= asset('assets/petals-ByR-N01G.svg') ?>" width="81" height="84" loading="lazy" decoding="async" alt="" class="faq-mark">
            <h2 id="faq-title" class="faq-title"><?= e($faq['title']) ?></h2>
<?php if ($faq['intro'] !== ''): ?>
            <p class="faq-intro"><?= e($faq['intro']) ?></p>
<?php endif; ?>
        </div>
        <div class="faq-list">
<?php foreach ($faq['items'] as $i => $item): ?>
            <details class="faq-item"<?= $i === 0 ? ' open' : '' ?>>
                <summary class="faq-q"><span><?= e($item['q']) ?></span><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="faq-icon" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg></summary>
                <div class="faq-a"><?= cms_faq_answer_html($item['a']) ?></div>
            </details>
<?php endforeach; ?>
        </div>
    </div>
</section>
