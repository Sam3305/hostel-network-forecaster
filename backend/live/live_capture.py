import os
import time
import subprocess
from datetime import datetime
import sys

# Add current and ML directory to path
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
sys.path.append(current_dir)
sys.path.append(parent_dir)

# Import our modules
import config
from config import CAPTURE_INTERFACE, CAPTURE_DURATION, PCAP_DIR
import feature_extractor
from feature_extractor import extract_features
import db_handler
from db_handler import insert_capture, insert_metrics
from ml.predict_worker import run_prediction

def capture_live():
    print("Starting Live Network Capture...")
    print(f"Interface: {CAPTURE_INTERFACE}")
    print(f"Window Duration: {CAPTURE_DURATION}s")
    print(f"PCAP Directory: {PCAP_DIR}")
    print("Press Ctrl+C to stop.")
    
    # Ensure tshark is available
    
    try:
        subprocess.run(["tshark", "-v"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except FileNotFoundError:
        print("Error: 'tshark' not found")
        return

    try:
        while True:
            # Generate filename with timestamp (YYYY-MM-DD_HH-MM-SS)
            timestamp_str = datetime.now().strftime("%Y-%m-%d_%H-%M-%S")
            pcap_file = os.path.join(PCAP_DIR, f"capture_{timestamp_str}.pcap")
            
            print(f"\n[{datetime.now().strftime('%H:%M:%S')}] Capturing for {CAPTURE_DURATION}s...")
            
            # Start tshark capture
            # -i: interface, -a: autostop duration, -w: write to file
            try:
                subprocess.run([
                    "tshark", 
                    "-i", CAPTURE_INTERFACE, 
                    "-a", f"duration:{CAPTURE_DURATION}", 
                    "-w", pcap_file
                ], check=True, capture_output=True)
            except subprocess.CalledProcessError as e:
                print(f"Capture failed: {e.stderr.decode()}")
                time.sleep(5)
                continue

            # Process the capture
            print(f"  -> Extracting features from {os.path.basename(pcap_file)}...")
            rows, duration, pkt_count = extract_features(pcap_file)
            
            if pkt_count > 0:
                print(f"  -> Captured {pkt_count} packets over {duration:.2f}s.")
                
                # Insert into DB
                start_time = datetime.now() # Approximate
                capture_id = insert_capture(os.path.basename(pcap_file), start_time, duration, pkt_count)
                
                if capture_id:
                    insert_metrics(capture_id, rows)
                    
                    # Trigger predictions
                    print("  -> Triggering ML predictions...")
                    run_prediction()
                else:
                    print("  -> Failed to record capture in database. Skipping metrics.")
            else:
                print("  -> No packets captured in this window.")
                # Clean up empty file
                if os.path.exists(pcap_file):
                    os.remove(pcap_file)

            # Short sleep if capture is short
            time.sleep(1)

    except KeyboardInterrupt:
        print("\nStopping live capture...")
    except Exception as e:
        print(f"\nUnexpected error: {e}")

if __name__ == "__main__":
    capture_live()
