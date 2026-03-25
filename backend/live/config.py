import os

# Database Connection
DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "networkDB"
}


CAPTURE_INTERFACE = r"\Device\NPF_{C6B39D64-3A98-47EB-A9D6-2303D886675E}" 
CAPTURE_DURATION = 10  # Seconds per capture window

# Feature Extraction Settings
WINDOW_SIZE = 5   # Analysis window in seconds
SLIDE_STEP = 1    # Step size for sliding window

# Directory paths
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PCAP_DIR = os.path.join(BASE_DIR, "backend", "live", "captures")

# Ensure the capture directory exists
if not os.path.exists(PCAP_DIR):
    os.makedirs(PCAP_DIR)
