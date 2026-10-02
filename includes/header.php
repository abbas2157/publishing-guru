<header class="md:fixed top-0 left-0 right-0 w-full py-2 md:py-4 bg-background/95 md:bg-transparent backdrop-blur-md z-50">
    <style>
        nav ul li {
          margin: 0 !important;
        }
        nav ul li > button{
          background: transparent !important; 
        }
          .story-link::after{
              height: 2.5rem;
              background-color: #FFEB3B;
              border-radius: 10px;
              z-index: -1;
          }
      </style>
    <div class="container mx-auto px-4 flex items-center justify-between">
        <div class="flex items-center space-x-2"><a href="<?= url('/') ?>"><img src="<?= asset('assets/publishing-guru-logo.webp') ?>" width="520" height="216" alt="Publishing Guru Logo" class="h-auto w-[100px] sm:w-[130px] lg:w-[180px] xl:w-[220px]"></a></div>
        <nav aria-label="Main" data-orientation="horizontal" dir="ltr" class="relative z-10 max-w-max flex-1 items-center justify-center hidden lg:flex">
            <div style="position: relative;">
                <ul data-orientation="horizontal" class="group flex flex-1 list-none items-center justify-center space-x-1 xl:space-x-4" dir="ltr">
                    <li><a class="story-link flex items-center space-x-1 text-foreground hover:text-foreground transition-colors group font-medium px-3 py-2" href="<?= url('/') ?>"><img src="<?= icon('home') ?>" alt="Home" class="w-5 h-5"><span>Home</span></a></li>
                    <li><a class="story-link flex items-center space-x-1 text-foreground hover:text-foreground transition-colors group font-medium px-3 py-2" href="<?= url('/services') ?>"><img src="<?= asset('assets/headerIcon-services-BFA4_xAA.svg') ?>" width="24" height="25" alt="Services" class="w-5 h-5"><span>Services</span></a></li>
                    <li><a class="story-link flex items-center space-x-2 text-foreground hover:text-foreground transition-colors group font-medium px-3 py-2" href="<?= url('/contact') ?>"><img src="<?= asset('assets/headerIcon-contact-C9foSzIA.svg') ?>" width="24" height="25" alt="Contact" class="w-5 h-5"><span>Contact</span></a></li>
                    <li><a class="story-link flex items-center space-x-2 text-foreground hover:text-foreground transition-colors group font-medium px-3 py-2" href="<?= url('/kdp-flipping-web') ?>"><img src="<?= asset('assets/kdp-icon-CHrYapQL.svg') ?>" width="24" height="24" alt="KDP Flipping" class="w-5 h-5"><span>KDP Flipping</span></a></li>
                    <li><a class="story-link flex items-center space-x-2 text-foreground hover:text-foreground transition-colors group font-medium px-3 py-2" href="<?= url('/amazon-kdp-course-book') ?>"><img src="<?= asset('assets/book-course-icon-CLnpCMV3.svg') ?>" width="24" height="24" alt="Course Book" class="w-5 h-5"><span>Course Book</span></a></li>
                    <li><a class="story-link flex items-center space-x-2 text-foreground hover:text-foreground transition-colors group font-medium px-3 py-2" href="<?= url('/blog') ?>"><img src="<?= asset('assets/headerIcon-blog.svg') ?>" width="24" height="25" alt="Blog" class="w-5 h-5"><span>Blog</span></a></li>
                </ul>
            </div>
            <div class="absolute left-0 top-full flex justify-center"></div>
        </nav>
        <div class="hidden lg:flex"><a href="<?= url('/book-call') ?>"><button class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:size-4 [&amp;_svg]:shrink-0 h-10 px-4 py-2 bg-primary text-primary-foreground hover:bg-primary/90">Book a FREE call<img src="<?= asset('assets/Arrow-dH2l6ufH.svg') ?>" width="37" height="9" alt="Arrow" class="ml-2 w-4 h-4"></button></a></div>
        <div class="flex items-center space-x-4 lg:hidden"><a href="<?= url('/book-call') ?>"><button class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:size-4 [&amp;_svg]:shrink-0 h-10 px-4 py-2 bg-primary text-primary-foreground hover:bg-primary/90">Book a FREE call<img src="<?= asset('assets/Arrow-dH2l6ufH.svg') ?>" width="37" height="9" alt="Arrow" class="ml-2 w-4 h-4"></button></a><button class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:size-4 [&amp;_svg]:shrink-0 hover:text-accent-foreground h-10 w-10 bg-yellow-400 hover:bg-yellow-500 text-black" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="mobile-menu" data-state="closed"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-menu h-6 w-6">
                    <line x1="4" x2="20" y1="12" y2="12"></line>
                    <line x1="4" x2="20" y1="6" y2="6"></line>
                    <line x1="4" x2="20" y1="18" y2="18"></line>
                </svg><span class="sr-only">Toggle menu</span></button></div>
    </div>
    <?php partial('mobile-menu'); ?>
</header>
