<?php
declare(strict_types=1);

/**
 * Content loader and validator. File-based content.json, no DB.
 * Visitenkarten-Modell: Hero, Leistungen, Über uns, Kontakt, Footer.
 */
class Content {
    private const CONTENT_FILE = CONTENT_DIR . '/content.json';
    private const MAX_TITLE = 200;
    private const MAX_SUBTITLE = 500;
    private const MAX_SECTION_TEXT = 10000;
    private const MAX_SERVICE_TITLE = 150;
    private const MAX_SERVICE_TEXT = 1000;
    private const MAX_ADDRESS_FIELD = 200;

    /** Alle Inhalte aus content.json (Migration von site.json falls nötig) */
    public static function getAll(): array {
        $path = self::CONTENT_FILE;
        $legacyPath = CONTENT_DIR . '/site.json';
        if (!is_file($path) && is_file($legacyPath)) {
            $legacy = json_read($legacyPath);
            $data = self::migrateFromSite($legacy);
            json_write($path, $data);
        } else {
            $data = json_read($path);
        }
        return self::mergeDefaults($data);
    }

    private static function migrateFromSite(array $s): array {
        $addr = ['company' => '', 'addition' => '', 'street' => '', 'zip' => '', 'city' => ''];
        if (!empty($s['footer']['address'])) {
            $addr['company'] = $s['footer']['company_name'] ?? '';
            $addr['street'] = $s['footer']['address'] ?? '';
        }
        $features = $s['features'] ?? [];
        return [
            'hero' => [
                'hero_image' => $s['hero']['image'] ?? '',
                'hero_title' => $s['hero']['title'] ?? '',
                'hero_subtitle' => $s['hero']['subtitle'] ?? '',
                'hero_claim' => $s['hero']['claim'] ?? '',
            ],
            'services' => [
                'services_section_title' => 'Leistungen',
                'services_items' => array_map(fn($f) => [
                    'title' => $f['title'] ?? '',
                    'short_text' => $f['text'] ?? '',
                ], $features),
            ],
            'about' => [
                'about_title' => $s['about']['title'] ?? '',
                'about_text' => $s['about']['text'] ?? '',
            ],
            'contact' => [
                'contact_title' => $s['contact']['title'] ?? '',
                'contact_text' => $s['contact']['text'] ?? '',
                'contact_address' => $addr,
                'contact_email' => $s['contact']['email'] ?? '',
                'contact_phone_landline' => $s['contact']['phone'] ?? '',
                'contact_fax' => '',
                'contact_mobile' => '',
            ],
            'footer' => ['footer_note' => ''],
            'images' => [
                'hero_image' => $s['hero']['image'] ?? '',
                'details_image' => '',
                'portrait_image' => $s['about']['image'] ?? '',
                'contact_image' => '',
            ],
        ];
    }

    /** Legacy-Kompatibilität: site-ähnliche Struktur für bestehende Templates */
    public static function getSite(): array {
        $d = self::getAll();
        $addr = $d['contact']['contact_address'] ?? [];
        $addrStr = self::formatAddress($addr);
        return [
            'hero' => [
                'title' => $d['hero']['hero_title'] ?? '',
                'subtitle' => $d['hero']['hero_subtitle'] ?? '',
                'claim' => $d['hero']['hero_claim'] ?? '',
                'image' => $d['images']['hero_image'] ?? $d['hero']['hero_image'] ?? '',
            ],
            'services_section_title' => $d['services']['services_section_title'] ?? 'Leistungen',
            'features' => array_map(fn($s) => [
                'title' => $s['title'] ?? '',
                'text' => $s['short_text'] ?? '',
            ], $d['services']['services_items'] ?? []),
            'about' => [
                'title' => $d['about']['about_title'] ?? '',
                'text' => $d['about']['about_text'] ?? '',
                'image' => $d['images']['portrait_image'] ?? '',
            ],
            'images' => [
                'details_image' => $d['images']['details_image'] ?? '',
                'contact_image' => $d['images']['contact_image'] ?? '',
            ],
            'contact' => [
                'title' => $d['contact']['contact_title'] ?? '',
                'text' => $d['contact']['contact_text'] ?? '',
                'address' => $addr,
                'email' => $d['contact']['contact_email'] ?? '',
                'phone' => $d['contact']['contact_phone_landline'] ?? '',
            ],
            'footer' => [
                'company_name' => $addr['company'] ?? '',
                'address' => $addrStr,
                'phone' => $d['contact']['contact_phone_landline'] ?? $d['contact']['contact_mobile'] ?? '',
                'email' => $d['contact']['contact_email'] ?? '',
                'impressum_label' => 'Impressum',
                'datenschutz_label' => 'Datenschutz',
                'footer_note' => $d['footer']['footer_note'] ?? '',
            ],
        ];
    }

    private static function formatAddress(array $a): string {
        $parts = [];
        if (!empty($a['company'])) $parts[] = $a['company'];
        if (!empty($a['addition'])) $parts[] = $a['addition'];
        if (!empty($a['street'])) $parts[] = $a['street'];
        if (!empty($a['zip']) || !empty($a['city'])) {
            $parts[] = trim(($a['zip'] ?? '') . ' ' . ($a['city'] ?? ''));
        }
        return implode(', ', array_filter($parts));
    }

