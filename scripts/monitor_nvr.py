import random
import requests
import time
from datetime import datetime

API_URL = "http://127.0.0.1:8000/api/update-nvr"
ALERTS_URL = "http://127.0.0.1:8000/api/alerts"

nvrs = [
    {"name": "NVR Main"},
    {"name": "NVR Backup"}
]

# ✅ Escalation tracking for NVR
nvr_sync_failure_count = {}  # Track consecutive sync failures
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

print("✅ NVR Monitoring Script Started...\n")

while True:
    for nvr in nvrs:
        try:
            # Simulate NVR type (20% chance master, 80% standard)
            nvr_type = "master" if random.randint(0, 5) == 1 else "standard"
            
            # Simulate sync_status (master can be "lost" sometimes)
            sync_status = "lost" if nvr_type == "master" and random.randint(0, 4) == 2 else "synced"
            
            # Simulate other NVR metrics
            status = random.choice(["online", "online", "online", "offline"])
            cameras_count = random.randint(5, 20)
            disk_usage = random.randint(40, 95)

            data = {
                "name": nvr["name"],
                "status": status,
                "cameras_count": cameras_count,
                "disk_usage": disk_usage,
                "type": nvr_type,
                "sync_status": sync_status
            }

            response = requests.post(API_URL, json=data)
            
            if response.status_code == 200 or response.status_code == 201:
                print(f"✅ [{datetime.now().strftime('%H:%M:%S')}] {nvr['name']} → {status.upper()} | Type={nvr_type.upper()} Sync={sync_status.upper()} | Cameras={cameras_count} Disk={disk_usage}%")
                
                # ✅ ESCALATION: NVR sync lost tracking
                if nvr_type == "master" and sync_status == "lost":
                    nvr_sync_failure_count[nvr["name"]] = nvr_sync_failure_count.get(nvr["name"], 0) + 1
                    sync_failures = nvr_sync_failure_count[nvr["name"]]
                    
                    # Escalate to critical if sync loss persists for 2+ cycles
                    severity = "critical" if sync_failures >= 2 else "warning"
                    
                    sync_alert_key = f"nvr_sync_{nvr['name'].replace(' ', '_')}"
                    if should_send_alert(sync_alert_key):
                        send_alert(
                            key=sync_alert_key,
                            title=f"NVR {nvr['name']} SYNC LOST",
                            message=f"Master NVR lost synchronization (Cycles: {sync_failures})",
                            alert_type="nvr",
                            severity=severity
                        )
                        if severity == "critical":
                            print(f"   🔴 CRITICAL ALERT: Sync lost for {sync_failures} consecutive cycles")
                        else:
                            print(f"   🚨 ALERT: Sync lost (cycle {sync_failures})")
                elif nvr_type == "master":
                    # Sync recovered - reset counter
                    if nvr_sync_failure_count.get(nvr["name"], 0) > 0:
                        old_count = nvr_sync_failure_count[nvr["name"]]
                        nvr_sync_failure_count[nvr["name"]] = 0
                        print(f"   ✅ Sync RESTORED (was lost {old_count} cycle(s))")
                
                # Display alerts
                if status == "offline":
                    print(f"   🚨 ALERT: NVR is OFFLINE")
                if disk_usage > 90:
                    print(f"   ⚠️  ALERT: Disk full - {disk_usage}%")
            else:
                print(f"❌ [{datetime.now().strftime('%H:%M:%S')}] {nvr['name']} → HTTP {response.status_code}")
                if response.text:
                    try:
                        print(f"   Response: {response.json()}")
                    except:
                        print(f"   Response: {response.text}")

        except requests.exceptions.ConnectionError:
            print(f"❌ Connection Error: Could not reach {API_URL}")
        except Exception as e:
            print(f"❌ Error [{nvr['name']}]: {str(e)}")

    print(f"\n⏱️  Next NVR check in 15 seconds...\n")
    time.sleep(15)