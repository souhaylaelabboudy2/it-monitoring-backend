import psutil
import requests
import time
import random

API_URL = "http://127.0.0.1:8000/api/update-server"
ZABBIX_URL = "http://localhost:8090/api_jsonrpc.php"
BACKUP_URL = "http://127.0.0.1:8000/api/update-backup"
NVR_URL = "http://127.0.0.1:8000/api/update-nvr"
ALERTS_URL = "http://127.0.0.1:8000/api/alerts"
RESOLVE_URL = "http://127.0.0.1:8000/api/alerts/resolve"
HEADERS = {"Content-Type": "application/json"}

# ✅ Tracking caches
last_backup_status = {}
last_backup_time = {}

last_nvr_status = {}
last_nvr_time = {}
last_nvr_sync = {}

last_server_status = {}  # Track server status for resolution
last_alert_time = {}  # Track alert cooldown (5 min)

servers = [
    {"name": "Active Directory", "ip": "127.0.0.1"},
    {"name": "SQL Server",       "ip": "127.0.0.1"},
    {"name": "App Server",       "ip": "127.0.0.1"},
]

nvrs = [
    {"name": "NVR 1"},
    {"name": "NVR Master"},
]

def zabbix_login():
    try:
        res = requests.post(ZABBIX_URL,
            json={
                "jsonrpc": "2.0",
                "method": "user.login",
                "params": {"user": "Admin", "password": "zabbix"},
                "id": 1
            },
            headers={"Content-Type": "application/json"}
        )
        return res.json().get("result")
    except Exception as e:
        print(f"Login error: {e}")
        return None


def get_host_status(token, ip):
    try:
        res = requests.post(ZABBIX_URL,
            json={
                "jsonrpc": "2.0",
                "method": "host.get",
                "params": {
                    "output": ["hostid", "host", "status"],
                    "filter": {"ip": ip}
                },
                "auth": token,
                "id": 2
            },
            headers={"Content-Type": "application/json"}
        )
        hosts = res.json().get("result", [])
        if hosts:
            return "online" if hosts[0]["status"] == "0" else "offline"
        else:
            return "online"
    except:
        return "online"


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


print("✅ Script monitor_servers démarré...")

