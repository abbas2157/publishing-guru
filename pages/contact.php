<section class="pt-20 sm:pt-24 md:pt-32 pb-6 sm:pb-8 bg-gradient-to-b from-primary/10 to-background">
    <div class="container mx-auto px-4 sm:px-6 text-center">
        <h1 class="text-3xl sm:text-4xl lg:text-6xl font-bold text-foreground mb-4 sm:mb-6">Get in Touch</h1>
        <p class="text-base sm:text-lg text-foreground max-w-2xl mx-auto px-2 sm:px-0">Ready to start your KDP publishing journey? We're here to help you every step of the way.</p>
    </div>
</section>
<section class="pt-6 sm:pt-8 pb-12 sm:pb-16">
    <div class="container mx-auto px-4 sm:px-6">
        <div class="grid lg:grid-cols-2 gap-8 sm:gap-12 max-w-6xl mx-auto">
            <div>
                <div class="rounded-lg border bg-card text-card-foreground shadow-lg">
                    <div class="flex flex-col space-y-1.5 p-6">
                        <h3 class="tracking-tight text-2xl font-bold text-left">Let's Connect</h3>
                        <p class="text-foreground text-left p-0 m-0">Fill out the form below and we'll get back to you within 24 hours.</p>
                    </div>
                    <div class="p-6 pt-0">
<?php
$services = [
    'niche-keyword' => 'Niche & Keyword Research',
    'book-formatting' => 'Book Formatting',
    'amazon-ads' => 'Amazon Ads Management',
    'toc-creation' => 'Professional TOC Creation',
    'book-cover' => 'Book Cover Design',
    'a-content' => 'A+ Content Design',
    'success-accelerator' => 'Success Accelerator',
    'kdp-flipping-web' => 'KDP Flipping Web',
    'general' => 'General Inquiry',
];
// Pre-select a service from links like /contact?service=amazon-ads
$selectedService = isset($_GET['service'], $services[$_GET['service']]) ? $_GET['service'] : '';
?>
                        <form class="space-y-6" data-contact-form="contact">
                            <div class="space-y-2"><label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70" for="service">Select service that you want to inquire about *</label><button type="button" role="combobox" aria-controls="select-content" aria-expanded="false" aria-autocomplete="none" dir="ltr" data-state="closed"<?= $selectedService ? '' : ' data-placeholder=""' ?> class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 [&amp;&gt;span]:line-clamp-1"><span style="pointer-events: none;"><?= $selectedService ? e($services[$selectedService]) : 'Choose a service...' ?></span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down h-4 w-4 opacity-50" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6"></path>
                                    </svg></button><select aria-hidden="true" tabindex="-1" data-placeholder-text="Choose a service..." style="position: absolute; border: 0px; width: 1px; height: 1px; padding: 0px; margin: -1px; overflow: hidden; clip: rect(0px, 0px, 0px, 0px); white-space: nowrap; overflow-wrap: normal;">
<?php foreach ($services as $value => $label): ?>
                                    <option value="<?= e($value) ?>"<?= $value === $selectedService ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
                                </select></div>
                            <div data-orientation="horizontal" role="none" class="shrink-0 bg-border h-[1px] w-full my-6"></div>
                            <div class="space-y-2"><label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70" for="name">Full Name *</label><input type="text" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm" id="name" name="name" placeholder="Your full name" required="" value=""></div>
                            <div class="space-y-2"><label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70" for="email">Email Address *</label><input type="email" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm" id="email" name="email" placeholder="your.email@example.com" required="" value=""></div>
                            <div class="space-y-2"><label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70" for="phone">Phone Number</label><input type="tel" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm" id="phone" name="phone" placeholder="+1 (555) 123-4567" value=""></div>
                            <div class="space-y-2"><label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70" for="message">Message *</label><textarea class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 min-h-[120px]" id="message" name="message" placeholder="Tell us about your publishing goals and how we can help..." required=""></textarea></div><button class="inline-flex items-center justify-center gap-2 whitespace-nowrap font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg]:size-4 [&amp;_svg]:shrink-0 bg-primary text-primary-foreground hover:bg-primary/90 h-11 rounded-md px-8 w-full text-sm sm:text-base" type="submit">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="flex flex-col justify-center space-y-8 h-full">
                <div class=""><img src="<?= asset('assets/contact-illustration.webp') ?>" width="808" height="844" loading="lazy" decoding="async" alt="Hands typing on a laptop next to a cup of coffee" class="w-3/5"></div>
                <div class="">
                    <div class="flex flex-col space-y-1.5 p-6">
                        <h3 class="tracking-tight text-2xl font-bold">Follow Us</h3>
                        <p class="text-foreground text-left">Stay connected and get the latest publishing tips and updates.</p>
                    </div>
                    <div class="p-6 pt-0">
                        <div class="grid grid-cols-2 gap-4"><a href="https://www.instagram.com/publishinguru_kdp?igsh=MWQ4c2FoM29vZGpwZw==" target="_blank" rel="noopener noreferrer" class="flex items-center space-x-3 p-4 rounded-lg border border-border hover:border-primary/50 transition-all group hover:text-pink-500"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-instagram w-6 h-6 transition-colors">
                                    <rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect>
                                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                    <line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line>
                                </svg><span class="font-medium group-hover:text-current transition-colors">Instagram</span></a><a href="https://www.facebook.com/share/1CkckwvVAA/" target="_blank" rel="noopener noreferrer" class="flex items-center space-x-3 p-4 rounded-lg border border-border hover:border-primary/50 transition-all group hover:text-blue-600"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-facebook w-6 h-6 transition-colors">
                                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                                </svg><span class="font-medium group-hover:text-current transition-colors">Facebook</span></a><a href="https://youtube.com" target="_blank" rel="noopener noreferrer" class="flex items-center space-x-3 p-4 rounded-lg border border-border hover:border-primary/50 transition-all group hover:text-red-600"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-youtube w-6 h-6 transition-colors">
                                    <path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"></path>
                                    <path d="m10 15 5-3-5-3z"></path>
                                </svg><span class="font-medium group-hover:text-current transition-colors">YouTube</span></a><a href="https://www.linkedin.com/company/publishinguru/" target="_blank" rel="noopener noreferrer" class="flex items-center space-x-3 p-4 rounded-lg border border-border hover:border-primary/50 transition-all group hover:text-blue-700"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-linkedin w-6 h-6 transition-colors">
                                    <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path>
                                    <rect width="4" height="12" x="2" y="9"></rect>
                                    <circle cx="4" cy="4" r="2"></circle>
                                </svg><span class="font-medium group-hover:text-current transition-colors">LinkedIn</span></a></div>
                    </div>
                </div><a href="tel:+17208034953" class="flex items-center space-x-3 p-4 rounded-lg border border-border hover:border-primary/50 transition-all group hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone w-6 h-6 transition-colors">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg><span class="font-medium text-lg group-hover:text-current transition-colors">+1 (720) 803-4953</span></a>
            </div>
        </div>
    </div>
</section>
<?php partial('sections/how-it-works'); ?>
<?php partial('sections/cta-band'); ?>
<?php partial('sections/launch-journey'); ?>
