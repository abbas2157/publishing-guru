<?php
/**
 * Route table: URL path => page definition.
 *
 *  view        template under pages/ (without .php)
 *  name        short page name (breadcrumbs, sitemap)
 *  parent      parent route for breadcrumbs (defaults to '/')
 *  title       <title>
 *  description meta description (defaults to DEFAULT_DESCRIPTION)
 *  og_image    social share image (defaults to DEFAULT_OG_IMAGE)
 *  wrapper     classes on the page wrapper div
 *  track       Facebook Pixel ViewContent payload fired on page load
 *  schema      JSON-LD block(s) rendered in <head>
 */

$organizationSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    '@id' => SITE_URL . '/#organization',
    'name' => 'Publishing Guru',
    'url' => SITE_URL . '/',
    'logo' => SITE_URL . '/assets/publishing-guru-logo.png',
    'image' => SITE_URL . '/' . DEFAULT_OG_IMAGE,
    'description' => 'Publishing Guru is a US-focused professional service agency specializing in Amazon KDP book writing and publishing solutions. We help authors and publishers with end-to-end KDP services including profitable niche research, book formatting, cover design, A+ content creation, Amazon PPC ads management, and complete book creation from idea to launch. Since 2020, we have supported hundreds of clients with ethical, scalable, and compliant Kindle Direct Publishing solutions.',
    'foundingDate' => '2020',
    'areaServed' => ['@type' => 'Country', 'name' => 'United States'],
    'contactPoint' => [
        '@type' => 'ContactPoint',
        'telephone' => '+1-720-803-4953',
        'contactType' => 'customer support',
        'availableLanguage' => 'English',
        'url' => SITE_URL . '/contact',
    ],
    'sameAs' => [
        'https://www.instagram.com/publishinguru_kdp',
        'https://www.facebook.com/publishinguru',
        'https://www.linkedin.com/company/publishinguru/',
    ],
];

$websiteSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    '@id' => SITE_URL . '/#website',
    'name' => SITE_NAME,
    'url' => SITE_URL . '/',
    'publisher' => ['@id' => SITE_URL . '/#organization'],
    'inLanguage' => 'en-US',
];

$servicePackage = fn(string $name) => ['content_name' => $name, 'content_category' => 'Service Package'];

/** Route definition (with Service JSON-LD) for a service detail page. */
$service = fn(string $path, string $name, string $title, string $description, string $trackName = '') => [
    'view' => ltrim($path, '/'),
    'name' => $name,
    'parent' => '/services',
    'title' => $title,
    'description' => $description,
    'track' => $servicePackage($trackName ?: $name),
    'schema' => ['service-schema' => [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => $name,
        'serviceType' => $name,
        'description' => $description,
        'url' => SITE_URL . $path,
        'provider' => ['@type' => 'Organization', '@id' => SITE_URL . '/#organization', 'name' => SITE_NAME, 'url' => SITE_URL . '/'],
        'areaServed' => ['@type' => 'Country', 'name' => 'United States'],
    ]],
];

