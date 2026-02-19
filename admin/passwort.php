<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::requireAdmin();
$user = Auth::getUser();
$error = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ungültiger Sicherheits-Token. Bitte erneut versuchen.';
    } else {
        $r = Auth::changePassword(
            $user['email'],
            $_POST['current'] ?? '',
            $_POST['new1'] ?? '',
            $_POST['new2'] ?? ''
        );
        if (!empty($r['ok'])) {
            $success = true;
        } else {
            $error = $r['error'] ?? 'Fehler.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Passwort ändern</title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/admin.css'); ?>">
</head>
<body class="admin-body">
    <header class="admin-header">
        <a href="<?php echo base_url('admin/'); ?>">Admin</a>
        <span><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
        <a href="<?php echo base_url('admin/logout.php'); ?>">Abmelden</a>
    </header>
    <main class="admin-main">
        <h1>Passwort ändern</h1>
        <?php if ($success): ?>
            <p class="success">Passwort wurde geändert.</p>
        <?php else: ?>
            <form method="post" class="admin-form">
                <?php echo csrf_field(); ?>
                <label>Aktuelles Passwort <input type="password" name="current" required></label>
                <label>Neues Passwort (min. 8 Zeichen) <input type="password" name="new1" required minlength="8"></label>
                <label>Neues Passwort bestätigen <input type="password" name="new2" required minlength="8"></label>
                <?php if ($error): ?><p class="err"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
                <button type="submit">Passwort ändern</button>
            </form>
        <?php endif; ?>
        <p><a href="<?php echo base_url('admin/'); ?>">Zurück zur Übersicht</a></p>
    </main>
</body>
</html>
