import random
import requests
import time
from datetime import datetime

BACKUPS_URL = "http://127.0.0.1:8000/api/backups"

servers = [
    "Active Directory",
    "SQL Server",
    "App Server",
]

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
                print(f"✅ [{datetime.now().strftime('%H:%M:%S')}] Backup [{server}] → {backup_status.upper()} | HTTP 201")
                
                # If failed, the API will automatically create an alert
                if backup_status == "failed":
                    print(f"   🚨 Alert automatically created for failed backup")
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
