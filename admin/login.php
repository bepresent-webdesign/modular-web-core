<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::startSession();
if (Auth::isLoggedIn()) {
    header('Location: ' . base_url('admin/'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $r = Auth::login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if (!empty($r['ok'])) {
        header('Location: ' . base_url('admin/'));
        exit;
    }
    $error = $r['error'] ?? 'Anmeldung fehlgeschlagen.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Ungültiger Sicherheits-Token.';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/admin.css'); ?>">
</head>
<body class="login-page">
    <div class="login-box">
        <h1>Admin-Login</h1>
        <form method="post" action="">
            <?php echo csrf_field(); ?>
            <label>E-Mail <input type="email" name="email" required autofocus value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></label>
            <label>Passwort <input type="password" name="password" required></label>
            <?php if ($error): ?><p class="err"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
            <button type="submit">Anmelden</button>
        </form>
        <p><a href="<?php echo base_url(); ?>">Zur Website</a></p>
    </div>
</body>
</html>