<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::requireAdmin();

$page = $_GET['page'] ?? 'site';
$allowed = ['site', 'impressum', 'datenschutz'];
if (!in_array($page, $allowed, true)) {
    header('Location: ' . base_url('admin/'));
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Ungültiger Sicherheits-Token. Bitte erneut versuchen.';
    } else {
        Backup::create('content_edit');
        if ($page === 'site') {
            $n = (int)($_POST['services_count'] ?? 3);
            $n = max(1, min(6, $n));
            $items = [];
            for ($i = 0; $i < $n; $i++) {
                $items[] = [
                    'title' => $_POST['service_title'][$i] ?? '',
                    'short_text' => HtmlSanitizer::sanitize($_POST['service_short_text'][$i] ?? ''),
                ];
            }
            $data = [
                'hero' => [
                    'hero_image' => $_POST['hero_image'] ?? '',
                    'hero_title' => $_POST['hero_title'] ?? '',
                    'hero_subtitle' => $_POST['hero_subtitle'] ?? '',
                    'hero_claim' => trim($_POST['hero_claim'] ?? ''),
                ],
                'services' => [
                    'services_section_title' => $_POST['services_section_title'] ?? 'Leistungen',
                    'services_items' => $items,
                ],
                'about' => [
                    'about_title' => $_POST['about_title'] ?? '',
                    'about_text' => HtmlSanitizer::sanitize($_POST['about_text'] ?? ''),
                ],
                'contact' => [
                    'contact_title' => $_POST['contact_title'] ?? '',
                    'contact_text' => HtmlSanitizer::sanitize($_POST['contact_text'] ?? ''),
                    'contact_address' => [
                        'company' => $_POST['contact_company'] ?? '',
                        'addition' => $_POST['contact_addition'] ?? '',
                        'street' => $_POST['contact_street'] ?? '',
                        'zip' => $_POST['contact_zip'] ?? '',
                        'city' => $_POST['contact_city'] ?? '',
                    ],
                    'contact_email' => $_POST['contact_email'] ?? '',
                    'contact_phone_landline' => $_POST['contact_phone_landline'] ?? '',
                    'contact_fax' => $_POST['contact_fax'] ?? '',
                    'contact_mobile' => $_POST['contact_mobile'] ?? '',
                ],
                'footer' => [
                    'footer_note' => HtmlSanitizer::sanitize($_POST['footer_note'] ?? ''),
                ],
                'images' => [
                    'hero_image' => normalize_image_path(trim($_POST['img_hero_image'] ?? '')),
                    'details_image' => normalize_image_path(trim($_POST['img_details_image'] ?? '')),
                    'portrait_image' => normalize_image_path(trim($_POST['img_portrait_image'] ?? '')),
                    'contact_image' => normalize_image_path(trim($_POST['img_contact_image'] ?? '')),
                ],
            ];
            if (!$error) {
                $result = Content::save($data);
                if (!empty($result['ok'])) $success = true;
                else $error = implode(' ', $result['errors'] ?? ['Fehler beim Speichern.']);
            }
        } else {
            $content = HtmlSanitizer::sanitizeLegal($_POST['content'] ?? '');
            $content = str_replace("\r\n", "\n", $content);
            $content = str_replace("\r", "\n", $content);
            $content = trim($content);
            $result = Content::savePage($page, [
                'title' => $_POST['title'] ?? '',
                'content' => $content,
            ]);
            if (!empty($result['ok'])) $success = true;
            else $error = implode(' ', $result['errors'] ?? ['Fehler beim Speichern.']);
        }
    }
}

if ($page === 'site') {
    $raw = Content::getAll();
    $data = $raw;
} else {
    $data = $page === 'impressum' ? Content::getImpressum() : Content::getDatenschutz();
    $legalContent = $data['content'] ?? '';
    if (preg_match('/<[a-z][a-z0-9]*\b/i', $legalContent)) {
        $legalContent = str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>'], "\n", $legalContent);
        $legalContent = trim(strip_tags($legalContent));
    }
    $data['content_plain'] = $legalContent;
}

/**
 * Rendert einen Rich-Text-Editor mit Format-Toolbar (B, I, U, Schriftgröße).
 */
