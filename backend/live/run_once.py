import os
import sys
import argparse
from datetime import datetime

# Add current and ML directory to path
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
sys.path.append(current_dir)
sys.path.append(parent_dir)

import feature_extractor
from feature_extractor import extract_features
import db_handler
from db_handler import insert_capture, insert_metrics

try:
    from ml.predict_worker import run_prediction
except ImportError:
    run_prediction = lambda: None

def run_once(pcap_path):
    if not os.path.exists(pcap_path):
        print(f"Error: File {pcap_path} not found.")
        return

    print(f"Processing existing capture: {pcap_path}")
    
    # Process the capture
    print(f"  -> Extracting features...")
    rows, duration, pkt_count = extract_features(pcap_path)
    
    if pkt_count > 0:
        print(f"  -> Processed {pkt_count} packets over {duration:.2f}s.")
        
        # Insert into DB
        # Use file modification time as starting point if possible, otherwise now
        mtime = os.path.getmtime(pcap_path)
        start_time = datetime.fromtimestamp(mtime)
        
        capture_id = insert_capture(os.path.basename(pcap_path), start_time, duration, pkt_count)
        
        if capture_id:
            insert_metrics(capture_id, rows)
            
            # Trigger predictions
            print("  -> Triggering ML predictions...")
            run_prediction()
            
            print("\nDone! Check your dashboard for the new data.")
        else:
            print("  -> Failed to record capture in database.")
    else:
        print("  -> No packets found in this file.")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Process a single PCAP file into the network forecaster DB.")
    parser.add_argument("pcap", help="Path to the .pcap file")
    args = parser.parse_args()
    
    run_once(args.pcap)