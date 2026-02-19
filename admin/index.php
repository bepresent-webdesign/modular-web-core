<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::requireAdmin();
$user = Auth::getUser();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin – Inhalte verwalten</title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/admin.css'); ?>">
</head>
<body class="admin-body">
    <header class="admin-header">
        <span class="admin-title">Admin</span>
        <span class="admin-user"><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
        <a href="<?php echo base_url('admin/passwort.php'); ?>">Passwort ändern</a>
        <a href="<?php echo base_url('admin/backup.php'); ?>">Backup &amp; Restore</a>
        <a href="<?php echo base_url('admin/logout.php'); ?>">Abmelden</a>
    </header>
    <main class="admin-main">
        <h1>Inhalte verwalten</h1>
        <ul class="admin-links">
            <li><a href="<?php echo base_url('admin/content.php?page=site'); ?>">Startseite (Hero, Bilder, Leistungen, Über uns, Kontakt, Footer)</a></li>
            <li><a href="<?php echo base_url('admin/content.php?page=impressum'); ?>">Impressum</a></li>
            <li><a href="<?php echo base_url('admin/content.php?page=datenschutz'); ?>">Datenschutz</a></li>
        </ul>
    </main>
</body>
</html>
