import psutil
import requests
import time

API_URL = "http://127.0.0.1:8000/api/update-server"

servers = [
    {"name": "Server 1", "ip": "192.168.1.1"},
]

print("Script démarré...")

while True:
    for server in servers:
        try:
            data = {
                "name": server["name"],
                "status": "online",
                "cpu": psutil.cpu_percent(),
                "ram": psutil.virtual_memory().percent,
                "disk": psutil.disk_usage('/').percent
            }
            r = requests.post(API_URL, json=data)
            print(f"{server['name']} updated - status: {r.status_code}")
        except Exception as e:
            print(f"Erreur: {e}")
    time.sleep(10)