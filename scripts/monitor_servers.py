import psutil
import requests
import time
import random

API_URL = "http://127.0.0.1:8000/api/update-server"

servers = [
    {"name": "Server 1", "ip": "192.168.1.1"},
]

print("Script démarré...")
counter = 0

while True:
    for server in servers:
        try:
            # Simuler offline tous les 3 appels
            if counter % 3 == 2:
                status = "offline"
            else:
                status = "online"
            
            data = {
                "name": server["name"],
    "status": status,
    "cpu": psutil.cpu_percent(),
    "ram": psutil.virtual_memory().percent,
    "disk": psutil.disk_usage('C:\\').percent
            }
            r = requests.post(API_URL, json=data)
            print(f"{server['name']} updated - status: {r.status_code} - Server status: {status}")
        except Exception as e:
            print(f"Erreur: {e}")
    
    counter += 1
    time.sleep(10)