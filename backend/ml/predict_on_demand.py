import pandas as pd
import mysql.connector
import joblib
import warnings
import os
import argparse
from datetime import datetime
import json

warnings.filterwarnings('ignore', category=UserWarning)

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "networkDB"
}

def get_historic_metrics(target_time_str):
    conn = mysql.connector.connect(**DB_CONFIG)
    query = """
        SELECT metric_timestamp, throughput_mbps, packet_rate, active_flows, 
               latency_ms, retransmission_rate, packet_drop_rate, 
               burstiness, flow_entropy
        FROM network_metrics
        WHERE metric_timestamp <= %s
        ORDER BY metric_timestamp DESC
        LIMIT 15
    """
    df = pd.read_sql(query, conn, params=(target_time_str,))
    conn.close()
    
    if df.empty or len(df) < 15:
        return None
        
    df = df.sort_values('metric_timestamp')
    df['metric_timestamp'] = pd.to_datetime(df['metric_timestamp'])
    df.set_index('metric_timestamp', inplace=True)
    return df

def feature_engineering(df):
    df['hour'] = df.index.hour
    df['minute'] = df.index.minute
    
    metrics = ['throughput_mbps', 'packet_rate', 'active_flows', 
               'latency_ms', 'retransmission_rate', 'packet_drop_rate', 
               'burstiness', 'flow_entropy']
               
    for col in metrics:
        df[f'{col}_lag1'] = df[col].shift(1)
        df[f'{col}_lag5'] = df[col].shift(5)
        df[f'{col}_roll15_mean'] = df[col].rolling(window=15).mean()
        
    return df.iloc[[-1]]

def run_on_demand(target_time_str):
    script_dir = os.path.dirname(os.path.abspath(__file__))
    mh_model_path = os.path.join(script_dir, 'all_60_multi_horizon_models.pkl')
    mh_features_path = os.path.join(script_dir, 'model_features.pkl')

    if not os.path.exists(mh_model_path):
        print(json.dumps({"error": "Models missing"}))
        return

    mh_models = joblib.load(mh_model_path)
    mh_features = joblib.load(mh_features_path)

    df_recent = get_historic_metrics(target_time_str)
    if df_recent is None:
        print(json.dumps({"error": "Not enough historical metrics prior to this time to run sliding window filters."}))
        return
        
    processed_row_mh = feature_engineering(df_recent)
    X_mh = processed_row_mh[mh_features]
    
    horizons = ['2s', '5s', '10s', '1m', '5m', '60m']
    output_payload = {}
    
    for h_name in horizons:
        try:
            pred_throughput = float(mh_models[f'throughput_mbps_{h_name}'].predict(X_mh)[0])
            pred_packet = float(mh_models[f'packet_rate_{h_name}'].predict(X_mh)[0])
            pred_flows = int(mh_models[f'active_flows_{h_name}'].predict(X_mh)[0])
            pred_latency = float(mh_models[f'latency_ms_{h_name}'].predict(X_mh)[0])
            pred_retrans = float(mh_models[f'retransmission_rate_{h_name}'].predict(X_mh)[0])
            pred_drop = float(mh_models[f'packet_drop_rate_{h_name}'].predict(X_mh)[0])
            pred_burst = float(mh_models[f'burstiness_{h_name}'].predict(X_mh)[0])
            pred_entropy = float(mh_models[f'flow_entropy_{h_name}'].predict(X_mh)[0])
            surge_proba = float(mh_models[f'surge_{h_name}'].predict_proba(X_mh)[0][1]) * 100
            congest_proba = float(mh_models[f'congestion_{h_name}'].predict_proba(X_mh)[0][1]) * 100
            
            output_payload[h_name] = {
                "predicted_throughput_mbps": pred_throughput,
                "predicted_packet_rate": pred_packet,
                "predicted_active_flows": pred_flows,
                "predicted_latency_ms": pred_latency,
                "predicted_retrans_rate": pred_retrans,
                "predicted_packet_drop_rate": pred_drop,
                "predicted_burstiness": pred_burst,
                "predicted_flow_entropy": pred_entropy,
                "surge_probability_pct": surge_proba,
                "congestion_risk_pct": congest_proba,
            }
        except Exception as e:
            pass
            
    print(json.dumps({
        "status": "success",
        "anchor_time": target_time_str,
        "predictions": output_payload
    }))

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Generate on-the-fly predictions for a specific timestamp.")
    parser.add_argument('--time', required=True, help="Target timestamp (YYYY-MM-DD HH:MM:SS)")
    args = parser.parse_args()
    
    run_on_demand(args.time)
