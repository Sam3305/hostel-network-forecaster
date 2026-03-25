import pandas as pd
import mysql.connector
import joblib
import numpy as np
import warnings
import os
from datetime import datetime, timedelta

warnings.filterwarnings('ignore', category=UserWarning)

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "networkDB"
}

def get_latest_metrics():
    conn = mysql.connector.connect(**DB_CONFIG)
    query = """
        SELECT metric_timestamp, throughput_mbps, packet_rate, active_flows, 
               latency_ms, retransmission_rate, packet_drop_rate, 
               burstiness, flow_entropy
        FROM network_metrics
        ORDER BY metric_timestamp DESC
        LIMIT 15
    """
    df = pd.read_sql(query, conn)
    conn.close()
    
    if df.empty:
        return None
        
    df = df.sort_values('metric_timestamp')
    df['metric_timestamp'] = pd.to_datetime(df['metric_timestamp'])
    df.set_index('metric_timestamp', inplace=True)
    return df

def feature_engineering(df):
    df['hour'] = df.index.hour
    df['minute'] = df.index.minute
    df['day_of_week'] = df.index.dayofweek
    df['is_weekend'] = (df.index.dayofweek >= 5).astype(int)
    
    metrics = ['throughput_mbps', 'packet_rate', 'active_flows', 
               'latency_ms', 'retransmission_rate', 'packet_drop_rate', 
               'burstiness', 'flow_entropy']
               
    for col in metrics:
        df[f'{col}_lag1'] = df[col].shift(1)
        df[f'{col}_lag5'] = df[col].shift(5)
        df[f'{col}_velocity'] = df[col] - df[col].shift(1)
        
        # Consistent Features: matching train_multi_horizon.py
        df[f'{col}_roll30_mean'] = df[col].rolling(window=30, min_periods=1).mean()
        df[f'{col}_roll30_std'] = df[col].rolling(window=30, min_periods=1).std()
        
        df[f'{col}_roll120_mean'] = df[col].rolling(window=120, min_periods=1).mean()
        df[f'{col}_roll120_std'] = df[col].rolling(window=120, min_periods=1).std()
        
        df[f'{col}_roll300_mean'] = df[col].rolling(window=300, min_periods=1).mean()
        
    return df.iloc[[-1]]

def run_prediction():
    print("Running prediction worker for ALL horizons...")
    script_dir = os.path.dirname(os.path.abspath(__file__))
    
    mh_model_path = os.path.join(script_dir, 'all_60_multi_horizon_models.pkl')
    mh_features_path = os.path.join(script_dir, 'model_features.pkl')

    if not os.path.exists(mh_model_path):
        print("Required Models not trained yet. Exiting.")
        return

    mh_models = joblib.load(mh_model_path)
    mh_features = joblib.load(mh_features_path)
    scaler = joblib.load(os.path.join(script_dir, 'scaler.pkl'))

    df_recent = get_latest_metrics()
    if df_recent is None or len(df_recent) < 15:
        print("Not enough historical metrics to perform prediction.")
        return
        
    last_timestamp = df_recent.index[-1]
    
    processed_row_mh = feature_engineering(df_recent)
    X_mh_raw = processed_row_mh[mh_features]
    X_mh = scaler.transform(X_mh_raw)

    # Dense horizons (every second for the first minute) + long term anchors
    horizons = {f'{i}s': i for i in range(1, 61)}
    horizons.update({'5m': 300, '15m': 900, '60m': 3600})
    
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor()
    conn.autocommit = False
    
    insert_query = """
        INSERT INTO forecast_results (
            created_at, target_time, predicted_throughput_mbps, predicted_packet_rate, 
            surge_probability_pct, congestion_risk_pct, predicted_latency_ms, 
            predicted_active_flows, predicted_retrans_rate, predicted_burstiness
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """
    
    batch_values = []
    for h_name, h_secs in horizons.items():
        try:
            # Predictions are logged; apply expm1 to restore real values
            pred_throughput = float(np.expm1(mh_models[f'throughput_mbps_{h_name}'].predict(X_mh)[0]))
            pred_packet = float(np.expm1(mh_models[f'packet_rate_{h_name}'].predict(X_mh)[0]))
            pred_flows = int(np.expm1(mh_models[f'active_flows_{h_name}'].predict(X_mh)[0]))
            pred_latency = float(np.expm1(mh_models[f'latency_ms_{h_name}'].predict(X_mh)[0]))
            pred_retrans = float(np.expm1(mh_models[f'retransmission_rate_{h_name}'].predict(X_mh)[0]))
            pred_burst = float(np.expm1(mh_models[f'burstiness_{h_name}'].predict(X_mh)[0]))
            
            surge_proba = float(mh_models[f'surge_{h_name}'].predict_proba(X_mh)[0][1]) * 100
            congest_proba = float(mh_models[f'congestion_{h_name}'].predict_proba(X_mh)[0][1]) * 100
                
            target_time = last_timestamp + timedelta(seconds=h_secs)
            tag_created_at = target_time - timedelta(seconds=h_secs)
            
            values = (
                tag_created_at.strftime('%Y-%m-%d %H:%M:%S'),
                target_time.strftime('%Y-%m-%d %H:%M:%S'),
                pred_throughput, pred_packet, surge_proba, congest_proba, 
                pred_latency, pred_flows, pred_retrans, pred_burst
            )
            batch_values.append(values)
            
        except Exception as e:
            print(f"Error during inference for horizon {h_name}:", e)
    
    if batch_values:
        cursor.executemany(insert_query, batch_values)
        print(f"Successfully generated and inserted {len(batch_values)} prediction horizons.")
    
    conn.commit()
    cursor.close()
    conn.close()
    print("All multi-horizon predictions inserted.")

if __name__ == "__main__":
    run_prediction()
