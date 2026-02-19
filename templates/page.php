<?php
$title = $pageContent['title'] ?? 'Seite';
$pageTitle = $pageTitle ?? $title;
$metaDescription = $metaDescription ?? '';
$current = $page === 'impressum' ? 'impressum' : 'datenschutz';
require __DIR__ . '/layout_header.php';
?>
<main class="legal">
    <div class="page-content"><?php output_body_text($pageContent['content'] ?? '', true); ?></div>
</main>
<?php require __DIR__ . '/layout_footer.php';
