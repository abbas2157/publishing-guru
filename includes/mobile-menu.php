<?php
$mobileServices = [
    'Niche & Keyword Research' => '/services/niche-keyword-research',
    'Book Formatting' => '/services/book-formatting',
    'Amazon Ads Management' => '/services/amazon-ads',
    'TOC Creation' => '/services/toc-creation',
    'Book Cover Design' => '/services/book-cover-design',
    'A+ Content Design' => '/services/a-content-design',
    'A-to-Z Book Creation Services' => '/services/success-accelerator',
];
?>
<!-- Mobile menu sheet (cloned into <body> by app.js when the menu button is pressed) -->
<template id="mobile-menu-template">
    <div data-state="open" class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" data-aria-hidden="true" aria-hidden="true" style="pointer-events: auto;"></div>
    <div role="dialog" id="mobile-menu" data-state="open" class="fixed z-50 gap-4 p-6 shadow-lg transition ease-in-out data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:duration-300 data-[state=open]:duration-500 inset-y-0 right-0 h-full border-l data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right sm:max-w-sm w-[300px] bg-background" tabindex="-1" style="pointer-events: auto;">
        <nav class="flex flex-col space-y-6 mt-8"><a class="story-link flex items-center space-x-3 text-foreground hover:text-foreground transition-colors group font-bold text-lg" href="<?= url('/') ?>"><img src="<?= icon('home') ?>" alt="Home" class="w-5 h-5"><span>Home</span></a>
            <div><button class="flex items-center justify-between w-full space-x-3 text-foreground hover:text-foreground transition-colors group font-bold text-lg" data-services-toggle>
                    <div class="flex items-center space-x-3"><img src="<?= asset('assets/headerIcon-services-BFA4_xAA.svg') ?>" width="24" height="25" loading="lazy" decoding="async" alt="services" class="w-5 h-5"><span>Services</span></div><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down w-4 h-4 transition-transform ">
                        <path d="m6 9 6 6 6-6"></path>
                    </svg>
                </button>
                <template data-services-list>
                    <div class="mt-3 w-full space-y-3">
<?php foreach ($mobileServices as $name => $href): ?>
                        <a class="story-link block text-foreground/80 hover:text-foreground transition-colors font-medium px-4 py-2 w-full" href="<?= url($href) ?>"><?= e($name) ?></a>
<?php endforeach; ?>
                    </div>
                </template>
            </div><a class="story-link flex items-center space-x-3 text-foreground hover:text-foreground transition-colors group font-bold text-lg" href="<?= url('/contact') ?>"><img src="<?= asset('assets/headerIcon-contact-C9foSzIA.svg') ?>" width="24" height="25" loading="lazy" decoding="async" alt="Contact" class="w-5 h-5"><span>Contact</span></a><a class="story-link flex items-center space-x-3 text-foreground hover:text-foreground transition-colors group font-bold text-lg" href="<?= url('/kdp-flipping-web') ?>"><img src="<?= asset('assets/kdp-icon-CHrYapQL.svg') ?>" width="24" height="24" loading="lazy" decoding="async" alt="KDP Flipping" class="w-5 h-5"><span>KDP Flipping</span></a><a class="story-link flex items-center space-x-3 text-foreground hover:text-foreground transition-colors group font-bold text-lg" href="<?= url('/amazon-kdp-course-book') ?>"><img src="<?= asset('assets/book-course-icon-CLnpCMV3.svg') ?>" width="24" height="24" loading="lazy" decoding="async" alt="Course Book" class="w-5 h-5"><span>Course Book</span></a>
            <div class="pt-4 border-t border-border"><a href="<?= url('/book-call') ?>"><button class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:size-4 [&amp;_svg]:shrink-0 h-10 px-4 py-2 w-full bg-primary text-primary-foreground hover:bg-primary/90">Book a FREE call<img src="<?= asset('assets/Arrow-dH2l6ufH.svg') ?>" width="37" height="9" loading="lazy" decoding="async" alt="Arrow" class="ml-2 w-4 h-4"></button></a></div>
        </nav><button type="button" class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-secondary" data-dialog-close><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x h-4 w-4">
                <path d="M18 6 6 18"></path>
                <path d="m6 6 12 12"></path>
            </svg><span class="sr-only">Close</span></button>
    </div>
</template>
