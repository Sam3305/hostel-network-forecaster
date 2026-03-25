import mysql.connector
import config
from config import DB_CONFIG

def get_connection():
    return mysql.connector.connect(**DB_CONFIG)

def insert_capture(file_name, start_time, duration, packet_count):
    """
    Inserts capture metadata into network_captures table.
    """
    conn = get_connection()
    cursor = conn.cursor()

    query = """
    INSERT INTO network_captures 
    (capture_timestamp, capture_file, capture_duration, packets_captured)
    VALUES (%s, %s, %s, %s)
    """
    
    try:
        cursor.execute(query, (start_time, file_name, duration, packet_count))
        conn.commit()
        capture_id = cursor.lastrowid
        return capture_id
    except Exception as e:
        print(f"Error inserting capture metadata: {e}")
        return None
    finally:
        cursor.close()
        conn.close()

def insert_metrics(capture_id, rows):
    """
    Inserts extracted metrics into network_metrics table.
    """
    if not rows:
        return

    conn = get_connection()
    cursor = conn.cursor()

    # Column mapping from feature_extractor output to DB table columns
    # Note: peak_hour is skipped if not in DB schema. 
    # Check if peak_hour exists in the table first or just include it and handle error.
    # Based on README, it's not there. I'll stick to the 8 metrics + timestamp + capture_id.
    
    query = """
    INSERT INTO network_metrics
    (capture_id, metric_timestamp, throughput_mbps, packet_rate, 
     active_flows, latency_ms, retransmission_rate, packet_drop_rate, 
     burstiness, flow_entropy)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """

    data = []
    for row in rows:
        data.append((
            capture_id,
            row['timestamp'],
            row['throughput'],
            row['packet_rate'],
            row['flows'],
            row['latency'],
            row['retrans_rate'],
            row['drop_rate'],
            row['burstiness'],
            row['entropy']
        ))

    try:
        cursor.executemany(query, data)
        conn.commit()
        print(f"  -> Successfully inserted {len(data)} metric rows.")
    except Exception as e:
        print(f"Error inserting metrics: {e}")
        # If failure is due to peak_hour missing, we are already safe as we don't include it.
        # But if the user WANTED it, they should have added the column.
    finally:
        cursor.close()
        conn.close()
