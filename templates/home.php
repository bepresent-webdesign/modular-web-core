<?php
$title = $hero['title'] ?? 'Willkommen';
$pageTitle = $pageTitle ?? $title;
$metaDescription = $metaDescription ?? 'Ihr zuverlässiger Partner für Service, Qualität und fachgerechte Leistungen.';
$current = 'home';
$detailImage = asset_url(!empty($site['images']['details_image']) ? $site['images']['details_image'] : 'assets/img/detail.webp');
$portraitImage = asset_url(!empty($about['image']) ? $about['image'] : 'assets/img/portrait.webp');
$contactImage = asset_url(!empty($site['images']['contact_image']) ? $site['images']['contact_image'] : 'assets/img/contact.webp');

$defaultFeatures = [
    ['title' => 'Reparaturen & Instandhaltung', 'text' => 'Schnelle Hilfe bei Reparaturen und Instandhaltung – damit alles wieder läuft.'],
    ['title' => 'Renovierung & Montage', 'text' => 'Von der Renovierung bis zur Montage: sauber und termingerecht.'],
    ['title' => 'Service & Beratung', 'text' => 'Persönliche Beratung und Service – auf Sie zugeschnitten.'],
];
$features = !empty($features) ? $features : $defaultFeatures;

require __DIR__ . '/layout_header.php';
?>
<main class="home-main">
    <!-- 2) LEISTUNGEN: Kachel Bild + Text nebeneinander -->
    <section class="block block--leistungen" id="leistungen">
        <div class="block-inner block-inner--img-left">
            <div class="block-media">
                <img src="<?php echo htmlspecialchars($detailImage); ?>" alt="" loading="lazy">
            </div>
            <div class="block-content">
                <h2 class="block-title"><?php echo htmlspecialchars($site['services_section_title'] ?? 'Leistungen'); ?></h2>
                <div class="block-text">
                    <?php foreach ($features as $f): ?>
                        <p><strong><?php echo htmlspecialchars($f['title'] ?? ''); ?></strong><br><?php output_body_text($f['text'] ?? ''); ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- 3) ÜBER UNS: Bild + Text nebeneinander, abwechselnd (Bild rechts) -->
    <section class="block block--ueber-uns" id="ueber-mich">
        <div class="block-inner block-inner--img-right">
            <div class="block-media">
                <img src="<?php echo htmlspecialchars($portraitImage); ?>" alt="" loading="lazy">
            </div>
            <div class="block-content">
                <h2 class="block-title"><?php echo htmlspecialchars($about['title'] ?? 'Über uns'); ?></h2>
                <div class="block-text ueber-uns-text"><?php output_body_text($about['text'] ?? 'Hier stellen Sie sich vor.'); ?></div>
            </div>
        </div>
    </section>

    <!-- 4) KONTAKT: Kachel Bild + Text -->
    <section class="block block--kontakt" id="kontakt">
        <div class="block-inner block-inner--img-left">
            <div class="block-media">
                <img src="<?php echo htmlspecialchars($contactImage); ?>" alt="" loading="lazy">
            </div>
            <div class="block-content">
                <h2 class="block-title"><?php echo htmlspecialchars($contact['title'] ?? 'Kontakt'); ?></h2>
                <p class="block-intro"><?php output_body_text($contact['text'] ?? 'So erreichen Sie uns.'); ?></p>
                <?php if (!empty($contact['email'])): ?>
                    <p><a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" class="block-link"><?php echo htmlspecialchars($contact['email']); ?></a></p>
                <?php endif; ?>
                <?php if (!empty($contact['phone'])): ?>
                    <p><a href="tel:<?php echo preg_replace('/[^+0-9]/', '', $contact['phone']); ?>" class="block-link"><?php echo htmlspecialchars($contact['phone']); ?></a></p>
                <?php endif; ?>
                <?php if (empty($contact['email']) && empty($contact['phone'])): ?>
                    <p class="block-fallback">E-Mail und Telefon im Admin unter Startseite/Kontakt hinterlegen.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/layout_footer.php';
