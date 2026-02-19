<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::requireAdmin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $r = Backup::create('manual');
        if (!empty($r['ok'])) {
            $success = 'Backup erstellt: ' . ($r['id'] ?? '');
        } else {
            $error = $r['error'] ?? 'Backup fehlgeschlagen.';
        }
    } elseif ($action === 'restore' && !empty($_POST['file'])) {
        $r = Backup::restore($_POST['file']);
        if (!empty($r['ok'])) {
            $success = 'Wiederherstellung abgeschlossen.';
        } else {
            $error = $r['error'] ?? 'Restore fehlgeschlagen.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Ungültiger Sicherheits-Token.';
}

$backups = Backup::listBackups();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Backup &amp; Restore</title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/admin.css'); ?>">
</head>
<body class="admin-body">
    <header class="admin-header">
        <a href="<?php echo base_url('admin/'); ?>">Admin</a>
        <a href="<?php echo base_url('admin/backup.php'); ?>">Backup</a>
        <a href="<?php echo base_url('admin/logout.php'); ?>">Abmelden</a>
    </header>
    <main class="admin-main">
        <h1>Backup &amp; Restore</h1>
        <p>Bei jeder Content- oder Medienänderung wird automatisch ein Backup erstellt (max. 20). Restore stellt den gewählten Stand wieder her (Content + Metadaten, Bilder).</p>
        <?php if ($success): ?><p class="success"><?php echo htmlspecialchars($success); ?></p><?php endif; ?>
        <?php if ($error): ?><p class="err"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <form method="post" style="margin-bottom: 1.5rem;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <button type="submit">Backup jetzt erstellen</button>
        </form>

        <h2>Wiederherstellen</h2>
        <?php if (empty($backups)): ?>
            <p>Noch keine Backups vorhanden.</p>
        <?php else: ?>
            <ul class="backup-list">
                <?php foreach ($backups as $f): ?>
                    <?php $info = Backup::getBackupInfo($f); ?>
                    <li>
                        <strong><?php echo htmlspecialchars($f); ?></strong>
                        <?php if ($info): ?>
                            <span class="muted"><?php echo htmlspecialchars($info['created']); ?> (<?php echo htmlspecialchars($info['reason']); ?>)</span>
                        <?php endif; ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Dieses Backup wiederherstellen? Aktueller Inhalt wird überschrieben.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="restore">
                            <input type="hidden" name="file" value="<?php echo htmlspecialchars($f); ?>">
                            <button type="submit">Wiederherstellen</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p><a href="<?php echo base_url('admin/'); ?>">Zurück zur Übersicht</a></p>
    </main>
</body>
</html>
