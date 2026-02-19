<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::requireAdmin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload' && !empty($_FILES['file']['name'])) {
        Backup::create('media_upload');
        $r = Media::upload($_FILES['file'], $_POST['slot'] ?? '');
        if (!empty($r['ok'])) {
            $success = 'Bild hochgeladen: ' . $r['name'];
        } else {
            $error = $r['error'] ?? 'Upload fehlgeschlagen.';
        }
    } elseif ($action === 'trash' && !empty($_POST['name'])) {
        Backup::create('media_trash');
        $r = Media::moveToTrash($_POST['name']);
        if (!empty($r['ok'])) {
            $success = 'Bild in Papierkorb verschoben.';
        } else {
            $error = $r['error'] ?? 'Löschen fehlgeschlagen.';
        }
    } elseif ($action === 'replace' && !empty($_POST['name']) && !empty($_FILES['file']['name'])) {
        Backup::create('media_replace');
        $r = Media::replace($_POST['name'], $_FILES['file']);
        if (!empty($r['ok'])) {
            $success = 'Bild ersetzt.';
        } else {
            $error = $r['error'] ?? 'Ersetzen fehlgeschlagen.';
        }
    }
}

$images = Media::listImages();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medien</title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/admin.css'); ?>">
</head>
<body class="admin-body">
    <header class="admin-header">
        <a href="<?php echo base_url('admin/'); ?>">Admin</a>
        <a href="<?php echo base_url('admin/content.php?page=site'); ?>">Startseite</a>
        <a href="<?php echo base_url('admin/media.php'); ?>">Medien</a>
        <a href="<?php echo base_url('admin/logout.php'); ?>">Abmelden</a>
    </header>
    <main class="admin-main">
        <h1>Medien (Bilder)</h1>
        <p>Max. 10 MB pro Bild. Erlaubt: JPEG, PNG, GIF, WebP. Es wird automatisch WebP und ein Thumbnail erzeugt, falls möglich.</p>
        <?php if ($success): ?><p class="success"><?php echo htmlspecialchars($success); ?></p><?php endif; ?>
        <?php if ($error): ?><p class="err"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <div class="upload-zone" id="uploadZone">
            <p>Bild hier ablegen oder klicken zum Auswählen</p>
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="slot" value="">
                <input type="file" name="file" accept="image/jpeg,image/png,image/gif,image/webp" id="fileInput">
            </form>
        </div>

        <h2>Vorhandene Bilder</h2>
        <p>Relativen Pfad kopieren (z.B. <code>uploads/img/dateiname.jpg</code>). Ersetzen verschiebt das alte Bild in den Papierkorb.</p>
        <ul class="media-list">
            <?php foreach ($images as $img): ?>
                <li class="media-item">
<img src="<?php echo htmlspecialchars(asset_url($img['path'])); ?>" alt="" loading="lazy" class="thumb">
                        <div class="media-meta">
                            <input type="text" readonly value="<?php echo htmlspecialchars($img['path']); ?>" class="url-copy" onclick="this.select();">
                        <form method="post" enctype="multipart/form-data" class="replace-form">
                            <input type="hidden" name="action" value="replace">
                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($img['name']); ?>">
                            <input type="file" name="file" accept="image/jpeg,image/png,image/gif,image/webp" class="replace-file">
                            <button type="submit">Ersetzen</button>
                        </form>
                        <form method="post" onsubmit="return confirm('In Papierkorb verschieben?');">
                            <input type="hidden" name="action" value="trash">
                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($img['name']); ?>">
                            <button type="submit" class="btn-trash">In Papierkorb</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (empty($images)): ?>
            <p>Noch keine Bilder. Laden Sie eines hoch und fügen Sie die URL in den Content ein.</p>
        <?php endif; ?>
    </main>
    <script>
    (function() {
        var zone = document.getElementById('uploadZone');
        var form = document.getElementById('uploadForm');
        var input = document.getElementById('fileInput');
        if (!zone || !form || !input) return;

        function submitFile(file) {
            if (!file || file.size > 10 * 1024 * 1024) {
                alert('Max. 10 MB pro Bild.');
                return;
            }
            var fd = new FormData(form);
            fd.set('file', file);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', form.action || '');
            xhr.onload = function() {
                if (xhr.status === 200) location.reload();
                else alert('Upload fehlgeschlagen.');
            };
            xhr.send(fd);
        }

        zone.addEventListener('click', function() { input.click(); });
        zone.addEventListener('dragover', function(e) { e.preventDefault(); e.stopPropagation(); zone.classList.add('drag'); });
        zone.addEventListener('dragleave', function() { zone.classList.remove('drag'); });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('drag');
            var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (f) submitFile(f);
        });
        input.addEventListener('change', function() {
            if (input.files && input.files[0]) submitFile(input.files[0]);
        });
    })();
    </script>
</body>
</html>