function renderRichEditor(string $name, string $content, string $placeholder = '', int $rows = 6): string {
    $display = HtmlSanitizer::containsHtml($content)
        ? $content
        : nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
    $valueAttr = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $ph = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    $minH = max(80, $rows * 24);
    return '<div class="format-toolbar-wrapper">'
        . '<div class="format-toolbar">'
        . '<button type="button" class="format-btn format-btn--bold" title="Fett" data-cmd="bold">B</button>'
        . '<button type="button" class="format-btn format-btn--italic" title="Kursiv" data-cmd="italic">I</button>'
        . '<button type="button" class="format-btn format-btn--underline" title="Unterstrichen" data-cmd="underline">U</button>'
        . '<div class="format-font-wrap">'
        . '<select class="format-font-size" title="Schriftgröße" data-cmd="fontSize">'
        . '<option value="">Schriftgröße</option>'
        . '<option value="14">14 px</option><option value="16">16 px</option><option value="18">18 px</option>'
        . '<option value="20">20 px</option><option value="22">22 px</option><option value="24">24 px</option>'
        . '</select></div></div>'
        . '<div class="format-editor-wrap">'
        . '<div class="format-editor" contenteditable="true" data-placeholder="' . $ph . '" style="min-height:' . $minH . 'px">' . $display . '</div>'
        . '<input type="hidden" class="format-value" name="' . htmlspecialchars($name) . '" value="' . $valueAttr . '">'
        . '</div></div>';
}

