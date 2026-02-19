<?php
/**
 * Setup-Wizard: Admin anlegen, Ordner anlegen, Default-Content, Lock.
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if (is_setup_locked()) {
    header('HTTP/1.1 403 Forbidden');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Setup deaktiviert</title></head><body><p>Setup wurde bereits ausgeführt. Zugriff gesperrt.</p></body></html>';
    exit;
}

$step = (int)($_GET['step'] ?? 1);
$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        if ($email === '') {
            $error = 'Bitte E-Mail eingeben.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige E-Mail-Adresse.';
        } elseif (strlen($password) < 8) {
            $error = 'Passwort mindestens 8 Zeichen.';
        } elseif ($password !== $password2) {
            $error = 'Passwörter stimmen nicht überein.';
        } else {
            ensure_dirs();
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            $users = [
                'admin' => ['email' => $email, 'hash' => $hash],
            ];
            if (!json_write(DATA_DIR . '/users.json', $users)) {
                $error = 'Speichern fehlgeschlagen. Bitte Schreibrechte für data/ prüfen.';
            } else {
                ensure_dirs();
                selfCreateDefaultContent();
                file_put_contents(SETUP_LOCK_FILE, date('c') . "\nSetup abgeschlossen.\n");
                $done = true;
            }
        }
    }
}

function selfCreateDefaultContent(): void {
    $defaultContent = [
        'hero' => [
            'hero_image' => '',
            'hero_title' => 'Willkommen',
            'hero_subtitle' => 'Ihre Überschrift für die Startseite.',
        ],
        'services' => [
            'services_section_title' => 'Leistungen',
            'services_items' => [
                ['title' => 'Leistung 1', 'short_text' => 'Kurzbeschreibung.'],
                ['title' => 'Leistung 2', 'short_text' => 'Kurzbeschreibung.'],
                ['title' => 'Leistung 3', 'short_text' => 'Kurzbeschreibung.'],
            ],
        ],
        'about' => [
            'about_title' => 'Über uns',
            'about_text' => 'Hier stellen Sie sich vor.',
        ],
        'contact' => [
            'contact_title' => 'Kontakt',
            'contact_text' => 'So erreichen Sie uns.',
            'contact_address' => [
                'company' => 'Max Mustermann',
                'addition' => '',
                'street' => 'Musterstraße 1',
                'zip' => '12345',
                'city' => 'Musterstadt',
            ],
            'contact_email' => 'info@example.com',
            'contact_phone_landline' => '+49 123 456789',
            'contact_fax' => '',
            'contact_mobile' => '',
        ],
        'footer' => ['footer_note' => ''],
        'images' => [
            'hero_image' => '',
            'details_image' => '',
            'portrait_image' => '',
            'contact_image' => '',
        ],
    ];
    json_write(CONTENT_DIR . '/content.json', $defaultContent);
    json_write(CONTENT_DIR . '/impressum.json', [
        'title' => 'Impressum',
        'content' => '<p><strong>Angaben gemäß § 5 TMG</strong></p><p>Max Mustermann<br>Musterstraße 1<br>12345 Musterstadt</p><p>Telefon: +49 123 456789<br>E-Mail: info@example.com</p><p><strong>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV:</strong><br>Max Mustermann, Musterstraße 1, 12345 Musterstadt</p>',
    ]);
    json_write(CONTENT_DIR . '/datenschutz.json', [
        'title' => 'Datenschutz',
        'content' => '<p>Verantwortlicher: Max Mustermann, Musterstraße 1, 12345 Musterstadt.</p><p>Diese Website erhebt personenbezogene Daten nur im technisch notwendigen Umfang (z.&nbsp;B. Session beim Admin-Login). Es werden keine Tracking-Dienste eingesetzt.</p><p>Sie haben das Recht auf Auskunft, Berichtigung und Löschung Ihrer Daten. Kontakt: info@example.com</p>',
    ]);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup – Universal Core Mini-CMS</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; max-width: 420px; margin: 2rem auto; padding: 0 1rem; }
        h1 { font-size: 1.25rem; }
        label { display: block; margin-top: 0.75rem; }
        input[type=email], input[type=password] { width: 100%; padding: 0.5rem; margin-top: 0.25rem; }
        .err { color: #c00; margin-top: 0.5rem; }
        button { margin-top: 1rem; padding: 0.5rem 1rem; background: #333; color: #fff; border: 0; cursor: pointer; }
        .success { color: #080; margin: 1rem 0; }
        a { color: #06c; }
    </style>
</head>
<body>
    <h1>Setup – Universal Core Mini-CMS</h1>
    <?php if ($done): ?>
        <p class="success">Installation abgeschlossen. Setup ist jetzt deaktiviert.</p>
        <p><a href="<?php echo base_url('admin/'); ?>">Zum Admin-Bereich</a> | <a href="<?php echo base_url(); ?>">Zur Website</a></p>
    <?php elseif ($step === 1): ?>
        <p>Admin-Benutzer anlegen (E-Mail + Passwort).</p>
        <form method="post" action="">
            <label>E-Mail <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></label>
            <label>Passwort (min. 8 Zeichen) <input type="password" name="password" required minlength="8"></label>
            <label>Passwort bestätigen <input type="password" name="password2" required minlength="8"></label>
            <?php if ($error): ?><p class="err"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
            <button type="submit">Installation starten</button>
        </form>
    <?php endif; ?>
</body>
</html>
