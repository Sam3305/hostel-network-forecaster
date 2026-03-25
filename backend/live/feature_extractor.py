import pyshark
import numpy as np
from collections import Counter
from math import log2
import pandas as pd
import config
from config import WINDOW_SIZE, SLIDE_STEP

def compute_entropy(flows):
    if not flows: return 0
    counts = Counter(flows)
    total = sum(counts.values())
    probs = [c/total for c in counts.values()]
    return -sum(p * log2(p) for p in probs)

def extract_features(pcap_file):
    """
    Extracts network metrics from a PCAP file using sliding windows.
    Adapted from user provided code.
    """
    try:
        capture = pyshark.FileCapture(pcap_file)
    except Exception as e:
        print(f"Error opening capture file {pcap_file}: {e}")
        return [], 0, 0
    
    all_packets = [] 
    rows = []
    error_count = 0
    
    initial_time = None
    last_ts = None
    total_packets_processed = 0

    # Test if capture is readable
    try:
        for pkt in capture:
            try:
                ts = float(pkt.sniff_timestamp)
                last_ts = ts
                total_packets_processed += 1
                
                if initial_time is None: 
                    initial_time = ts
                    next_window_end = initial_time + WINDOW_SIZE

                pkt_data = {
                    'ts': ts,
                    'size': int(pkt.length),
                    'is_tcp': 'TCP' in pkt,
                    'retrans': hasattr(pkt.tcp, 'analysis_retransmission') if 'TCP' in pkt else False,
                    'lost': hasattr(pkt.tcp, 'analysis_lost_segment') if 'TCP' in pkt else False,
                    'rtt': float(pkt.tcp.analysis_ack_rtt) if ('TCP' in pkt and hasattr(pkt.tcp, 'analysis_ack_rtt')) else None,
                    'flow': (pkt.ip.src, pkt.ip.dst, pkt.transport_layer) if 'IP' in pkt else None
                }
                all_packets.append(pkt_data)

                # Sliding Window logic
                while ts >= next_window_end:
                    window_start = next_window_end - WINDOW_SIZE
                    window_end = next_window_end
                    
                    window_pkts = [p for p in all_packets if window_start <= p['ts'] < window_end]
                    
                    if window_pkts:
                        tcp_pkts = [p for p in window_pkts if p['is_tcp']]
                        rtts = [p['rtt'] for p in window_pkts if p['rtt'] is not None]
                        flows = [p['flow'] for p in window_pkts if p['flow'] is not None]
                        
                        total_bytes = sum(p['size'] for p in window_pkts)
                        retrans_count = sum(1 for p in window_pkts if p['retrans'])
                        lost_count = sum(1 for p in window_pkts if p['lost'])
                        
                        throughput = (total_bytes * 8) / (WINDOW_SIZE * 1e6)
                        packet_rate = len(window_pkts) / WINDOW_SIZE
                        active_flows = len(set(flows))
                        latency = np.mean(rtts) if rtts else 0
                        retrans_rate = retrans_count / len(tcp_pkts) if tcp_pkts else 0
                        drop_rate = lost_count / len(tcp_pkts) if tcp_pkts else 0
                        
                        sec_bins = Counter(int(p['ts'] - window_start) for p in window_pkts)
                        burstiness = np.var(list(sec_bins.values())) if sec_bins else 0
                        
                        entropy = compute_entropy(flows)
                        dt = pd.to_datetime(window_start, unit="s").tz_localize('UTC').tz_convert('Asia/Kolkata')
                        hour = dt.hour
                        peak_hour = 1 if 18 <= hour <= 23 else 0

                        rows.append({
                            "timestamp": dt.strftime('%Y-%m-%d %H:%M:%S'),
                            "throughput": throughput,
                            "packet_rate": packet_rate,
                            "flows": active_flows,
                            "latency": latency,
                            "retrans_rate": retrans_rate,
                            "drop_rate": drop_rate,
                            "burstiness": burstiness,
                            "entropy": entropy,
                            "peak_hour": peak_hour
                        })

                    next_window_end += SLIDE_STEP
                    
                    oldest_allowed = next_window_end - WINDOW_SIZE
                    all_packets = [p for p in all_packets if p['ts'] >= oldest_allowed] # cleaning memory by removing older packets 

            except AttributeError:
                error_count += 1
            except Exception as e:
                error_count += 1
                # print(f"Error processing packet: {e}")
    finally:
        capture.close()

    total_duration = (last_ts - initial_time) if (initial_time and last_ts) else 0.0
    
    if error_count > 0:
        print(f"  -> Warning: Skipped {error_count} malformed packets.")

    return rows, total_duration, total_packets_processed
