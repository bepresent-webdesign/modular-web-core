<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::requireSuperadmin();

$user = Auth::getUser();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Superadmin</title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/admin.css'); ?>">
</head>
<body class="admin-body">
    <header class="admin-header">
        <span class="admin-title">Superadmin</span>
        <span><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
        <a href="<?php echo base_url('admin/') ?>">Admin</a>
        <a href="<?php ('admin/backup.php'); ?>">Backup &amp; Restore</a>
        <a href="<?php ('admin/logout.php'); ?>">Abmelden</a>
    </header>
    <main class="admin-main">
        <h1>Superadmin</h1>
        <p>Voller Zugriff: Backup/Restore, alle Admin-Funktionen. Setup kann nur per manueller Entfernung der Lock-Datei <code>data/.setup.lock</code> wieder aktiviert werden.</p>
        <ul class="admin-links">
            <li><a href="<?php ('admin/'); ?>">Admin (Content &amp; Medien)</a></li>
            <li><a href="<?php ('admin/backup.php'); ?>">Backup &amp; Restore</a></li>
        </ul>
    </main>
</body>
</html>
