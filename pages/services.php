<main>
    <section class="bg-[#9B7855] text-[#222] py-16 lg:py-24">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="flex items-center gap-4 mb-8"><a class="inline-flex items-center text-[#222]/80 hover:text-[#222] transition-colors" href="<?= url('/') ?>"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left w-5 h-5 mr-2">
                        <path d="m12 19-7-7 7-7"></path>
                        <path d="M19 12H5"></path>
                    </svg>Back to Home</a></div>
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl lg:text-5xl font-bold mb-4">Our Amazon KDP Publishing Services</h1>
                <p class="text-xl text-[#222]/90 mb-8 max-w-2xl mx-auto">Everything you need to publish, optimize, and grow your book on Amazon — from cover design to ads management.</p><img src="<?= asset('assets/services-fg-80jeHsi5.svg') ?>" width="553" height="598" alt="Publishing services illustration" class="w-full max-w-md mx-auto mt-4">
            </div>
        </div>
    </section>
    <?php partial('sections/services-grid'); ?>
    <?php partial('sections/pricing'); ?>
</main>