    public static function getImpressum(): array {
        return self::getPage('impressum');
    }

    public static function getDatenschutz(): array {
        return self::getPage('datenschutz');
    }

    public static function getPage(string $name): array {
        $path = CONTENT_DIR . '/' . preg_replace('/[^a-z0-9_-]/', '', $name) . '.json';
        $data = json_read($path);
        $titles = ['impressum' => 'Impressum', 'datenschutz' => 'Datenschutz'];
        $defaults = [
            'title' => $titles[$name] ?? ucfirst($name),
            'content' => '<p>Platzhalter für ' . ($titles[$name] ?? $name) . '.</p>',
        ];
        return array_merge($defaults, $data);
    }

    public static function save(array $data): array {
        $merged = self::mergeDefaults($data);
        $valid = self::validate($merged);
        if (!empty($valid['errors'])) {
            return $valid;
        }
        if (!json_write(self::CONTENT_FILE, $merged)) {
            return ['errors' => ['Speichern fehlgeschlagen.']];
        }
        return ['ok' => true];
    }

    public static function savePage(string $name, array $data): array {
        $safe = preg_replace('/[^a-z0-9_-]/', '', $name);
        if ($safe !== $name) {
            return ['errors' => ['Ungültiger Seitenname.']];
        }
        $titles = ['impressum' => 'Impressum', 'datenschutz' => 'Datenschutz'];
        $merged = array_merge(
            ['title' => $titles[$name] ?? ucfirst($name), 'content' => ''],
            $data
        );
        if (mb_strlen($merged['title'] ?? '') > self::MAX_TITLE) {
            return ['errors' => ['Titel zu lang.']];
        }
        if (!json_write(CONTENT_DIR . '/' . $safe . '.json', $merged)) {
            return ['errors' => ['Speichern fehlgeschlagen.']];
        }
        return ['ok' => true];
    }

    private static function mergeDefaults(array $data): array {
        $defaults = [
            'hero' => [
                'hero_image' => '',
                'hero_title' => 'Willkommen',
                'hero_subtitle' => 'Ihre Überschrift für die Startseite.',
                'hero_claim' => '',
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
                    'company' => '',
                    'addition' => '',
                    'street' => '',
                    'zip' => '',
                    'city' => '',
                ],
                'contact_email' => '',
                'contact_phone_landline' => '',
                'contact_fax' => '',
                'contact_mobile' => '',
            ],
            'footer' => [
                'footer_note' => '',
            ],
            'images' => [
                'hero_image' => '',
                'details_image' => '',
                'portrait_image' => '',
                'contact_image' => '',
            ],
        ];
        return self::deepMerge($defaults, $data);
    }

    private static function deepMerge(array $defaults, array $data): array {
        foreach ($data as $k => $v) {
            if (is_array($v) && isset($defaults[$k]) && is_array($defaults[$k]) && !self::isList($v)) {
                $defaults[$k] = self::deepMerge($defaults[$k], $v);
            } else {
                $defaults[$k] = $v;
            }
        }
        return $defaults;
    }

    private static function isList(array $a): bool {
        if ($a === []) return true;
        return array_keys($a) === range(0, count($a) - 1);
    }

    private static function validate(array $d): array {
        $err = [];
        if (mb_strlen($d['hero']['hero_title'] ?? '') > self::MAX_TITLE) $err[] = 'Hero-Titel zu lang.';
        if (mb_strlen($d['hero']['hero_subtitle'] ?? '') > self::MAX_SUBTITLE) $err[] = 'Hero-Subtitle zu lang.';
        if (mb_strlen($d['hero']['hero_claim'] ?? '') > self::MAX_SUBTITLE) $err[] = 'Hero-Claim zu lang.';

        $items = $d['services']['services_items'] ?? [];
        if (count($items) < 1 || count($items) > 6) {
            $err[] = 'Leistungen: 1–6 Einträge erforderlich.';
        }
        foreach ($items as $i => $s) {
            if (mb_strlen($s['title'] ?? '') > self::MAX_SERVICE_TITLE) $err[] = "Leistung $i: Titel zu lang.";
            if (mb_strlen($s['short_text'] ?? '') > self::MAX_SERVICE_TEXT) $err[] = "Leistung $i: Text zu lang.";
        }

        if (mb_strlen($d['about']['about_text'] ?? '') > self::MAX_SECTION_TEXT) $err[] = 'Über-uns-Text zu lang.';
        if (mb_strlen($d['contact']['contact_text'] ?? '') > self::MAX_SECTION_TEXT) $err[] = 'Kontakt-Text zu lang.';

        $addr = $d['contact']['contact_address'] ?? [];
        foreach (['company', 'addition', 'street', 'zip', 'city'] as $f) {
            if (mb_strlen($addr[$f] ?? '') > self::MAX_ADDRESS_FIELD) $err[] = "Adressfeld $f zu lang.";
        }

        return $err ? ['errors' => $err] : ['ok' => true];
    }
}
