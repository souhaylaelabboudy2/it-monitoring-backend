import psutil
import requests
import time

API_URL = "http://127.0.0.1:8000/api/update-server"
ZABBIX_URL = "http://localhost:8090/api_jsonrpc.php"
BACKUP_URL = "http://127.0.0.1:8000/api/update-backup"
NVR_URL = "http://127.0.0.1:8000/api/update-nvr"
ALERTS_URL = "http://127.0.0.1:8000/api/alerts"
RESOLVE_URL = "http://127.0.0.1:8000/api/alerts/resolve"
LOGS_URL = "http://127.0.0.1:8000/api/logs"
HEADERS = {"Content-Type": "application/json"}

# ✅ Tracking caches
last_backup_status = {}
last_backup_time = {}
backup_failure_count = {}  # Track consecutive failures

last_nvr_status = {}
last_nvr_time = {}
last_nvr_sync = {}
nvr_uptime = {}  # Track NVR uptime in seconds

last_server_status = {}  # Track server status for resolution
server_warning_time = {}  # Track when server entered warning state
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


def determine_severity(cpu, ram):
    """
    Determine severity based on resource usage
    - Critical: CPU > 90% OR RAM > 95%
    - Warning: CPU > 85% OR RAM > 90%
    - Info: Everything else
    """
    if cpu > 90 or ram > 95:
        return "critical"
    elif cpu > 85 or ram > 90:
        return "warning"
    else:
        return "info"


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
            cpu = psutil.cpu_percent(interval=1)
            ram = psutil.virtual_memory().percent
            disk = psutil.disk_usage('C:\\').percent

            # Determine severity based on resource usage
            severity = determine_severity(cpu, ram)

            data = {
                "name": server["name"],
                "ip_address": server["ip"],
                "status": status,
                "cpu": cpu,
                "ram": ram,
                "disk": disk
            }

            r = requests.post(API_URL, json=data)
            status_display = f"[{server['name']}] {status.upper()} | CPU={cpu}% RAM={ram}% HTTP {r.status_code}"
            
            # Show severity indicator
            if severity == "critical":
                status_display += " 🔴 CRITICAL"
            elif severity == "warning":
                status_display += " 🟡 WARNING"
            
            print(status_display)

            # Alert system for server down
            alert_key = f"server_down_{server['name'].replace(' ', '_')}"
            last_status = last_server_status.get(server['name'])

            if status == "offline":
                # Server is down - send alert if cooldown expired
                if should_send_alert(alert_key):
                    send_alert(
                        key=alert_key,
                        title=f"{server['name']} is OFFLINE",
                        message="Server not responding to health checks",
                        alert_type="server",
                        severity="critical"
                    )
                    print(f"🚨 Alert: {server['name']} is OFFLINE")
            else:
                # Server is online - resolve alert if it was previously down
                if last_status == "offline":
                    resolve_alert(alert_key)
                    print(f"✅ Resolved: {server['name']} is back ONLINE")

            # Alert system for high resource usage
            if severity in ["warning", "critical"]:
                resource_alert_key = f"server_resources_{server['name'].replace(' ', '_')}"
                msg = f"CPU: {cpu}% | RAM: {ram}%"
                
                if should_send_alert(resource_alert_key):
                    send_alert(
                        key=resource_alert_key,
                        title=f"{server['name']} High Resource Usage",
                        message=msg,
                        alert_type="server",
                        severity=severity
                    )
                    print(f"⚠️ Alert: {server['name']} resource usage high")
            else:
                # Resource usage normal - resolve if previously warned
                resource_alert_key = f"server_resources_{server['name'].replace(' ', '_')}"
                if last_alert_time.get(resource_alert_key, 0) > 0:
                    resolve_alert(resource_alert_key)

            last_server_status[server['name']] = status

        except Exception as e:
            print(f"❌ Error [{server['name']}]: {e}")

    # ===== BACKUPS =====
    for server in servers:
        try:
            current_time = time.time()
            
            # Realistic backup logic:
            # - Backups succeed most of the time
            # - Occasionally fail (simulating real-world scenarios)
            # - Only send update if status changed or 5+ minutes passed
            
            last_time = last_backup_time.get(server["name"], 0)
            time_since_last = current_time - last_time
            
            # Determine if we should simulate a backup failure
            # Pattern: Fail on specific iteration cycles to simulate occasional failures
            loop_count = int(current_time / 60) % 10  # Every 10 minutes cycle
            
            if loop_count in [3, 8]:  # Fail at cycle 3 and 8 (simulates occasional failures)
                backup_status = "failed"
            else:
                backup_status = "success"
            
            # Only send if status changed or 5+ minutes passed
            if (
                last_backup_status.get(server["name"]) != backup_status
                or time_since_last > 300
            ):
                requests.post(BACKUP_URL, json={
                    "server_name": server["name"],
                    "status": backup_status,
                }, headers=HEADERS)

                # Determine severity
                if backup_status == "failed":
                    # Track consecutive failures
                    backup_failure_count[server["name"]] = backup_failure_count.get(server["name"], 0) + 1
                    consecutive_failures = backup_failure_count[server["name"]]
                    
                    # Severity based on consecutive failures
                    severity = "critical" if consecutive_failures >= 2 else "warning"
                    
                    print(f"💾 Backup [{server['name']}] → FAILED (consecutive: {consecutive_failures}) {severity.upper()}")
                    
                    # Send alert
                    alert_key = f"backup_failed_{server['name'].replace(' ', '_')}"
                    if should_send_alert(alert_key):
                        send_alert(
                            key=alert_key,
                            title=f"Backup FAILED for {server['name']}",
                            message=f"Backup job did not complete successfully (failed {consecutive_failures} time(s))",
                            alert_type="backup",
                            severity=severity
                        )
                        print(f"🚨 Alert: Backup failed for {server['name']}")
                else:
                    # Backup succeeded
                    print(f"💾 Backup [{server['name']}] → SUCCESS")
                    
                    # Reset failure count on success
                    if backup_failure_count.get(server["name"], 0) > 0:
                        backup_failure_count[server["name"]] = 0
                        
                        # Resolve alert if it was previously failed
                        alert_key = f"backup_failed_{server['name'].replace(' ', '_')}"
                        resolve_alert(alert_key)
                        print(f"✅ Resolved: Backup succeeded for {server['name']}")

                last_backup_status[server["name"]] = backup_status
                last_backup_time[server["name"]] = current_time

        except Exception as e:
            print(f"❌ Backup Error [{server['name']}]: {e}")

    # ===== NVR =====
    for nvr in nvrs:
        try:
            current_time = time.time()
            nvr_type = "master" if "Master" in nvr["name"] else "standard"
            
            # Initialize uptime tracking
            if nvr["name"] not in nvr_uptime:
                nvr_uptime[nvr["name"]] = 0
            
            # Realistic NVR logic:
            # - NVRs stay online for extended periods
            # - Occasionally go offline (simulating network issues, maintenance, etc.)
            # - Standard NVRs have no sync status
            # - Master NVRs have sync status
            
            loop_count = int(current_time / 60) % 20  # Every 20 minutes cycle
            
            # Determine NVR status - offline only at specific cycle points
            if loop_count == 12:  # Simulate offline at cycle 12
                nvr_status = "offline"
            else:
                nvr_status = "online"
            
            # Track uptime
            if nvr_status == "online":
                nvr_uptime[nvr["name"]] += 60
            else:
                nvr_uptime[nvr["name"]] = 0
            
            # Determine disk usage based on realistic pattern
            # Master NVRs typically have higher disk usage than standard
            base_disk = 75 if nvr_type == "master" else 50
            # Simulate gradual increase with occasional variations
            cycle_variation = (loop_count * 2) % 15
            disk_usage = min(99, base_disk + cycle_variation)
            
            # Camera count - realistic values
            cameras = 16 if nvr_type == "master" else 8
            
            # Determine sync status for master NVRs
            if nvr_type == "master":
                # Master sync is lost occasionally (simulating sync issues)
                if loop_count in [7, 18]:
                    sync_status = "lost"
                else:
                    sync_status = "synced"
            else:
                sync_status = None
            
            # Build data payload
            data = {
                "name": nvr["name"],
                "status": nvr_status,
                "cameras_count": cameras,
                "disk_usage": disk_usage,
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
                
                status_display = f"📹 NVR [{nvr['name']}] → {nvr_status.upper()} | Type={nvr_type} Cameras={cameras} Disk={disk_usage}%"
                if nvr_type == "master":
                    status_display += f" Sync={sync_status.upper()}"
                print(status_display)
                
                # Alert system for NVR offline
                offline_alert_key = f"nvr_offline_{nvr['name'].replace(' ', '_')}"
                last_status = last_nvr_status.get(nvr["name"])
                
                if nvr_status == "offline":
                    if should_send_alert(offline_alert_key):
                        send_alert(
                            key=offline_alert_key,
                            title=f"NVR {nvr['name']} OFFLINE",
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
                            print(f"⚠️ Alert: {nvr['name']} sync LOST")
                    else:
                        # Sync is back - resolve alert if it was previously lost
                        if last_sync == "lost":
                            resolve_alert(sync_alert_key)
                            print(f"✅ Resolved: {nvr['name']} sync RESTORED")
                
                # Alert for disk usage > 90%
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
                        print(f"⚠️ Alert: {nvr['name']} disk usage critical")
                
                # Update all tracking caches
                last_nvr_status[nvr["name"]] = nvr_status
                last_nvr_time[nvr["name"]] = current_time
                if nvr_type == "master":
                    last_nvr_sync[nvr["name"]] = sync_status
        
        except Exception as e:
            print(f"❌ NVR Error [{nvr['name']}]: {e}")

    print("------ LOOP END ------")
    time.sleep(60)