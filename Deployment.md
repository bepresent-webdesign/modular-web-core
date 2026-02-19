# Deployment – Universal Core Mini-CMS

## 1. Installation (FTP + /setup)

1. Alle Dateien des Projekts per **FTP** in das Webroot (z. B. `htdocs`, `www`, `public_html`) hochladen.
2. **Keine** weiteren Schritte auf dem Server (kein Composer, kein npm, keine Datenbank).
3. Im Browser **https://ihre-domain.tld/setup** aufrufen.
4. **Admin-E-Mail** und **Passwort** (min. 8 Zeichen) eingeben, Passwort bestätigen, „Installation starten“ klicken.
5. Das System legt an:
   - `data/users.json` (Admin mit bcrypt-Hash)
   - `data/.setup.lock` (Setup wird danach gesperrt)
   - `content/site.json`, `content/impressum.json`, `content/datenschutz.json` (Platzhalter)
   - Ordner `data/`, `content/`, `uploads/images/`, `uploads/trash/`, `backups/`
6. Nach Abschluss: Links „Zum Admin-Bereich“ bzw. „Zur Website“ nutzen. **/setup** ist danach nicht mehr nutzbar (403).

## 2. Schreibrechte (wichtig bei 403/500)

Der Webserver (Apache/Nginx-PHP) muss in folgende Ordner **schreiben** können:

| Ordner / Datei        | Zweck                          | Typische Rechte |
|------------------------|--------------------------------|------------------|
| `data/`                | users.json, .setup.lock, Rate  | 755 oder 775      |
| `content/`             | site.json, impressum, datenschutz | 755 oder 775  |
| `uploads/`             | Bilder                         | 755 oder 775      |
| `uploads/images/`      | Hochgeladene Bilder            | 755 oder 775      |
| `uploads/trash/`       | Gelöschte Bilder (Trash)       | 755 oder 775      |
| `backups/`             | Backup-Dateien                 | 755 oder 775      |

- **403 Forbidden:** Oft fehlende Schreibrechte oder falsche Besitzer. Auf Shared Hosting: Ordner auf **755**, ggf. **775** setzen; Besitzer = Webserver-User (z. B. `nobody`, `www-data` oder der FTP-User, je nach Anbieter).
- **500 Internal Server Error:** PHP-Fehler oder fehlende Schreibrechte. In `lib/bootstrap.php` temporär `ini_set('display_errors', '1');` setzen, um die Meldung zu sehen; danach wieder auf `0` stellen. Logs des Hosters prüfen.

## 3. Login

- **URL:** https://ihre-domain.tld/admin  
- **Anmeldung:** Die im Setup angegebene **E-Mail** und das **Passwort**.
- Nach Login: Übersicht mit Links zu „Startseite bearbeiten“, „Impressum“, „Datenschutz“, „Medien“, „Backup & Restore“, „Passwort ändern“.

## 4. Content pflegen

- **Startseite:** Hero (Titel, Untertitel, Bild- oder Video-Embed), Leistungen, Über uns, Kontakt, Footer (Firma, Anschrift, Telefon, E-Mail, Impressum/Datenschutz-Linktexte). Alle Felder sind Text/Copy&Paste; Bild-URLs können aus der Medienverwaltung kopiert werden.
- **Impressum / Datenschutz:** Eigene Seiten mit Titel und HTML-Inhalt. Links erscheinen im Footer und sind eigene Seiten (keine Modals).
- Änderungen **sofort** auf der Website sichtbar (kein Cache im MVP).

## 5. Medienregeln

- **Bilder:** Max. **10 MB** pro Datei; Formate JPEG, PNG, GIF, WebP.
- **Upload:** In „Medien“ Bild auswählen oder per Drag&Drop in die Zone legen. Es wird automatisch WebP und ein Thumbnail erzeugt, falls PHP GD verfügbar ist; sonst Fallback auf Original.
- **URL verwenden:** Nach dem Upload die angezeigte URL (z. B. `/uploads/images/xxx.jpg`) in die gewünschten Content-Felder (Hero, Leistungen, Über uns etc.) kopieren.
- **Ersetzen:** „Ersetzen“ lädt eine neue Datei hoch und verschiebt die alte in den Papierkorb.
- **Löschen:** „In Papierkorb“ verschiebt die Datei in `uploads/trash/` (kein sofortiges Löschen), damit Restore sinnvoll bleibt.
- **Video:** Nur per Embed (z. B. YouTube/Vimeo iframe) in die entsprechenden Textbereiche einfügen.

## 6. Backup & Restore

- **Automatisch:** Bei jeder Speicherung von Content und bei Medien-Upload/Ersetzen/Trash wird ein Backup erstellt (max. 20 Backups).
- **Manuell:** Im Admin „Backup & Restore“ → „Backup jetzt erstellen“.
- **Wiederherstellen:** In der Liste ein Backup wählen und „Wiederherstellen“ klicken. Bestätigung beachten – aktueller Inhalt (Content + Metadaten, Bilder) wird durch den Stand des Backups ersetzt. „Letzter funktionierender Stand“ = zuletzt gewähltes Backup.

## 7. Nginx (optional)

Falls Sie Nginx nutzen, eine passende `location` für das Frontend und für `/setup`, `/admin`, `/superadmin` setzen, z. B.:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;  # Anpassen
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

Für saubere URLs `/impressum` und `/datenschutz` ggf. `index.php?page=impressum` bzw. `page=datenschutz` in der Konfiguration abbilden.

---

## Akzeptanzkriterien – Checkliste

- [ ] **/setup** funktioniert, legt **users.json** mit bcrypt-Hash an und sperrt Setup (Lockfile).
- [ ] **/admin** Login mit E-Mail/Passwort funktioniert.
- [ ] **Passwort ändern** (mit Doppel-Eingabe) funktioniert.
- [ ] **Content-Änderung** erscheint sofort im Frontend (Startseite, Impressum, Datenschutz).
- [ ] **Bildupload** (≤ 10 MB) funktioniert; es wird WebP + Thumbnail erzeugt oder Fallback auf Original + Thumb.
- [ ] **Löschen/Ersetzen** funktioniert sicher (Trash, kein Hard-Delete).
- [ ] **Backup** (automatisch bei Änderung + „Backup jetzt erstellen“) und **Restore** (Wiederherstellung eines Backups) funktionieren.
- [ ] **Impressum** und **Datenschutz** sind als **eigene Seiten** erreichbar (keine Modals).
- [ ] **Responsive:** Header mit Hamburger-Menü unter 1024 px Breite.
