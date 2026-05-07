import random
import requests
import time
from datetime import datetime

BACKUPS_URL = "http://127.0.0.1:8000/api/backups"
ALERTS_URL = "http://127.0.0.1:8000/api/alerts"

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

print("✅ Backup Monitoring Script Started...\n")

while True:
    for server in servers:
        try:
            # Simulate backup status (70% success, 30% failed)
            backup_status = random.choice(["success", "success", "success", "failed"])
            
            data = {
                "server_name": server,
                "status": backup_status
            }

            # Send POST request to /api/backups
            response = requests.post(BACKUPS_URL, json=data)
            
            if response.status_code == 201:
                # ✅ ESCALATION LOGIC
                if backup_status == "failed":
                    # Increment failure count
                    failure_count[server] = failure_count.get(server, 0) + 1
                    consecutive_failures = failure_count[server]
                    
                    # Escalate severity based on consecutive failures
                    severity = "critical" if consecutive_failures >= 3 else "warning"
                    
                    status_display = f"✅ [{datetime.now().strftime('%H:%M:%S')}] Backup [{server}] → FAILED | Consecutive: {consecutive_failures} | {severity.upper()}"
                    print(status_display)
                    
                    # Send escalating alert
                    alert_key = f"backup_failed_{server.replace(' ', '_')}"
                    if should_send_alert(alert_key):
                        send_alert(
                            key=alert_key,
                            title=f"Backup FAILED for {server}",
                            message=f"Backup failed {consecutive_failures} time(s) consecutively",
                            alert_type="backup",
                            severity=severity
                        )
                        print(f"   🚨 Alert sent: {severity.upper()} - Failed {consecutive_failures}x")
                else:
                    # Backup succeeded - reset failure counter
                    if failure_count.get(server, 0) > 0:
                        old_count = failure_count[server]
                        failure_count[server] = 0
                        print(f"✅ [{datetime.now().strftime('%H:%M:%S')}] Backup [{server}] → SUCCESS | Resolved (was {old_count} failures)")
                        print(f"   ✅ Failure counter reset")
                    else:
                        print(f"✅ [{datetime.now().strftime('%H:%M:%S')}] Backup [{server}] → SUCCESS")
                    
                    last_backup_status[server] = backup_status
            else:
                print(f"❌ [{datetime.now().strftime('%H:%M:%S')}] Backup [{server}] → HTTP {response.status_code}")
                if response.text:
                    print(f"   Response: {response.text}")

        except requests.exceptions.ConnectionError:
            print(f"❌ Connection Error: Could not reach {BACKUPS_URL}")
        except Exception as e:
            print(f"❌ Error [{server}]: {str(e)}")

    # Wait before next monitoring cycle
    print(f"\n⏱️  Next backup check in 15 seconds...\n")
    time.sleep(15)