while True:
    token = zabbix_login()

    # ===== SERVERS =====
    for server in servers:
        try:
            status = get_host_status(token, server["ip"]) if token else "online"

            data = {
                "name": server["name"],
                "ip_address": server["ip"],
                "status": status,
                "cpu": psutil.cpu_percent(interval=1),
                "ram": psutil.virtual_memory().percent,
                "disk": psutil.disk_usage('C:\\').percent
            }

            r = requests.post(API_URL, json=data)
            print(f"[{server['name']}] {status} | CPU={data['cpu']}% RAM={data['ram']}% HTTP {r.status_code}")

            # Alert system for server down
            alert_key = f"server_down_{server['name'].replace(' ', '_')}"
            last_status = last_server_status.get(server['name'])

            if status == "offline":
                # Server is down - send alert if cooldown expired
                if should_send_alert(alert_key):
                    send_alert(
                        key=alert_key,
                        title=f"{server['name']} is DOWN",
                        message="Server not responding to health checks",
                        alert_type="server",
                        severity="critical"
                    )
                    print(f"🚨 Alert: {server['name']} is DOWN")
            else:
                # Server is online - resolve alert if it was previously down
                if last_status == "offline":
                    resolve_alert(alert_key)
                    print(f"✅ Resolved: {server['name']} is back ONLINE")

            last_server_status[server['name']] = status

        except Exception as e:
            print(f"❌ Error [{server['name']}]: {e}")

    # ===== BACKUPS =====
    for server in servers:
        try:
            backup_status = random.choice(["success", "success", "failed"])
            current_time = time.time()

            if (
                last_backup_status.get(server["name"]) != backup_status
                or current_time - last_backup_time.get(server["name"], 0) > 300
            ):
                requests.post(BACKUP_URL, json={
                    "server_name": server["name"],
                    "status": backup_status,
                }, headers=HEADERS)
                print(f"💾 Backup [{server['name']}] → {backup_status}")

                # Alert system for backup failed
                alert_key = f"backup_failed_{server['name'].replace(' ', '_')}"

                if backup_status == "failed":
                    if should_send_alert(alert_key):
                        send_alert(
                            key=alert_key,
                            title=f"Backup FAILED for {server['name']}",
                            message="Backup job did not complete successfully",
                            alert_type="backup",
                            severity="critical"
                        )
                        print(f"🚨 Alert: Backup failed for {server['name']}")
                else:
                    # Backup succeeded - resolve alert if it was previously failed
                    if last_backup_status.get(server["name"]) == "failed":
                        resolve_alert(alert_key)
                        print(f"✅ Resolved: Backup succeeded for {server['name']}")

                last_backup_status[server["name"]] = backup_status
                last_backup_time[server["name"]] = current_time

        except Exception as e:
            print(f"❌ Backup Error [{server['name']}]: {e}")

    # ===== NVR =====
    for nvr in nvrs:
        try:
            nvr_status = random.choice(["online", "online", "offline"])
            cameras = random.randint(5, 20)
            disk = random.randint(40, 95)
            current_time = time.time()

            nvr_type = "master" if "Master" in nvr["name"] else "standard"
            
            # Only assign sync_status for master NVRs
            if nvr_type == "master":
                sync_status = "lost" if random.randint(0, 4) == 2 else "synced"
            else:
                sync_status = None

            # Build data payload - only include sync_status for master
            data = {
                "name": nvr["name"],
                "status": nvr_status,
                "cameras_count": cameras,
                "disk_usage": disk,
                "type": nvr_type,
            }
            if nvr_type == "master":
                data["sync_status"] = sync_status

            # Send if: status changed OR 5min passed OR sync_status changed (master only)
            status_changed = last_nvr_status.get(nvr["name"]) != nvr_status
            time_passed = current_time - last_nvr_time.get(nvr["name"], 0) > 300
            sync_changed = (nvr_type == "master" and last_nvr_sync.get(nvr["name"]) != sync_status)

            if status_changed or time_passed or sync_changed:
                requests.post(NVR_URL, json=data, headers=HEADERS)
                print(f"📹 NVR [{nvr['name']}] → {nvr_status} | Type={nvr_type} Sync={sync_status} | Cameras={cameras} Disk={disk}%")

                # Alert system for NVR offline
                offline_alert_key = f"nvr_offline_{nvr['name'].replace(' ', '_')}"
                last_status = last_nvr_status.get(nvr["name"])

                if nvr_status == "offline":
                    if should_send_alert(offline_alert_key):
                        send_alert(
                            key=offline_alert_key,
                            title=f"NVR {nvr['name']} is OFFLINE",
                            message="NVR system not responding",
                            alert_type="nvr",
                            severity="critical"
                        )
                        print(f"🚨 Alert: {nvr['name']} is OFFLINE")
                else:
                    # NVR is online - resolve offline alert if it was previously down
                    if last_status == "offline":
                        resolve_alert(offline_alert_key)
                        print(f"✅ Resolved: {nvr['name']} is back ONLINE")

                # Alert system for NVR Master sync lost
                if nvr_type == "master":
                    sync_alert_key = f"nvr_sync_{nvr['name'].replace(' ', '_')}"
                    last_sync = last_nvr_sync.get(nvr["name"])

                    if sync_status == "lost":
                        if should_send_alert(sync_alert_key):
                            send_alert(
                                key=sync_alert_key,
                                title=f"NVR {nvr['name']} SYNC LOST",
                                message="NVR Master lost synchronization",
                                alert_type="nvr",
                                severity="warning"
                            )
                            print(f"🚨 Alert: {nvr['name']} sync LOST")
                    else:
                        # Sync is back - resolve alert if it was previously lost
                        if last_sync == "lost":
                            resolve_alert(sync_alert_key)
                            print(f"✅ Resolved: {nvr['name']} sync RESTORED")

                # Update all tracking caches
                last_nvr_status[nvr["name"]] = nvr_status
                last_nvr_time[nvr["name"]] = current_time
                if nvr_type == "master":
                    last_nvr_sync[nvr["name"]] = sync_status

        except Exception as e:
            print(f"❌ NVR Error [{nvr['name']}]: {e}")

    print("------ LOOP END ------")
    time.sleep(60)