return [
    '/' => [
        'view' => 'home',
        'preload' => 'hero-bg.webp',
        'name' => 'Home',
        'title' => 'Amazon Book Publishing Services | KDP Book Publisher USA',
        'description' => 'Done-for-you Amazon book publishing services to help authors write, publish, optimize, and scale compliant KDP books. Book a free strategy call today.',
        'schema' => ['home-organization-schema' => $organizationSchema, 'website-schema' => $websiteSchema],
    ],
    '/services' => [
        'view' => 'services',
        'name' => 'Services',
        'title' => 'Amazon KDP Publishing Services | Publishing Guru',
        'description' => 'Explore our full range of Amazon KDP publishing services including book creation, niche research, formatting, cover design, A+ content, and PPC ads management.',
    ],
    '/services/niche-keyword-research' => $service('/services/niche-keyword-research', 'Niche & Keyword Research',
        'KDP Niche & Keyword Research Service | Publishing Guru',
        'Find profitable, low-competition Amazon KDP niches and keywords tailored to your books. Data-driven research to help you dominate your market segment.'),
    '/services/book-formatting' => $service('/services/book-formatting', 'Book Formatting',
        'Kindle & Paperback Book Formatting Service | Publishing Guru',
        'Professional Kindle eBook and paperback formatting that meets Amazon KDP standards, so your book looks polished on every device and in print.'),
    '/services/amazon-ads' => $service('/services/amazon-ads', 'Amazon Ads Management',
        'Amazon Ads (PPC) Management for Authors | Publishing Guru',
        'Complete Amazon PPC strategy and ads management for KDP authors, built to maximize your book sales and return on ad spend.'),
    '/services/toc-creation' => $service('/services/toc-creation', 'TOC Creation',
        'Professional Table of Contents Creation | Publishing Guru',
        'Professionally designed Table of Contents for Kindle and print books that improves reader navigation and the overall reading experience.'),
    '/services/book-cover-design' => $service('/services/book-cover-design', 'Book Cover Design',
        'KDP Book Cover Design Service | Publishing Guru',
        'Eye-catching book cover design for eBooks and paperbacks that captures attention and drives sales. Two revisions included.'),
    '/services/a-content-design' => $service('/services/a-content-design', 'A+ Content Design',
        'Amazon A+ Content Design for Books | Publishing Guru',
        'Premium Amazon A+ Content design that enhances your book detail page and boosts conversion rates. Two revisions included.'),
    '/services/success-accelerator' => $service('/services/success-accelerator', 'A-to-Z Book Creation Services',
        'A-to-Z Book Creation Services | Publishing Guru',
        'Fast-track your Amazon KDP publishing success with a complete done-for-you book creation program and intensive support to build a thriving book business.',
        'Success Accelerator'),
    '/contact' => [
        'view' => 'contact',
        'name' => 'Contact',
        'title' => 'Contact Us | Publishing Guru',
        'description' => 'Get in touch with Publishing Guru about your Amazon KDP project. Ask about our publishing services and get expert help at every step of your journey.',
        'wrapper' => 'min-h-screen bg-background',
    ],
    '/book-call' => [
        'view' => 'book-call',
        'name' => 'Book a Call',
        'title' => 'Book a Free KDP Strategy Call | Publishing Guru',
        'description' => 'Schedule a free consultation with our Amazon KDP publishing experts to discuss your book project and discover how we can help you succeed.',
        'track' => ['content_name' => 'Book Free Strategy Call', 'content_category' => 'Consultation'],
    ],
    '/kdp-flipping-web' => [
        'view' => 'kdp-flipping-web',
        'name' => 'Sell Your KDP Business',
        'title' => 'Sell Your Amazon KDP Business | Publishing Guru',
        'description' => 'Turn your publishing work into a profitable exit. Sell your Amazon KDP business with expert guidance from Publishing Guru.',
    ],
    '/amazon-kdp-course-book' => [
        'view' => 'amazon-kdp-course-book',
        'preload' => 'assets/kdp-banner-DbGG8k1i.webp',
        'name' => 'Amazon KDP Course Book',
        'title' => 'Amazon KDP Course Book – KDP Made Simple | Publishing Guru',
        'description' => 'KDP Made Simple: a 30-minute, step-by-step Amazon KDP course book that teaches the complete publishing system. $35 one-time payment.',
        'wrapper' => 'min-h-screen bg-background',
        'track' => ['content_name' => 'Amazon KDP Course Book', 'content_category' => 'Course', 'value' => 35, 'currency' => 'USD'],
        'og_image' => 'assets/kdp-banner-DbGG8k1i.jpg',
        'schema' => ['product-schema' => [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'KDP Made Simple – Amazon KDP Course Book',
            'description' => 'A 30-minute guide to mastering the science of profitable publishing on Amazon KDP.',
            'image' => SITE_URL . '/assets/kdp-banner-DbGG8k1i.jpg',
            'brand' => ['@type' => 'Brand', 'name' => SITE_NAME],
            'offers' => [
                '@type' => 'Offer',
                'price' => '35.00',
                'priceCurrency' => 'USD',
                'availability' => 'https://schema.org/InStock',
                'url' => SITE_URL . '/amazon-kdp-course-book',
            ],
        ]],
    ],
];
