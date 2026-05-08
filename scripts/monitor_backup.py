import random
import requests
import time
from datetime import datetime

BACKUPS_URL = "http://127.0.0.1:8000/api/backups"
ALERTS_URL = "http://127.0.0.1:8000/api/alerts"
RESOLVE_URL = "http://127.0.0.1:8000/api/alerts/resolve"

servers = [
    "Active Directory",
    "SQL Server",
    "App Server",
]

# ✅ Escalation tracking for backups
failure_count = {}  # Track consecutive failures
last_backup_status = {}  # Track last status
last_alert_time = {}  # Track alert cooldown (5 min)

HEADERS = {"Content-Type": "application/json"}

def should_send_alert(key):
    """Check if alert should be sent (cooldown: 5 minutes)"""
    current_time = time.time()
    last_time = last_alert_time.get(key, 0)
    if current_time - last_time > 300:  # 5 minutes
        last_alert_time[key] = current_time
        return True
    return False

def send_alert(key, title, message, alert_type, severity):
    """Send alert to backend"""
    try:
        data = {
            "key": key,
            "title": title,
            "message": message,
            "type": alert_type,
            "severity": severity
        }
        response = requests.post(ALERTS_URL, json=data, headers=HEADERS)
        return response.status_code in [200, 201]
    except Exception as e:
        print(f"❌ Failed to send alert: {e}")
        return False

def resolve_alert(key):
    """Resolve alert when issue is fixed"""
    try:
        data = {"key": key}
        response = requests.post(RESOLVE_URL, json=data, headers=HEADERS)
        return response.status_code == 200
    except Exception as e:
        print(f"❌ Failed to resolve alert: {e}")
        return False

def generate_error_message(failure_count):
    """Generate realistic error messages for failures"""
    errors = [
        "Connection timeout to backup server",
        "Insufficient disk space on backup destination",
        "Database lock detected - backup aborted",
        "Network connectivity lost during backup",
        "Authentication failed for backup credentials",
        "Backup destination unavailable",
        "System resources exhausted",
        "Backup window timeout exceeded"
    ]
    return errors[failure_count % len(errors)]

def generate_backup_metrics():
    """Generate realistic backup metrics"""
    duration = random.randint(15, 180)  # 15-180 minutes
    size = round(random.uniform(50, 500), 2)  # 50-500 GB
    return duration, size

print("✅ Backup Monitoring Script Started...\n")

while True:
    for server in servers:
        try:
            # Simulate backup status (70% success, 30% failed)
            backup_status = random.choice(["success", "success", "success", "failed"])
            
            # Get consecutive failure count
            if backup_status == "failed":
                failure_count[server] = failure_count.get(server, 0) + 1
                consecutive_failures = failure_count[server]
                
                # ✅ ESCALATION LOGIC:
                # 1 failure: WARNING
                # 3+ consecutive failures: CRITICAL
                severity = "critical" if consecutive_failures >= 3 else "warning"
                
                status_display = f"✅ [{datetime.now().strftime('%H:%M:%S')}] Backup [{server}] → FAILED"
                status_display += f" | Consecutive: {consecutive_failures}"
                status_display += f" | {severity.upper()}"
                print(status_display)
                
                # Send alert with proper severity
                alert_key = f"backup_failed_{server.replace(' ', '_')}"
                if should_send_alert(alert_key):
                    send_alert(
                        key=alert_key,
                        title=f"Backup FAILED for {server}",
                        message=f"Backup failed {consecutive_failures} time(s) consecutively",
                        alert_type="backup",
                        severity=severity
                    )
                    if severity == "critical":
                        print(f"   🔴 CRITICAL: Backup failed {consecutive_failures}x")
                        print(f"   🚨 Incident created automatically")
                    else:
                        print(f"   ⚠️  Alert sent: {severity.upper()}")
            else:
                # ✅ SUCCESS - Reset counter and resolve if needed
                if failure_count.get(server, 0) > 0:
                    prev_failures = failure_count[server]
                    failure_count[server] = 0
                    
                    status_display = f"✅ [{datetime.now().strftime('%H:%M:%S')}] Backup [{server}] → SUCCESS"
                    status_display += f" | Resolved ({prev_failures} previous failures)"
                    print(status_display)
                    
                    # Resolve the alert
                    alert_key = f"backup_failed_{server.replace(' ', '_')}"
                    resolve_alert(alert_key)
                    print(f"   ✅ Alert resolved - Failure counter reset")
                else:
                    print(f"✅ [{datetime.now().strftime('%H:%M:%S')}] Backup [{server}] → SUCCESS")
                    print(f"   No issues detected")

            last_backup_status[server] = backup_status

        except requests.exceptions.ConnectionError:
            print(f"❌ Connection Error: Could not reach API")
        except Exception as e:
            print(f"❌ Error [{server}]: {str(e)}")

    # Wait before next monitoring cycle
    print(f"\n⏱️  Next backup check in 15 seconds...\n")
    time.sleep(15)
