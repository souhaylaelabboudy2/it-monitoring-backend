import random
import requests
import time
from datetime import datetime

NVR_URL = "http://127.0.0.1:8000/api/nvrs"
ALERTS_URL = "http://127.0.0.1:8000/api/alerts"
RESOLVE_URL = "http://127.0.0.1:8000/api/alerts/resolve"

nvrs = [
    {"name": "NVR Main"},
    {"name": "NVR Backup"}
]

# ✅ Escalation tracking for NVR
nvr_sync_failure_count = {}  # Track consecutive sync failures
last_nvr_status = {}  # Track last status
last_nvr_sync = {}  # Track last sync status
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

print("✅ NVR Monitoring Script Started...\n")

while True:
    for nvr in nvrs:
        try:
            # Simulate NVR type (20% chance master, 80% standard)
            nvr_type = "master" if random.randint(0, 5) == 1 else "standard"
            
            # Simulate status (70% online, 30% offline)
            status = random.choice(["online", "online", "online", "offline"])
            
            # Simulate sync_status (master can be "lost" sometimes)
            # Standard NVRs always synced (but controller ignores it)
            if nvr_type == "master":
                sync_status = "lost" if random.randint(0, 4) == 2 else "synced"
            else:
                sync_status = "synced"
            
            # Simulate other NVR metrics
            cameras_count = random.randint(5, 20)
            disk_usage = round(random.uniform(40, 95), 1)

            # Build request data
            data = {
                "name": nvr["name"],
                "type": nvr_type,
                "status": status,
                "cameras_count": cameras_count,
                "disk_usage": disk_usage
            }

            # Add sync_status only for master NVRs
            if nvr_type == "master":
                data["sync_status"] = sync_status

            # Send POST request to /api/nvrs
            response = requests.post(NVR_URL, json=data, headers=HEADERS)
            
            if response.status_code in [201, 200]:
                # Parse response
                try:
                    resp_data = response.json()
                    action = resp_data.get('action', 'unknown')
                except:
                    action = 'unknown'

                # ============================================================
                # STATUS UPDATE (online/offline)
                # ============================================================
                status_display = f"✅ [{datetime.now().strftime('%H:%M:%S')}] {nvr['name']} → {status.upper()}"
                status_display += f" | Type={nvr_type.upper()} | Cameras={cameras_count} | Disk={disk_usage}%"
                
                if status == "offline":
                    # ✅ OFFLINE - CRITICAL ALERT
                    status_display += " 🔴 CRITICAL"
                    print(status_display)
                    
                    # Send critical alert for offline
                    offline_alert_key = f"nvr_offline_{nvr['name'].replace(' ', '_')}"
                    if should_send_alert(offline_alert_key):
                        send_alert(
                            key=offline_alert_key,
                            title=f"NVR {nvr['name']} OFFLINE",
                            message="NVR system not responding to monitoring requests",
                            alert_type="nvr",
                            severity="critical"
                        )
                        print(f"   🚨 Incident created automatically")
                    
                    # Reset sync failure counter on offline
                    nvr_sync_failure_count[nvr["name"]] = 0
                else:
                    # NVR is online
                    print(status_display)
                    
                    # Resolve offline alert if it was previously down
                    last_status = last_nvr_status.get(nvr["name"])
                    if last_status == "offline":
                        offline_alert_key = f"nvr_offline_{nvr['name'].replace(' ', '_')}"
                        resolve_alert(offline_alert_key)
                        print(f"   ✅ Alert resolved - NVR is back ONLINE")

                # ============================================================
                # SYNC STATUS (master NVRs only)
                # ============================================================
                if nvr_type == "master" and status == "online":
                    sync_alert_key = f"nvr_sync_{nvr['name'].replace(' ', '_')}"
                    
                    if sync_status == "lost":
                        # ✅ SYNC LOST - WARNING/CRITICAL ESCALATION
                        nvr_sync_failure_count[nvr["name"]] = nvr_sync_failure_count.get(nvr["name"], 0) + 1
                        sync_failures = nvr_sync_failure_count[nvr["name"]]
                        
                        # ✅ ESCALATION: 1 cycle = warning, 2+ cycles = critical
                        severity = "critical" if sync_failures >= 2 else "warning"
                        emoji = "🔴" if severity == "critical" else "⚠️"
                        
                        print(f"   {emoji} {severity.upper()}: Sync Lost | Cycle #{sync_failures}")
                        
                        # Send alert
                        if should_send_alert(sync_alert_key):
                            send_alert(
                                key=sync_alert_key,
                                title=f"NVR {nvr['name']} SYNC LOST",
                                message=f"NVR Master lost synchronization (Cycle: {sync_failures})",
                                alert_type="nvr",
                                severity=severity
                            )
                            if severity == "critical":
                                print(f"      🚨 Incident created automatically")

                    elif sync_status == "synced" and nvr_sync_failure_count.get(nvr["name"], 0) > 0:
                        # ✅ SYNC RESTORED
                        previous_losses = nvr_sync_failure_count[nvr["name"]]
                        nvr_sync_failure_count[nvr["name"]] = 0
                        print(f"   ✅ Sync RESTORED (was lost {previous_losses} cycle{'s' if previous_losses != 1 else ''})")
                        
                        # Resolve sync alert
                        resolve_alert(sync_alert_key)

                # ============================================================
                # DISK USAGE WARNING
                # ============================================================
                if disk_usage > 90:
                    disk_alert_key = f"nvr_disk_{nvr['name'].replace(' ', '_')}"
                    if should_send_alert(disk_alert_key):
                        send_alert(
                            key=disk_alert_key,
                            title=f"NVR {nvr['name']} Disk Full",
                            message=f"Disk usage: {disk_usage}%",
                            alert_type="nvr",
                            severity="warning"
                        )
                        print(f"   ⚠️  WARNING: Disk usage at {disk_usage}%")

                last_nvr_status[nvr["name"]] = status
                if nvr_type == "master":
                    last_nvr_sync[nvr["name"]] = sync_status

            else:
                print(f"❌ [{datetime.now().strftime('%H:%M:%S')}] {nvr['name']} → HTTP {response.status_code}")
                if response.text:
                    try:
                        error_data = response.json()
                        print(f"   Error: {error_data.get('message', 'Unknown error')}")
                    except:
                        print(f"   Response: {response.text}")

        except requests.exceptions.ConnectionError:
            print(f"❌ Connection Error: Could not reach API")
        except Exception as e:
            print(f"❌ Error [{nvr['name']}]: {str(e)}")

    print(f"\n⏱️  Next NVR check in 15 seconds...\n")
    time.sleep(15)