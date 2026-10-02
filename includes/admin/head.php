<?php /** Shared <head> for admin pages. Expects $pageTitle. */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title><?= e($pageTitle) ?> · Publishing Guru Admin</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('assets/favicon-32x32-DOg4SlHu.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Open+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
<?php if (!empty($extraHead)): ?>
    <?= $extraHead ?>
<?php endif; ?>
</head>
