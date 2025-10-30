# Manual Test Checklist

Version 1.2.0 - 2025-10-30

## Voraussetzungen

- [ ] ILIAS Installation läuft
- [ ] Plugin ist aktiviert
- [ ] Test-Übung mit Individual Assignment vorhanden
- [ ] Test-Übung mit Team Assignment vorhanden
- [ ] Test-User mit Tutor-Rechten verfügbar

---

## Test 1: Individual Assignment - Multi-Feedback Download

### Schritte:
1. Öffne Übung mit Individual Assignment
2. Stelle sicher, dass mindestens 2 Teilnehmer Abgaben haben
3. Gehe zu "Bewertung/Noten"
4. Klicke "Multi-Feedback Download"
5. Wähle mindestens 2 Teilnehmer aus
6. Klicke Download

### Erwartetes Ergebnis:
- [ ] ZIP-Download startet
- [ ] ZIP enthält `status.xlsx` und `status.csv`
- [ ] ZIP enthält `README.md`
- [ ] ZIP enthält Ordner `Lastname_Firstname_Login_ID/` für jeden User
- [ ] In User-Ordnern sind die Submission-Dateien vorhanden
- [ ] README.md enthält KEIN "Noch in Entwicklung"
- [ ] README.md enthält Warnung "Ordner-Namen dürfen NICHT geändert werden!"

### Bei Fehler:
- Check Log: `/var/www/studon7_extras/studon7.log`
- Fehlermeldung notieren
- ZIP-Struktur überprüfen

---

## Test 2: Individual Assignment - Feedback Upload OHNE Status-Updates

### Vorbereitung:
1. Nutze ZIP aus Test 1
2. Entpacke ZIP
3. Öffne **EINEN** User-Ordner (z.B. `Mustermann_Max_mmuster_12345/`)
4. Lege eine neue Datei hinein: `feedback_test.txt` (Inhalt egal)
5. **WICHTIG**: Ändere NICHTS in `status.xlsx` - kein `update=1`!
6. Erstelle ZIP wieder (alle Dateien)

### Schritte:
1. Gehe in die Übung → "Bewertung/Noten"
2. Klicke "Multi-Feedback Upload"
3. Lade das modifizierte ZIP hoch

### Erwartetes Ergebnis:
- [ ] Upload erfolgreich (keine Fehlermeldung)
- [ ] Bei dem User erscheint `feedback_test.txt` unter "Rückmeldung per Datei"
- [ ] Die originalen Submission-Dateien wurden NICHT als Feedback hochgeladen
- [ ] Keine Änderung am Status/Note (weil kein `update=1` gesetzt)

### Bei Fehler:
- Check Log: `tail -100 /var/www/studon7_extras/studon7.log | grep FEEDBACK`
- Prüfe ob Datei wirklich neu ist (nicht schon Submission)
- Prüfe Ordner-Namen (darf nicht geändert worden sein)

---

## Test 3: Individual Assignment - Feedback Upload MIT Status-Updates

### Vorbereitung:
1. Nutze ZIP aus Test 1
2. Entpacke ZIP
3. Öffne `status.xlsx`
4. Für EINEN User: Setze `update` = 1, ändere `mark` = 2.5, ändere `status` = passed
5. Speichere `status.xlsx`
6. Lege auch eine neue Datei `feedback_mit_status.txt` in den User-Ordner
7. Erstelle ZIP wieder

### Schritte:
1. Multi-Feedback Upload
2. Lade ZIP hoch

### Erwartetes Ergebnis:
- [ ] Upload erfolgreich
- [ ] Status wurde geändert → "Bestanden"
- [ ] Note wurde geändert → "2.5"
- [ ] Feedback-Datei `feedback_mit_status.txt` ist sichtbar

---

## Test 4: Team Assignment - Multi-Feedback Download

### Schritte:
1. Öffne Übung mit Team Assignment
2. Stelle sicher, dass mindestens 2 Teams Abgaben haben
3. Gehe zu "Bewertung/Noten"
4. Klicke "Multi-Feedback Download"
5. Wähle mindestens 2 Teams aus
6. Klicke Download

### Erwartetes Ergebnis:
- [ ] ZIP-Download startet
- [ ] ZIP enthält `status.xlsx`, `status.csv`, `README.md`
- [ ] ZIP enthält `Team_X/` Ordner (X = Team-Nummer aus DB, z.B. 13, 15)
- [ ] Jedes Team-Ordner enthält User-Unterordner: `Team_13/Lastname_Firstname_Login_ID/`
- [ ] In User-Ordnern sind Submissions vorhanden
- [ ] README.md korrekt (kein "in Entwicklung")

---

## Test 5: Team Assignment - Feedback Upload OHNE Status-Updates

### Vorbereitung:
1. ZIP aus Test 4 entpacken
2. Gehe in EINEN Team-Ordner, dann in EINEN User-Ordner
   Beispiel: `Team_13/Mustermann_Max_mmuster_12345/`
3. Lege `team_feedback.txt` hinein
4. **Ändere NICHTS in status.xlsx** (kein `update=1`)
5. ZIP erstellen

### Schritte:
1. Multi-Feedback Upload
2. ZIP hochladen

