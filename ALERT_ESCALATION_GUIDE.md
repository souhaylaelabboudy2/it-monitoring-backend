# Alert Escalation System - Implementation Summary

## 🎯 Overview
A complete alert escalation system has been implemented for both Python monitoring scripts and Laravel backend. Alerts start at **warning** level and automatically escalate to **critical** when issues persist, triggering incident creation only once.

---

## 📊 Python Monitoring Scripts

### 1. **Backup Monitoring (`monitor_backup.py`)**
**Escalation Logic:**
- Tracks consecutive backup failures using `failure_count` dictionary
- **Severity Levels:**
  - 1 failure: `WARNING`
  - 3+ consecutive failures: `CRITICAL`
- Failure counter resets when backup succeeds
- Sends alerts with proper severity to backend

**Key Additions:**
```python
failure_count = {}  # Track consecutive failures
server_resource_warning_count = {}  # Track escalation cycles
```

### 2. **Server Monitoring (`monitor_servers.py`)**
**Escalation Logic:**
- Tracks consecutive monitoring cycles with resource warnings
- **Resource Escalation (CPU/RAM):**
  - < 3 cycles: `WARNING`
  - ≥ 3 cycles of high usage: `CRITICAL`
- **NVR Sync Escalation:**
  - 1 cycle with sync lost: `WARNING`
  - ≥ 2 consecutive cycles: `CRITICAL`
- Counters reset when metrics normalize

**Key Additions:**
```python
server_resource_warning_count = {}  # Track CPU/RAM warning cycles
nvr_sync_failure_count = {}  # Track NVR sync failures
```

### 3. **NVR Monitoring (`monitor_nvr.py`)**
**Escalation Logic:**
- Tracks consecutive NVR master sync failures
- **Sync Escalation:**
  - 1 cycle sync lost: `WARNING`
  - ≥ 2 consecutive cycles: `CRITICAL`
- Counter resets when sync is restored

**Key Additions:**
```python
nvr_sync_failure_count = {}  # Track consecutive sync failures
```

---

## 🔧 Laravel Backend

### 1. **Alert Model (`App\Models\Alert`)**
Enhanced with escalation methods:
```php
protected $fillable = [
    'title', 'message', 'type', 
    'severity', 'key', 'status', 'incident_id'
];

// Helper methods:
- findByKey($key)      // Find alert by unique identifier
- isResolved()         // Check if alert is resolved
- resolve()            // Mark alert as resolved
- acknowledge()        // Mark as acknowledged
```

### 2. **AlertSystem Model (`App\Models\AlertSystem`)**
Core alert tracking model with incident linking:
```php
protected $fillable = [
    'key', 'title', 'message', 'type', 
    'severity', 'status', 'incident_id', 'last_seen'
];

// Relationships:
- incident()           // Belongs to Incident
```

### 3. **Incident Model (`App\Models\Incident`)**
Enhanced with resolution tracking:
```php
// New methods:
- resolve()            // Resolve incident + all related alerts
- acknowledge()        // Mark as acknowledged
- isResolved()         // Check resolution status
```

### 4. **AlertSystemController** - Core Logic
**Escalation & Incident Management:**

```php
// Existing alert update flow:
if ($existingAlert) {
    $oldSeverity = $existingAlert->severity;
    $newSeverity = $request->severity;
    
    // Only create incident on escalation to CRITICAL (once)
    if ($newSeverity === 'critical' && 
        $oldSeverity !== 'critical' && 
        !$existingAlert->incident_id) {
        // Create incident
    }
}

// New alert flow:
if ($request->severity === 'critical') {
    // Create incident immediately
}
```

**Key Features:**
- ✅ Prevents duplicate incidents (checks `incident_id`)
- ✅ Escalates only when severity changes to critical
- ✅ Maintains one incident per alert key
- ✅ Resolves alerts without incident spam

---

## 📋 Database Migrations

### New Migrations Created:

1. **`2026_05_07_add_escalation_columns_to_alerts_table.php`**
   - Adds: `severity`, `key`, `title`, `status`
   - Enables: Alert deduplication and escalation tracking

2. **`2026_05_07_add_incident_id_to_alert_system_table.php`**
   - Adds: `incident_id` (nullable, indexed)
   - Links: Alerts to incidents

---

## 🔄 Escalation Flow

### Python Script → Backend → Database

