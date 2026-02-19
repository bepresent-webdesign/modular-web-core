<?php
/**
 * Frontend: Home, Impressum, Datenschutz. Content aus JSON.
 */
require_once __DIR__ . '/lib/bootstrap.php';

$page = $_GET['page'] ?? 'home';
$allowed = ['home', 'impressum', 'datenschutz'];
if (!in_array($page, $allowed, true)) {
    $page = 'home';
}

$site = Content::getSite();
$footer = $site['footer'] ?? [];

if ($page === 'home') {
    $hero = $site['hero'] ?? [];
    $features = $site['features'] ?? [];
    $about = $site['about'] ?? [];
    $contact = $site['contact'] ?? [];
    require __DIR__ . '/templates/home.php';
} else {
    $pageContent = $page === 'impressum' ? Content::getImpressum() : Content::getDatenschutz();
    $pageTitle = $pageContent['title'] ?? ($page === 'impressum' ? 'Impressum' : 'Datenschutz');
    $company = $footer['company_name'] ?? 'Musterbetrieb';
    $metaDescription = $page === 'impressum'
        ? ("Impressum von {$company}. Kontaktdaten und rechtliche Angaben.")
        : ("Datenschutzerklärung von {$company}. Informationen zum Umgang mit Ihren Daten.");
    $hero = [
        'title' => $pageTitle,
        'subtitle' => '',
        'claim' => '',
        'image' => $site['hero']['image'] ?? '',
    ];
    require __DIR__ . '/templates/page.php';
}
