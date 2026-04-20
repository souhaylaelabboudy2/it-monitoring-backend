import random
import requests
import time

API_URL = "http://127.0.0.1:8000/api/update-nvr"

nvrs = [
    {"name": "NVR 1"},
    {"name": "NVR Master"}
]

while True:
    for nvr in nvrs:
        status = random.choice(["online", "offline"])
        cameras = random.randint(5, 20)
        disk = random.randint(40, 95)

        data = {
            "name": nvr["name"],
            "status": status,
            "cameras": cameras,
            "disk": disk
        }

        try:
            requests.post(API_URL, json=data)
            print(f"{nvr['name']} updated")
        except:
            print("Error")

    time.sleep(15)