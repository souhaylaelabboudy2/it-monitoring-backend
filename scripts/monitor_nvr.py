import random
import requests
import time
from datetime import datetime

API_URL = "http://127.0.0.1:8000/api/update-nvr"

nvrs = [
    {"name": "NVR Main"},
    {"name": "NVR Backup"}
]

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
                
                # Display alerts
                if status == "offline":
                    print(f"   🚨 ALERT: NVR is OFFLINE")
                if disk_usage > 90:
                    print(f"   ⚠️  ALERT: Disk full - {disk_usage}%")
                if nvr_type == "master" and sync_status == "lost":
                    print(f"   🚨 CRITICAL: Master NVR sync lost!")
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