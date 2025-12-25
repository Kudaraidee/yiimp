# Performance Tuning für schnellere Block-Finds

## Stratum-Konfiguration optimieren

### 1. Difficulty-Einstellungen

**Für SHA256 (Bitcoin):**
```ini
[STRATUM]
algo = sha256
difficulty = 512          # Start-Difficulty
diff_min = 256           # Minimum (verhindert zu viele Shares)
diff_max = 32768         # Maximum (verhindert zu wenige Shares)
max_ttf = 40000          # Time-To-Find Limit
max_cons = 10000         # Max Connections (für große Pools)
```

**Für Scrypt (Litecoin):**
```ini
[STRATUM]
algo = scrypt
difficulty = 128
diff_min = 64
diff_max = 16384
max_ttf = 40000
max_cons = 10000
```

### 2. Job-Management

**Optimierte Werte:**
- `MAX_JOBAGE = 300` (5 Minuten) - Erhöht von 120s
- `YAAMP_SHAREPERSEC = 10` - Target: 10 Shares pro Minute
- `g_allow_rolltime = true` - Ntime-Rolling aktiviert

### 3. ASIC Boost

**Version Mask:** `0x1fffe000`
- Erlaubt ASICs, Bits 13-28 im Block-Header zu modifizieren
- Erhöht die Effizienz um bis zu 20%
- Kompatibel mit allen modernen ASICs

**Extensions:**
- `version-rolling`: Aktiviert
- `minimum-difficulty`: Unterstützt
- `version-rolling.min-bit-count`: Informational

### 4. Database-Optimierungen

**Shares-Cleanup:**
```sql
-- Alte Shares konsolidieren (läuft automatisch)
-- Reduziert DB-Größe und verbessert Performance

-- Invalid Shares nach 24h löschen
DELETE FROM shares WHERE time < UNIX_TIMESTAMP() - 86400 AND valid = 0;

-- Rewarded Shares nach 2 Tagen löschen
DELETE FROM shares WHERE time < UNIX_TIMESTAMP() - 172800 AND blockrewarded > 0;
```

**Indizes prüfen:**
```sql
-- Wichtige Indizes für Performance
SHOW INDEX FROM shares;
SHOW INDEX FROM blocks;
SHOW INDEX FROM workers;

-- Falls fehlend, hinzufügen:
CREATE INDEX idx_shares_time ON shares(time);
CREATE INDEX idx_shares_userid ON shares(userid);
CREATE INDEX idx_shares_coinid_time ON shares(coinid, time);
CREATE INDEX idx_blocks_coinid_height ON blocks(coin_id, height);
```

### 5. Connection-Limits

**Für große Pools:**
```ini
max_cons = 10000         # Erhöhen für mehr Connections
```

**System-Limits prüfen:**
```bash
# File Descriptor Limits erhöhen
ulimit -n 65536

# In /etc/security/limits.conf:
* soft nofile 65536
* hard nofile 65536
```

### 6. Netzwerk-Optimierungen

**TCP-Tuning für hohe Last:**
```bash
# In /etc/sysctl.conf:
net.core.somaxconn = 4096
net.ipv4.tcp_max_syn_backlog = 4096
net.ipv4.tcp_fin_timeout = 30
net.ipv4.tcp_keepalive_time = 300
net.ipv4.tcp_keepalive_probes = 5
net.ipv4.tcp_keepalive_intvl = 15

# Anwenden:
sysctl -p
```

### 7. Monitoring für Block-Finds

**Wichtige Metriken:**
```sql
-- Pool Hashrate
SELECT algo, SUM(difficulty) / 60 as hashrate_per_sec
FROM shares 
WHERE time > UNIX_TIMESTAMP() - 60
GROUP BY algo;

-- Blocks pro Stunde
SELECT 
    c.symbol,
    COUNT(*) as blocks_found,
    AVG(b.difficulty) as avg_difficulty,
    AVG(b.effort) as avg_effort
FROM blocks b
JOIN coins c ON b.coin_id = c.id
WHERE b.time > UNIX_TIMESTAMP() - 3600
  AND b.category IN ('immature', 'generate')
GROUP BY c.id;

-- Effort-Analyse (< 100% = Glück, > 100% = Pech)
SELECT 
    c.symbol,
    b.height,
    b.effort,
    CASE 
        WHEN b.effort < 50 THEN 'Sehr glücklich'
        WHEN b.effort < 100 THEN 'Glücklich'
        WHEN b.effort < 150 THEN 'Normal'
        WHEN b.effort < 200 THEN 'Pech'
        ELSE 'Sehr viel Pech'
    END as luck
FROM blocks b
JOIN coins c ON b.coin_id = c.id
WHERE b.time > UNIX_TIMESTAMP() - 86400
  AND b.category IN ('immature', 'generate')
ORDER BY b.time DESC;
```

### 8. Coin-Switching Optimierung

**Für Auto-Exchange Pools:**
```sql
-- Profitabilität prüfen
SELECT 
    symbol,
    price,
    difficulty,
    (reward * price / difficulty) as profitability
FROM coins
WHERE enable = 1
  AND auto_ready = 1
ORDER BY profitability DESC;
```

**max_ttf anpassen:**
- Niedrigerer Wert = Schnelleres Switching zu profitableren Coins
- Höherer Wert = Stabileres Mining, weniger Orphans
- Empfohlen: `40000` (Standard)

### 9. Block-Propagation

**Schnellere Block-Submission:**
```php
// In coind_submit.cpp bereits optimiert
// Blocks werden sofort submitted, keine Verzögerung
```

**Wallet-Verbindung prüfen:**
```bash
# RPC-Latenz testen
time bitcoin-cli getblockcount

# Sollte < 100ms sein
# Falls langsamer: Wallet optimieren oder SSD verwenden
```

### 10. Stratum-Prozess-Optimierung

**Mehrere Stratum-Instanzen:**
```bash
# Für verschiedene Algos separate Prozesse
./run.sh sha256 &
./run.sh scrypt &
./run.sh x11 &

# Oder mit Screen/Tmux:
screen -dmS sha256 ./run.sh sha256
screen -dmS scrypt ./run.sh scrypt
```

**CPU-Affinity setzen:**
```bash
# Stratum an bestimmte CPU-Cores binden
taskset -c 0-3 ./run.sh sha256
```

## Erwartete Verbesserungen

### Vor Optimierung:
- Reject-Rate: 10-50%
- Stale-Shares: 5-10%
- Block-Finds: Langsam
- ASIC-Probleme: Häufig

### Nach Optimierung:
- Reject-Rate: < 3%
- Stale-Shares: < 2%
- Block-Finds: 10-20% schneller
- ASIC-Probleme: Minimal

## Checkliste

- [ ] Job-Timeout auf 300s erhöht
- [ ] ASIC Boost aktiviert und getestet
- [ ] Difficulty-Werte angepasst
- [ ] Database-Indizes geprüft
- [ ] System-Limits erhöht
- [ ] TCP-Tuning angewendet
- [ ] Monitoring eingerichtet
- [ ] Wallet-Latenz < 100ms
- [ ] Stratum-Logs auf Fehler geprüft
- [ ] Test mit ASIC durchgeführt

## Weitere Ressourcen

- [Stratum Protocol Extensions](https://github.com/slushpool/stratumprotocol/blob/master/stratum-extensions.mediawiki)
- [ASIC Boost Specification](https://arxiv.org/abs/1604.00575)
- [Bitcoin Mining Optimization](https://en.bitcoin.it/wiki/Mining)
