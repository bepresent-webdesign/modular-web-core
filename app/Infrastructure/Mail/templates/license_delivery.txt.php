<?php
/** @var string $productName */
/** @var string $licenseType */
/** @var string $licenseKey */
/** @var string $downloadUrl */
/** @var int $maxDownloads */
/** @var int $expiryDays */
/** @var string $supportContact */
?>
Hallo,

Ihre Lizenz und der Download-Link für <?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?> sind bereit.

Lizenztyp: <?= htmlspecialchars($licenseType, ENT_QUOTES, 'UTF-8') ?>

Ihr Lizenzschlüssel:
<?= htmlspecialchars($licenseKey, ENT_QUOTES, 'UTF-8') ?>

Download-Link (<?= (int) $expiryDays ?> Tage gültig, maximal <?= (int) $maxDownloads ?> Downloads):
<?= $downloadUrl ?>

Hinweis: Bitte bewahren Sie diese E-Mail und den Lizenzschlüssel sicher auf.

Bei Fragen: <?= htmlspecialchars($supportContact, ENT_QUOTES, 'UTF-8') ?>