### Erwartetes Ergebnis:
- [ ] Upload erfolgreich
- [ ] `team_feedback.txt` ist bei dem User sichtbar (Rückmeldung per Datei)
- [ ] Bei ALLEN Team-Mitgliedern ist die Datei sichtbar (Teams teilen Feedback)
- [ ] Submissions wurden NICHT als Feedback hochgeladen
- [ ] Kein Status/Note geändert

### Check Log:
```bash
tail -100 /var/www/studon7_extras/studon7.log | grep -E "(team_id|Processing team)"
```

Erwartete Logs:
- `processTeamFeedbackFiles: Found 1 teams with feedback files`
- `processTeamFeedbackFiles: Processing team_id=13 with X files`

**NICHT erwartet** (alter Bug):
- `Team 47 not found in assignment 47` ❌

---

## Test 6: Security - Path Traversal Prevention

### ⚠️ NUR IN TESTUMGEBUNG DURCHFÜHREN! ⚠️

### Vorbereitung:
1. Erstelle ein ZIP mit bösartigem Dateinamen:
   ```bash
   mkdir test_zip
   echo "evil" > test_zip/normal.txt
   # Erstelle Datei mit path traversal Namen (schwierig, Manual)
   cd test_zip
   zip -r ../evil.zip .
   ```
2. ODER: Nutze Python-Skript (sicherer):
   ```python
   import zipfile
   z = zipfile.ZipFile('evil.zip', 'w')
   z.writestr('../../evil.php', '<?php echo "HACKED"; ?>')
   z.writestr('normal.txt', 'safe content')
   z.close()
   ```

### Schritte:
1. Versuche Upload von evil.zip

### Erwartetes Ergebnis:
- [ ] Upload schlägt NICHT fehl (wird verarbeitet)
- [ ] Aber: Verdächtige Datei wird NICHT extrahiert
- [ ] Log zeigt: "Suspicious filename detected" oder "Path traversal attempt detected"
- [ ] Keine Datei außerhalb von Temp-Verzeichnis erstellt
- [ ] Normale Dateien (normal.txt) werden trotzdem verarbeitet

### Check:
```bash
tail -50 /var/www/studon7_extras/studon7.log | grep -E "(Suspicious|Path traversal)"
```

---

## Test 7: Große Dateien / Viele Dateien

### Schritte:
1. Erstelle ZIP mit vielen Dateien (z.B. 100 Feedback-Dateien)
2. Upload

### Erwartetes Ergebnis:
- [ ] Upload funktioniert (kann länger dauern)
- [ ] Alle Dateien werden verarbeitet
- [ ] Keine Timeout-Fehler
- [ ] Keine Memory-Fehler

### Bei Timeout:
PHP-Settings prüfen:
- `max_execution_time`
- `memory_limit`
- `upload_max_filesize`

---

## Test 8: Ordner-Namen Validierung

### Schritte:
1. ZIP aus Test 1 entpacken
2. Benenne einen User-Ordner um:
   `Mustermann_Max_mmuster_12345/` → `Mustermann_Max_FALSCH_99999/`
3. Lege Feedback-Datei in den umbenannten Ordner
4. ZIP erstellen und hochladen

### Erwartetes Ergebnis:
- [ ] Upload läuft durch
- [ ] Feedback für umbenannten Ordner wird NICHT zugeordnet (User 99999 existiert nicht)
- [ ] Andere User erhalten trotzdem ihr Feedback
- [ ] Keine kritischen Fehler (nur Warnings im Log)

### Message:
Dies beweist, dass die Warnung "Ordner-Namen nicht ändern" wichtig ist!

---

## Checkliste - Alle Tests

Nach Durchführung aller Tests:

- [ ] Test 1: Individual Download ✅
- [ ] Test 2: Individual Upload ohne Status ✅
- [ ] Test 3: Individual Upload mit Status ✅
- [ ] Test 4: Team Download ✅
- [ ] Test 5: Team Upload ohne Status ✅
- [ ] Test 6: Security (optional, Testumgebung) ✅
- [ ] Test 7: Große Dateien ✅
- [ ] Test 8: Ordner-Namen Validierung ✅

**Alle Tests bestanden?**
- Ja → Plugin ist produktionsbereit ✅
- Nein → Fehler dokumentieren und melden

---

## Fehler melden

Bei Problemen:
1. Log-Einträge sichern: `tail -200 /var/www/studon7_extras/studon7.log > error.log`
2. Fehlermeldung notieren
3. Schritte zur Reproduktion dokumentieren
4. GitHub Issue erstellen: https://github.com/comusielak/ExerciseStatusFile/issues

---

## Performance Baseline (optional)

Messe Zeiten für Referenz:

| Test | Anzahl Files | Upload-Zeit | Download-Zeit |
|------|--------------|-------------|---------------|
| Individual (10 User) | ~50 | __ sec | __ sec |
| Individual (50 User) | ~250 | __ sec | __ sec |
| Team (5 Teams, 6 Members) | ~150 | __ sec | __ sec |

**Normal**: 1-5 Sekunden für kleine ZIPs, 10-30 Sekunden für große ZIPs