$imgHints = [
    'hero_image' => 'Empfohlen: 1920×600 px (Querformat). JPG, PNG oder WebP.',
    'details_image' => 'Empfohlen: 800×600 px. JPG, PNG oder WebP.',
    'portrait_image' => 'Empfohlen: 400×500 px (Hochformat). JPG, PNG oder WebP.',
    'contact_image' => 'Empfohlen: 800×600 px. JPG, PNG oder WebP.',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Content: <?php echo htmlspecialchars($page); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/admin.css'); ?>">
    <script src="<?php echo base_url('assets/js/format-toolbar.js'); ?>" defer></script>
</head>
<body class="admin-body">
    <header class="admin-header">
    <a href="<?php echo base_url('admin/'); ?>">Admin</a>
    <a href="<?php echo base_url('admin/content.php?page=site'); ?>">Startseite</a>
    <a href="<?php echo base_url('admin/content.php?page=impressum'); ?>">Impressum</a>
    <a href="<?php echo base_url('admin/content.php?page=datenschutz'); ?>">Datenschutz</a>
    <a href="<?php echo base_url('admin/logout.php'); ?>">Abmelden</a>
    </header>
    <main class="admin-main">
        <h1><?php echo $page === 'site' ? 'Startseite' : ucfirst($page); ?> bearbeiten</h1>
        <?php if ($success): ?><p class="success">Gespeichert. <a href="<?php echo base_url(); ?>">Ansehen</a></p><?php endif; ?>
        <?php if ($error): ?><p class="err"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <?php if ($page === 'site'): ?>
            <form method="post" class="admin-form" id="contentForm">
                <?php echo csrf_field(); ?>
                <?php $svcCount = count($data['services']['services_items'] ?? []); $svcCount = $svcCount ?: 3; ?>
                <input type="hidden" name="services_count" id="servicesCount" value="<?php echo $svcCount; ?>">

                <fieldset>
                    <legend>Hero</legend>
                    <label>Titel <input type="text" name="hero_title" value="<?php echo htmlspecialchars($data['hero']['hero_title'] ?? ''); ?>"></label>
                    <label>Untertitel <textarea name="hero_subtitle" rows="2"><?php echo htmlspecialchars($data['hero']['hero_subtitle'] ?? ''); ?></textarea></label>
                    <label>Claim (kurzer Slogan unter dem Untertitel) <input type="text" name="hero_claim" value="<?php echo htmlspecialchars($data['hero']['hero_claim'] ?? ''); ?>" placeholder="z. B. Zuverlässig. Sauber. Termingerecht."></label>
                </fieldset>

                <fieldset>
                    <legend>Bilder (4 feste Slots – sofortiger Upload oder Auswahl vorhandener Bilder)</legend>
                    <?php foreach (['hero_image', 'details_image', 'portrait_image', 'contact_image'] as $slot): ?>
                        <?php
                        $label = ['hero_image' => 'Hero-Bild', 'details_image' => 'Detail-Bild (Leistungen)', 'portrait_image' => 'Portrait (Über uns)', 'contact_image' => 'Kontakt-Bild'][$slot];
                        $path = normalize_image_path($data['images'][$slot] ?? '');
                        $previewUrl = $path ? asset_url($path) : '';
                        ?>
                        <div class="img-slot" data-slot="<?php echo htmlspecialchars($slot); ?>">
                            <label><strong><?php echo $label; ?></strong></label>
                            <input type="hidden" name="img_<?php echo $slot; ?>" value="<?php echo htmlspecialchars($path); ?>" class="img-path-input">
                            <div class="img-dropzone" data-slot="<?php echo htmlspecialchars($slot); ?>" tabindex="0">
                                <div class="img-preview-wrap">
                                    <?php if ($previewUrl): ?>
                                        <img src="<?php echo htmlspecialchars($previewUrl); ?>" alt="" class="img-preview-thumb" onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <span class="img-placeholder">Bild hier ablegen oder auswählen</span>
                                    <?php endif; ?>
                                </div>
                                <p class="img-path-display"><?php echo $path ? htmlspecialchars($path) : '<em>Kein Bild</em>'; ?></p>
                                <div class="img-actions">
                                    <button type="button" class="btn-select-file">Datei auswählen</button>
                                    <button type="button" class="btn-pick-existing">Vorhandenes Bild wählen</button>
                                </div>
                                <input type="file" class="img-file-input" accept="image/jpeg,image/png,image/webp" style="display:none">
                            </div>
                            <p class="img-hint"><?php echo htmlspecialchars($imgHints[$slot]); ?></p>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <fieldset>
                    <legend>Leistungen</legend>
                    <label>Abschnitts-Titel <input type="text" name="services_section_title" value="<?php echo htmlspecialchars($data['services']['services_section_title'] ?? 'Leistungen'); ?>"></label>
                    <label>Anzahl (1–6) <select id="servicesCountSel">
                        <?php $svcCount = count($data['services']['services_items'] ?? []); $svcCount = $svcCount ?: 3; ?>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>"<?php echo $svcCount === $i ? ' selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select></label>
                    <div id="servicesItems">
                        <?php $items = $data['services']['services_items'] ?? []; $maxRows = max($svcCount, count($items), 1); for ($i = 0; $i < $maxRows; $i++): $s = $items[$i] ?? ['title' => '', 'short_text' => '']; ?>
                            <div class="service-row" data-index="<?php echo $i; ?>">
                                <label>Titel <input type="text" name="service_title[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($s['title'] ?? ''); ?>"></label>
                                <label>Kurzbeschreibung</label>
                                <?php echo renderRichEditor('service_short_text[' . $i . ']', $s['short_text'] ?? '', 'Kurzbeschreibung …', 2); ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Über uns</legend>
                    <label>Titel <input type="text" name="about_title" value="<?php echo htmlspecialchars($data['about']['about_title'] ?? ''); ?>"></label>
                    <label>Text</label>
                    <?php echo renderRichEditor('about_text', $data['about']['about_text'] ?? '', 'Hier stellen Sie sich vor …', 6); ?>
                </fieldset>

                <fieldset>
                    <legend>Kontakt</legend>
                    <label>Titel <input type="text" name="contact_title" value="<?php echo htmlspecialchars($data['contact']['contact_title'] ?? ''); ?>"></label>
                    <label>Text</label>
                    <?php echo renderRichEditor('contact_text', $data['contact']['contact_text'] ?? '', 'So erreichen Sie uns …', 3); ?>
                    <p><strong>Adresse (wird im Footer verwendet)</strong></p>
                    <?php $addr = $data['contact']['contact_address'] ?? []; ?>
                    <label>Firma <input type="text" name="contact_company" value="<?php echo htmlspecialchars($addr['company'] ?? ''); ?>"></label>
                    <label>Zusatz <input type="text" name="contact_addition" value="<?php echo htmlspecialchars($addr['addition'] ?? ''); ?>" placeholder="z. B. Büro Nord"></label>
                    <label>Straße <input type="text" name="contact_street" value="<?php echo htmlspecialchars($addr['street'] ?? ''); ?>"></label>
                    <label>PLZ <input type="text" name="contact_zip" value="<?php echo htmlspecialchars($addr['zip'] ?? ''); ?>"></label>
                    <label>Ort <input type="text" name="contact_city" value="<?php echo htmlspecialchars($addr['city'] ?? ''); ?>"></label>
                    <label>E-Mail <input type="email" name="contact_email" value="<?php echo htmlspecialchars($data['contact']['contact_email'] ?? ''); ?>"></label>
                    <label>Telefon Festnetz <input type="text" name="contact_phone_landline" value="<?php echo htmlspecialchars($data['contact']['contact_phone_landline'] ?? ''); ?>"></label>
                    <label>Telefon mobil <input type="text" name="contact_mobile" value="<?php echo htmlspecialchars($data['contact']['contact_mobile'] ?? ''); ?>"></label>
                    <label>Fax <input type="text" name="contact_fax" value="<?php echo htmlspecialchars($data['contact']['contact_fax'] ?? ''); ?>"></label>
                </fieldset>

                <fieldset>
                    <legend>Footer</legend>
                    <p class="hint">Adresse und Kontaktdaten werden automatisch aus dem Kontakt-Bereich übernommen.</p>
                    <label>Zusätzlicher Hinweis (optional, z. B. Öffnungszeiten – mehrzeilig möglich)</label>
                    <?php echo renderRichEditor('footer_note', $data['footer']['footer_note'] ?? '', 'Öffnungszeiten: Mo–Fr 9–18 Uhr …', 4); ?>
                </fieldset>

                <button type="submit">Speichern</button>
            </form>
            <script>
            (function() {
                var sel = document.getElementById('servicesCountSel');
                var hidden = document.getElementById('servicesCount');
                var container = document.getElementById('servicesItems');
                if (!sel || !hidden || !container) return;
                function update() {
                    var n = parseInt(sel.value, 10);
                    hidden.value = n;
                    var rows = container.querySelectorAll('.service-row');
                    for (var i = 0; i < rows.length; i++) {
                        rows[i].style.display = i < n ? 'block' : 'none';
                    }
                }
                sel.addEventListener('change', update);
                update();
            })();
            </script>

            <!-- Bild-Auswahl Modal -->
            <div id="imagePickerModal" class="img-picker-modal" role="dialog" aria-labelledby="imagePickerTitle" aria-hidden="true">
                <div class="img-picker-backdrop"></div>
                <div class="img-picker-content">
                    <h2 id="imagePickerTitle">Bild auswählen</h2>
                    <div class="img-picker-tabs">
                        <button type="button" class="img-picker-tab active" data-tab="assets">assets/img</button>
                        <button type="button" class="img-picker-tab" data-tab="uploads">uploads/img</button>
                    </div>
                    <div class="img-picker-panel active" id="panel-assets">
                        <div class="img-picker-grid" id="grid-assets"></div>
                    </div>
                    <div class="img-picker-panel" id="panel-uploads">
                        <div class="img-picker-grid" id="grid-uploads"></div>
                    </div>
                    <button type="button" class="img-picker-close">Schließen</button>
                </div>
            </div>

            <script>
            (function() {
                var baseUrl = '<?php echo addslashes(rtrim(base_url(), '/')); ?>';
                var uploadUrl = baseUrl + '/admin/upload-api.php';
                var imagesApiUrl = baseUrl + '/admin/images-api.php';
                var csrfToken = document.querySelector('input[name="csrf_token"]') && document.querySelector('input[name="csrf_token"]').value;

                function initImageSlots() {
                    document.querySelectorAll('.img-slot').forEach(function(slotEl) {
                        var slot = slotEl.dataset.slot;
                        var dropzone = slotEl.querySelector('.img-dropzone');
                        var pathInput = slotEl.querySelector('.img-path-input');
                        var fileInput = slotEl.querySelector('.img-file-input');
                        var btnSelect = slotEl.querySelector('.btn-select-file');
                        var btnPick = slotEl.querySelector('.btn-pick-existing');
                        var previewWrap = slotEl.querySelector('.img-preview-wrap');
                        var pathDisplay = slotEl.querySelector('.img-path-display');

                        function updateSlot(path, previewSrc) {
                            pathInput.value = path || '';
                            pathDisplay.innerHTML = path ? escapeHtml(path) : '<em>Kein Bild</em>';
                            previewWrap.innerHTML = '';
                            if (path && previewSrc) {
                                var img = document.createElement('img');
                                img.className = 'img-preview-thumb';
                                img.alt = '';
                                img.src = previewSrc;
                                img.onerror = function() { this.style.display = 'none'; };
                                previewWrap.appendChild(img);
                            } else {
                                var s = document.createElement('span');
                                s.className = 'img-placeholder';
                                s.textContent = 'Bild hier ablegen oder auswählen';
                                previewWrap.appendChild(s);
                            }
                        }

                        function doUpload(file) {
                            if (!file || file.size > 10 * 1024 * 1024) {
                                alert('Max. 10 MB. Nur JPG, PNG, WebP.');
                                return;
                            }
                            var fd = new FormData();
                            fd.append('file', file);
                            fd.append('slot', slot);
                            fd.append('csrf_token', csrfToken);
                            dropzone.classList.add('uploading');
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', uploadUrl);
                            xhr.onload = function() {
                                dropzone.classList.remove('uploading');
                                try {
                                    var r = JSON.parse(xhr.responseText);
                                    if (r.ok && r.path) {
                                        updateSlot(r.path, baseUrl + '/' + r.path);
                                    } else {
                                        alert(r.error || 'Upload fehlgeschlagen.');
                                    }
                                } catch (e) {
                                    alert('Upload fehlgeschlagen.');
                                }
                            };
                            xhr.onerror = function() {
                                dropzone.classList.remove('uploading');
                                alert('Upload fehlgeschlagen.');
                            };
                            xhr.send(fd);
                        }

                        btnSelect.addEventListener('click', function(e) {
                            e.preventDefault();
                            fileInput.click();
                        });
                        fileInput.addEventListener('change', function() {
                            if (fileInput.files && fileInput.files[0]) {
                                doUpload(fileInput.files[0]);
                                fileInput.value = '';
                            }
                        });

                        dropzone.addEventListener('click', function(e) {
                            if (e.target === dropzone || e.target.classList.contains('img-placeholder')) fileInput.click();
                        });
                        dropzone.addEventListener('dragover', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            dropzone.classList.add('drag-over');
                        });
                        dropzone.addEventListener('dragleave', function() {
                            dropzone.classList.remove('drag-over');
                        });
                        dropzone.addEventListener('drop', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            dropzone.classList.remove('drag-over');
                            var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
                            if (f && /^image\/(jpeg|png|webp)$/i.test(f.type)) doUpload(f);
                            else alert('Nur JPG, PNG oder WebP.');
                        });

                        btnPick.addEventListener('click', function(e) {
                            e.preventDefault();
                            openImagePicker(slot, function(selectedPath, selectedUrl) {
                                updateSlot(selectedPath, selectedUrl);
                            });
                        });
                    });
                }

                function escapeHtml(s) {
                    var d = document.createElement('div');
                    d.textContent = s;
                    return d.innerHTML;
                }

                var pickerCurrentSlot = null;
                var pickerCallback = null;

                function openImagePicker(slot, callback) {
                    pickerCurrentSlot = slot;
                    pickerCallback = callback;
                    var modal = document.getElementById('imagePickerModal');
                    modal.setAttribute('aria-hidden', 'false');
                    modal.classList.add('open');
                    loadImages();
                }

                function closeImagePicker() {
                    var modal = document.getElementById('imagePickerModal');
                    modal.setAttribute('aria-hidden', 'true');
                    modal.classList.remove('open');
                }

                function loadImages() {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', imagesApiUrl);
                    xhr.onload = function() {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            renderGrid('grid-assets', data.assets || []);
                            renderGrid('grid-uploads', data.uploads || []);
                        } catch (e) {}
                    };
                    xhr.send();
                }

                function renderGrid(id, items) {
                    var grid = document.getElementById(id);
                    if (!grid) return;
                    grid.innerHTML = '';
                    items.forEach(function(item) {
                        var div = document.createElement('div');
                        div.className = 'img-picker-item';
                        div.innerHTML = '<img src="' + escapeHtml(item.url) + '" alt="" loading="lazy"><span class="img-picker-path">' + escapeHtml(item.path) + '</span>';
                        div.dataset.path = item.path;
                        div.dataset.url = item.url;
                        div.addEventListener('click', function() {
                            if (pickerCallback) pickerCallback(item.path, item.url);
                            closeImagePicker();
                        });
                        grid.appendChild(div);
                    });
                    if (items.length === 0) {
                        grid.innerHTML = '<p class="img-picker-empty">Keine Bilder.</p>';
                    }
                }

                document.querySelectorAll('.img-picker-tab').forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        document.querySelectorAll('.img-picker-tab').forEach(function(t) { t.classList.remove('active'); });
                        document.querySelectorAll('.img-picker-panel').forEach(function(p) { p.classList.remove('active'); });
                        tab.classList.add('active');
                        var panel = document.getElementById('panel-' + tab.dataset.tab);
                        if (panel) panel.classList.add('active');
                    });
                });
                document.querySelector('.img-picker-backdrop').addEventListener('click', closeImagePicker);
                document.querySelector('.img-picker-close').addEventListener('click', closeImagePicker);

                initImageSlots();
            })();
            </script>
        <?php else: ?>
            <form method="post" class="admin-form">
                <?php echo csrf_field(); ?>
                <label>Seitentitel <input type="text" name="title" value="<?php echo htmlspecialchars($data['title'] ?? ''); ?>"></label>
                <label>Inhalt</label>
                <?php echo renderRichEditor('content', $data['content'] ?? $data['content_plain'] ?? '', 'Inhalt eingeben …', 20); ?>
                <button type="submit">Speichern</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