```
┌─ Backup Fails ──────────────────────────────────────┐
│                                                      │
├─ Failure #1 ──→ severity: "warning" ────────→ Alert Created
│                                               (no incident)
│
├─ Failure #2 ──→ severity: "warning" ────────→ Alert Updated
│                                               (no incident)
│
└─ Failure #3+ ─→ severity: "critical" ───────→ Alert Updated
                                               + Incident Created (ONCE)
```

### Alert Resolution

```
┌─ Backup Succeeds ───────────────────────────────────┐
│                                                      │
├─ Reset failure_count ──────────────────────────┐    │
│                                                 │    │
└──→ Resolve Alert ──→ resolve_alert(key) ──────→ Alert.status = "resolved"
```

---

## 🚀 Alert API Endpoints

### Create/Update Alert
```http
POST /api/alerts
Content-Type: application/json

{
  "key": "backup_failed_SQL_Server",
  "title": "Backup FAILED for SQL Server",
  "message": "Backup failed 3 time(s) consecutively",
  "type": "backup",
  "severity": "critical"
}
```

**Response (Escalation Case):**
```json
{
  "success": true,
  "action": "escalated",
  "message": "Alert escalated to CRITICAL - Incident created",
  "data": {
    "alert": { ... },
    "incident": { ... }
  }
}
```

### Resolve Alert
```http
POST /api/alerts/resolve
Content-Type: application/json

{
  "key": "backup_failed_SQL_Server"
}
```

### Get Critical Alerts
```http
GET /api/alerts/critical
```

---

## 📈 Monitoring Output

### Python Script Console Output

**Backup Escalation:**
```
✅ [14:25:30] Backup [SQL Server] → FAILED | Consecutive: 1 | WARNING
   🚨 Alert sent: WARNING - Failed 1x

✅ [14:40:30] Backup [SQL Server] → FAILED | Consecutive: 2 | WARNING
   🚨 Alert sent: WARNING - Failed 2x

✅ [14:55:30] Backup [SQL Server] → FAILED | Consecutive: 3 | CRITICAL
   🚨 Alert sent: CRITICAL - Failed 3x

✅ [15:10:30] Backup [SQL Server] → SUCCESS | Resolved (was 3 failures)
   ✅ Failure counter reset
```

**Server Resource Escalation:**
```
🔴 CRITICAL Alert: Active Directory resource usage critical (3 cycles)

✅ Resolved: Active Directory resource usage normal
```

---

## ⚡ Key Behavior

| Event | Action | Incident Created |
|-------|--------|-----------------|
| New warning alert | Create alert | ❌ No |
| Warning → Warning | Update alert | ❌ No |
| Warning → Critical | Update alert + escalate | ✅ Yes (once) |
| Critical → Warning | Update alert | ❌ No |
| Resolution | Mark resolved | ❌ No |

---

## 🛡️ Spam Prevention Features

1. **Alert Deduplication**: Same `key` = same alert (update, not duplicate)
2. **Cooldown System**: 5-minute minimum between identical alerts
3. **One Incident Per Alert**: Incident created only on escalation to critical
4. **Counter Reset**: Counters reset when issue resolves
5. **Status Tracking**: Resolved alerts don't trigger new incidents

---

## 🧪 Testing Escalation

To test the system:

1. **Backup Test**: Stop backup process 3+ times
   - Expected: Alert escalates to critical after 3 failures
   - Result: Single incident created

2. **Server Resource Test**: Maintain high CPU/RAM for 3+ monitoring cycles
   - Expected: Alert escalates to critical
   - Result: Incident created

3. **NVR Sync Test**: NVR master sync stays lost for 2+ cycles
   - Expected: Alert escalates to critical
   - Result: Incident created

4. **Resolution Test**: Fix the issue
   - Expected: Counters reset, alert resolves
   - Result: No duplicate incidents on next issue

---

## 📝 Migration Steps

To apply the new system:

```bash
# Run migrations
php artisan migrate

# (Optional) If you need to reset:
# php artisan migrate:rollback
```

---

## 💡 Summary

✅ **Escalation System Implemented:**
- Python scripts track failure/warning cycles
- Automatic severity escalation after repeated issues
- Laravel backend creates incidents only for critical alerts
- Prevents duplicate incidents and alert spam
- Clean alert resolution and counter reset
- Professional, production-ready behavior

