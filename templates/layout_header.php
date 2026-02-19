<?php
$baseRef = ($current ?? '') === 'home' ? '' : 'index.php';
$homeHref = $baseRef === '' ? '#' : 'index.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    <title><?php echo htmlspecialchars($pageTitle ?? $title ?? 'Musterbetrieb – Service & Qualität'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription ?? 'Ihr zuverlässiger Partner für Service, Qualität und fachgerechte Leistungen.'); ?>">
    <link rel="canonical" href="https://<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle ?? $title ?? 'Musterbetrieb'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription ?? ''); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?>">
    <link rel="stylesheet" href="assets/css/site.css?v=2">
</head>
<body>
<header class="site-header<?php echo (isset($hero) && !empty($hero['title'])) ? ' header-overlay' : ''; ?>" id="site-header">
    <div class="header-bar">
    <div class="header-inner">
        <a href="<?php echo $homeHref; ?>" class="logo"><?php echo htmlspecialchars($footer['company_name'] ?? 'Startseite'); ?></a>
        <button type="button" class="nav-toggle" aria-label="Menü öffnen" aria-expanded="false"></button>
        <nav class="nav" aria-hidden="true">
            <a href="<?php echo $homeHref; ?>"<?php echo ($current ?? '') === 'home' ? ' class="active"' : ''; ?>>Start</a>
            <a href="<?php echo $baseRef; ?>#leistungen">Leistungen</a>
            <a href="<?php echo $baseRef; ?>#ueber-mich">Über mich</a>
            <a href="<?php echo $baseRef; ?>#kontakt">Kontakt</a>
            <a href="<?php echo htmlspecialchars(page_url('impressum')); ?>"<?php echo ($current ?? '') === 'impressum' ? ' class="active"' : ''; ?>>Impressum</a>
            <a href="<?php echo htmlspecialchars(page_url('datenschutz')); ?>"<?php echo ($current ?? '') === 'datenschutz' ? ' class="active"' : ''; ?>>Datenschutz</a>
        </nav>
    </div>
    </div>
<?php if (isset($hero) && !empty($hero['title'])): ?>
    <?php $headerHeroImage = !empty($hero['image']) ? asset_url($hero['image']) : asset_url('assets/img/hero.webp'); ?>
    <div class="header-hero" style="background-image: url('<?php echo htmlspecialchars($headerHeroImage); ?>');">
        <div class="header-hero-overlay"></div>
        <div class="header-hero-inner">
            <h1 class="header-hero-title"><?php echo htmlspecialchars($hero['title'] ?? 'Willkommen'); ?></h1>
            <p class="header-hero-subline"><?php echo htmlspecialchars($hero['subtitle'] ?? 'Überschrift für die Startseite'); ?></p>
            <?php if (!empty($hero['claim'])): ?><p class="header-hero-claim"><?php echo htmlspecialchars($hero['claim']); ?></p><?php endif; ?>
        </div>
    </div>
<?php endif; ?>
</header>
