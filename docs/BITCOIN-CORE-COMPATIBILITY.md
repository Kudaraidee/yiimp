# Bitcoin Core 0.17+ Kompatibilität

## Übersicht der RPC-Änderungen

Diese Datei dokumentiert alle RPC-Befehle die in neueren Bitcoin Core Versionen geändert oder entfernt wurden und wie der Pool damit umgeht.

## Entfernte/Geänderte RPC-Befehle

### Bitcoin Core 0.17
- `getinfo` → **DEPRECATED** (entfernt in 0.18)
- Account-System → **DEPRECATED** (entfernt in 0.18)

### Bitcoin Core 0.18
- `getinfo` → **ENTFERNT** → Ersetzt durch `getblockchaininfo`, `getnetworkinfo`, `getwalletinfo`
- `getaccountaddress` → **ENTFERNT** → Ersetzt durch `getnewaddress`
- `listaccounts` → **ENTFERNT** → Ersetzt durch `listlabels`
- `getaccount` → **ENTFERNT**
- `setaccount` → **ENTFERNT**

### Bitcoin Core 0.21
- `sendmany` Account-Parameter → **ENTFERNT** (erster Parameter wird ignoriert)
- Descriptor Wallets → `getwalletinfo` gibt keine `balance` mehr zurück

## Pool-Implementierung

### 1. `getinfo` Ersatz
**Status:** ✅ Implementiert

Wenn `hasgetinfo=0`:
```php
// Kombiniert Daten aus:
$miningInfo = getmininginfo();      // blocks, difficulty
$walletInfo = getwalletinfo();      // balance, walletversion
$networkInfo = getnetworkinfo();    // connections, version
```

### 2. Balance Abruf (Descriptor Wallets)
**Status:** ✅ Implementiert

```php
// Prüft zuerst getwalletinfo
if (isset($walletInfo['balance'])) {
    $balance = $walletInfo['balance'];
} else {
    // Für Descriptor Wallets (Bitcoin Core 0.21+)
    $balances = getbalances();
    $balance = $balances['mine']['trusted'] + $balances['mine']['untrusted_pending'];
}
```

### 3. `getaccountaddress` Ersatz
**Status:** ✅ Implementiert

```php
// Alt (Bitcoin Core < 0.18):
getaccountaddress("pool")

// Neu (Bitcoin Core 0.18+):
getnewaddress("pool")  // "pool" ist jetzt ein Label statt Account
```

### 4. `listaccounts` Ersatz
**Status:** ✅ Implementiert (gibt leeres Array zurück)

```php
// Alt (Bitcoin Core < 0.18):
listaccounts(1)

// Neu (Bitcoin Core 0.18+):
// Accounts existieren nicht mehr
// Pool sollte nicht mehr auf Accounts angewiesen sein
return [];
```

**Hinweis:** Wenn der Pool Rental-Features nutzt, die auf `listaccounts` angewiesen sind, funktionieren diese mit Bitcoin Core 0.18+ nicht mehr.

### 5. `sendmany` ohne Account
**Status:** ✅ Implementiert

```php
// Alt (Bitcoin Core < 0.21):
sendmany("pool", {addresses}, minconf, comment)

// Neu (Bitcoin Core 0.21+):
sendmany("", {addresses}, minconf, comment)  // Account-Parameter wird ignoriert
```

### 6. Wallet-Name im RPC-Pfad
**Status:** ✅ Implementiert

```php
// Für Bitcoin Core 0.17+ mit Multi-Wallet Support
// RPC-URL: http://host:port/wallet/pool
// Konfiguration: rpcwallet = "pool"
```

## Konfiguration für moderne Wallets

### Für Bitcoin Core 0.21+ (wie Briskcoin 3.0)

In der Datenbank:
```sql
UPDATE coins SET 
    hasgetinfo = 0,
    rpcwallet = 'pool'
WHERE symbol = 'BKC';
```

Im Admin-Panel:
- ☐ Has getinfo (ausschalten)
- RPC Wallet: `pool`

### Wallet erstellen
```bash
# Für Bitcoin Core 0.21+
briskcoin-cli createwallet "pool"

# Prüfen
briskcoin-cli listwallets
# Sollte zeigen: ["pool"]
```

## Betroffene Pool-Funktionen

### ✅ Funktioniert mit neuen Wallets
- Mining (getblocktemplate)
- Balance-Anzeige (getbalances)
- Payments (sendtoaddress, sendmany)
- Block-Tracking (listsinceblock)
- Wallet-Adresse (getnewaddress)

### ⚠️ Eingeschränkt mit neuen Wallets
- **Rental-System**: `listaccounts` gibt leeres Array zurück
  - Rental-Payments könnten nicht mehr funktionieren
  - Alternative: Separate Wallets für jeden Renter verwenden

### ❌ Nicht kompatibel
- Account-basierte Funktionen (komplett entfernt in 0.18+)

## Testen

Test-Script ausführen:
```bash
php test-balance-methods.php
```

Sollte zeigen:
- ✓ getinfo funktioniert (verwendet getwalletinfo)
- ✓ Balance wird korrekt abgerufen
- ✓ Connections werden angezeigt

## Weitere Coins

Diese Änderungen funktionieren auch für:
- Bitcoin Core 0.17+
- Litecoin Core 0.17+
- Dogecoin Core 1.14.5+
- Alle Bitcoin-Forks die auf Core 0.17+ basieren

Setze einfach `hasgetinfo=0` und optional `rpcwallet=<name>`.
