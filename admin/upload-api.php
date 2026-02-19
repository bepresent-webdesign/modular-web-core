<?php
/**
 * AJAX-Upload: Sofortiger Bild-Upload ohne Form-Submit.
 * Liefert JSON: {ok: true, path: "uploads/img/..."} oder {error: "..."}
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Nur POST erlaubt.']);
    exit;
}
if (!csrf_verify()) {
    echo json_encode(['error' => 'Ungültiger Sicherheits-Token.']);
    exit;
}

$file = $_FILES['file'] ?? null;
$slot = trim($_POST['slot'] ?? '');

if (!$file || empty($file['name'])) {
    echo json_encode(['error' => 'Keine Datei ausgewählt.']);
    exit;
}

$r = Media::upload($file, $slot);
if (!empty($r['ok'])) {
    echo json_encode(['ok' => true, 'path' => $r['path'], 'name' => $r['name']]);
} else {
    echo json_encode(['error' => $r['error'] ?? 'Upload fehlgeschlagen.']);
}
