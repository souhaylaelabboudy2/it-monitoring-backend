import psutil
import requests
import time

API_URL = "http://127.0.0.1:8000/api/update-server"
ZABBIX_URL = "http://localhost:8090/api_jsonrpc.php"

servers = [
    {"name": "Active Directory", "ip": "127.0.0.1"},
    {"name": "SQL Server",       "ip": "127.0.0.1"},
    {"name": "App Server",       "ip": "127.0.0.1"},
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
        print(f"Zabbix login response: {res.text}")
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
        print(f"Zabbix host response: {res.text}")
        hosts = res.json().get("result", [])
        if hosts:
            print(f"✅ Zabbix → IP {ip} trouvée")
            return "online" if hosts[0]["status"] == "0" else "offline"
        else:
            print(f"⚠️ Zabbix → IP {ip} non trouvée, défaut online")
    except Exception as e:
        print(f"❌ Zabbix → erreur: {e}")
    return "online"
    

print("✅ Script monitor_servers démarré...")

while True:
    token = zabbix_login()

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

        except Exception as e:
            print(f"❌ Error [{server['name']}]: {e}")

    time.sleep(10)
    