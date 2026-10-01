<?php
/**
 * Site configuration.
 */

// Base path the site is served from. Detected automatically so the same code
// works at a domain root (https://www.publishinguru.com/) and in a sub-folder
// (http://localhost/publishing-guru/). Override here if detection is wrong.
define('BASE_PATH', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'));

// Canonical production origin (used for canonical links and structured data).
define('SITE_URL', 'https://www.publishinguru.com');

define('SITE_NAME', 'Publishing Guru');

// Default meta used by pages that don't set their own.
define('DEFAULT_TITLE', 'Publishing Guru - Build & Scale Your KDP Publishing Business');
define('DEFAULT_DESCRIPTION', 'Learn proven strategies to build, launch & scale your KDP publishing business with confidence. Expert guidance, training & tools from Publishing Guru.');
// Social share image (1200x630), relative to the site root.
define('DEFAULT_OG_IMAGE', 'assets/og-image.jpg');

// Facebook Pixel.
define('FB_PIXEL_ID', '1365172541683278');

// Supabase backend (Edge Functions: send-contact-email, create-checkout-session, verify-purchase).
// The anon key is a public, browser-safe key — the same one the original site ships.
define('SUPABASE_URL', 'https://zlpxtvozzuaypgxpnhqo.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InpscHh0dm96enVheXBneHBuaHFvIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTgwOTczMjIsImV4cCI6MjA3MzY3MzMyMn0.zcfiIpNc3R05pKL3zPV37rZQ0pnInMB2LaGejpEy3mc');

define('CALENDLY_URL', 'https://calendly.com/publishinguru/free-consultancy-amazon-kdp');
define('WHATSAPP_NUMBER', '17208034953');
