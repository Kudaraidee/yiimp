# ASIC Mining Optimization Guide

## Problem: 50% Rejected Shares mit ASICs

Wenn ASICs (z.B. Antminer S9, L3+, Nano 3) hohe Reject-Raten haben, liegt das meist an:

### 1. Job-Timeout zu kurz
**✅ GELÖST:** Job-Timeout wurde von 120 auf 300 Sekunden erhöht (in `stratum/job.cpp`)

ASICs arbeiten oft länger an einem Job als GPUs. Mit dem erhöhten Timeout werden weniger Shares als "stale" abgelehnt.

### 2. ASIC Boost Optimierungen
**✅ VERBESSERT:** ASIC Boost Implementation wurde erweitert:
- Version-Rolling mit Mask `0x1fffe000` (Bits 13-28)
- Unterstützung für `minimum-difficulty` Extension
- Besseres Logging für ASIC-Konfiguration
- Optimierte Difficulty-Anpassung für hohe Hashrates

### 3. Difficulty-Einstellungen

**Für SHA256 (Bitcoin ASICs):**
```ini
[STRATUM]
algo = sha256
difficulty = 512
diff_min = 256
diff_max = 16384
```

**Für Scrypt (Litecoin ASICs):**
```ini
[STRATUM]
algo = scrypt
difficulty = 128
diff_min = 64
diff_max = 8192
```

**Wichtig:** 
- `difficulty` = Start-Difficulty
- `diff_min` = Minimale Difficulty (verhindert zu viele Shares)
- `diff_max` = Maximale Difficulty (verhindert zu wenige Shares)

### 4. Difficulty über Passwort setzen

Miner können ihre eigene Difficulty setzen:
```
-u ADRESSE -p d=512
```

Beispiel für verschiedene ASICs:
- **Antminer S9 (13.5 TH/s):** `d=2048` oder höher
- **Antminer L3+ (504 MH/s):** `d=512` oder höher
- **Whatsminer M30S (88 TH/s):** `d=8192` oder höher

### 5. Ntime-Rolling

Ntime-Rolling ist standardmäßig aktiviert (außer für x11evo, timetravel, bitcore, exosis).

ASICs benötigen Ntime-Rolling, um länger an einem Job arbeiten zu können.

### 6. Connection-Probleme

Wenn ASICs nicht connecten können:

**Prüfen Sie:**
1. Firewall-Regeln (Port 3333, 3433, etc.)
2. Stratum-Logs: `tail -f log/stratum-*.log`
3. Worker-Tabelle: Werden Worker erstellt?

**Häufige Fehler:**
- "Invalid job id" → Job-Timeout zu kurz (jetzt behoben)
- "Low difficulty share" → Difficulty zu hoch für den ASIC
- "Duplicate share" → ASIC sendet gleiche Share mehrmals

### 7. Optimale Einstellungen für verschiedene ASICs

#### Antminer S9 (SHA256, ~13.5 TH/s)
```
Stratum: stratum+tcp://pool:3333
Worker: ADRESSE.worker1
Password: d=2048
```

#### Antminer L3+ (Scrypt, ~504 MH/s)
```
Stratum: stratum+tcp://pool:3433
Worker: ADRESSE.worker1
Password: d=512
```

#### Whatsminer M30S (SHA256, ~88 TH/s)
```
Stratum: stratum+tcp://pool:3333
Worker: ADRESSE.worker1
Password: d=8192
```

### 8. Monitoring

**Prüfen Sie regelmäßig:**
```sql
-- Reject-Rate pro Worker
SELECT 
    w.name,
    COUNT(CASE WHEN s.valid = 1 THEN 1 END) as valid_shares,
    COUNT(CASE WHEN s.valid = 0 THEN 1 END) as invalid_shares,
    ROUND(COUNT(CASE WHEN s.valid = 0 THEN 1 END) * 100.0 / COUNT(*), 2) as reject_rate
FROM shares s
JOIN workers w ON s.workerid = w.id
WHERE s.time > UNIX_TIMESTAMP() - 3600
GROUP BY w.id
HAVING reject_rate > 5
ORDER BY reject_rate DESC;
```

**Normale Reject-Raten:**
- < 1% = Ausgezeichnet
- 1-3% = Gut
- 3-5% = Akzeptabel
- > 5% = Problem!

### 9. Weitere Optimierungen

**In der Stratum-Config:**
```ini
[STRATUM]
max_ttf = 40000          # Maximale Time-To-Find (für ASICs erhöhen)
reconnect = 1            # Reconnect-Support aktivieren
```

**Für Nano 3 mit ASIC Boost:**
- Stellen Sie sicher, dass `mining.configure` unterstützt wird (bereits implementiert)
- ASIC Boost ist in `client.cpp` implementiert

### 10. Troubleshooting

**Problem: ASIC verbindet sich nicht**
```bash
# Prüfen Sie Stratum-Logs
tail -f log/stratum-*.log | grep "new client"

# Prüfen Sie ob Port offen ist
netstat -tlnp | grep 3333
```

**Problem: Hohe Reject-Rate**
```bash
# Prüfen Sie Reject-Gründe
tail -f log/stratum-*.log | grep "REJECT"
```

**Problem: Worker verschwindet**
- Worker-Cleanup wurde optimiert (läuft alle 30 Minuten)
- Inaktive Worker werden nach 24 Stunden gelöscht

## Zusammenfassung der Änderungen

### Performance-Optimierungen:
1. ✅ **Job-Timeout erhöht:** 120s → 300s (weniger stale shares)
2. ✅ **ASIC Boost verbessert:** 
   - Erweiterte `mining.configure` Implementation
   - Support für `minimum-difficulty` Extension
   - Besseres Debug-Logging
3. ✅ **Difficulty-Anpassung optimiert:**
   - Aggressivere Erhöhung bei hohen Hashrates
   - Langsamere Verringerung (besser für ASICs)
   - Feinere Abstufungen (20, 30, 50, 75, 100 shares/min)
4. ✅ **Worker-Cleanup optimiert:** Inaktive Worker werden automatisch entfernt
5. ✅ **CashAddr-Support:** Bitcoin Cash/eCash Adressen funktionieren jetzt
6. ✅ **Share-Berechnung korrigiert:** Solo vs. Shared Mining getrennt

### ASIC Boost Details:
- **Version Mask:** `0x1fffe000` (Bits 13-28 können modifiziert werden)
- **Min-Bit-Count:** Vollständig unterstützt
- **Minimum Difficulty:** ASICs können ihre Mindest-Difficulty anfordern
- **Kompatibel mit:** Antminer S9/S17/S19, Whatsminer M20/M30, Avalon, etc.

### Erwartete Verbesserungen:
- **Reject-Rate:** Sollte von 50% auf < 3% sinken
- **Stale-Shares:** Deutlich weniger durch längeres Job-Timeout
- **Block-Finds:** Schneller durch bessere ASIC-Auslastung
- **Hashrate-Stabilität:** Weniger Difficulty-Schwankungen

Nach diesen Änderungen sollten ASICs optimal funktionieren!
