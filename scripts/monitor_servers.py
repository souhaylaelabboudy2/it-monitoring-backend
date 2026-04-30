import psutil
import requests
import time
import random

API_URL = "http://127.0.0.1:8000/api/update-server"
ZABBIX_URL = "http://localhost:8090/api_jsonrpc.php"
INCIDENTS_URL = "http://127.0.0.1:8000/api/system/incident"
BACKUP_URL = "http://127.0.0.1:8000/api/update-backup"
NVR_URL = "http://127.0.0.1:8000/api/update-nvr"

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

            if status == "offline":
                requests.post(INCIDENTS_URL, json={
                    "title": f"{server['name']} is DOWN",
                    "description": "Server not responding",
                    "severity": "high"
                })
                print(f"🚨 Incident créé pour {server['name']}")

        except Exception as e:
            print(f"❌ Error [{server['name']}]: {e}")

    # ===== BACKUPS =====
    for server in servers:
        try:
            backup_status = random.choice(["success", "success", "failed"])

            requests.post(BACKUP_URL, json={
                "server_name": server["name"],
                "status": backup_status,
            })
            print(f"💾 Backup [{server['name']}] → {backup_status}")

            if backup_status == "failed":
                requests.post(INCIDENTS_URL, json={
                    "title": f"Backup FAILED for {server['name']}",
                    "description": "Backup job did not complete successfully",
                    "severity": "high"
                })
                print(f"🚨 Incident backup créé pour {server['name']}")

        except Exception as e:
            print(f"❌ Backup Error [{server['name']}]: {e}")

    # ===== NVR =====
    for nvr in nvrs:
        try:
            nvr_status = random.choice(["online", "online", "offline"])
            cameras = random.randint(5, 20)
            disk = random.randint(40, 95)

            requests.post(NVR_URL, json={
                "name": nvr["name"],
                "status": nvr_status,
                "cameras_count": cameras,
                "disk_usage": disk
            })
            print(f"📹 NVR [{nvr['name']}] → {nvr_status} | Cameras={cameras} Disk={disk}%")

            if nvr_status == "offline":
                requests.post(INCIDENTS_URL, json={
                    "title": f"NVR {nvr['name']} is OFFLINE",
                    "description": "NVR system not responding",
                    "severity": "high"
                })
                print(f"🚨 Incident NVR créé pour {nvr['name']}")

        except Exception as e:
            print(f"❌ NVR Error [{nvr['name']}]: {e}")

    time.sleep(10)