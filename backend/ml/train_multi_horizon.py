import pandas as pd
import xgboost as xgb
import mysql.connector
import joblib
from sklearn.metrics import mean_absolute_error, classification_report
import warnings
import os

warnings.filterwarnings('ignore', category=UserWarning)

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "networkDB"
}

def load_data():
    print("Loading high-resolution data from database...")
    conn = mysql.connector.connect(**DB_CONFIG)
    query = """
        SELECT metric_timestamp, throughput_mbps, packet_rate, active_flows, 
               latency_ms, retransmission_rate, packet_drop_rate, 
               burstiness, flow_entropy
        FROM network_metrics
        ORDER BY metric_timestamp ASC
    """
    df = pd.read_sql(query, conn)
    conn.close()
    
    df['metric_timestamp'] = pd.to_datetime(df['metric_timestamp'])
    df.set_index('metric_timestamp', inplace=True)
    return df

def engineer_multi_horizon_all(df):
    print("Engineering multi-horizon targets for ALL 8 metrics (+2s, +5s, +10s, +1m, +5m, +60m)...")
    
    df['hour'] = df.index.hour
    df['minute'] = df.index.minute
    
    metrics = ['throughput_mbps', 'packet_rate', 'active_flows', 
               'latency_ms', 'retransmission_rate', 'packet_drop_rate', 
               'burstiness', 'flow_entropy']
               
    for col in metrics:
        df[f'{col}_lag1'] = df[col].shift(1)
        df[f'{col}_lag5'] = df[col].shift(5)
        df[f'{col}_roll15_mean'] = df[col].rolling(window=15).mean()
        
    horizons = {'2s': 2, '5s': 5, '10s': 10, '1m': 60, '5m': 300, '60m': 3600}
    
    surge_threshold = df['throughput_mbps'].quantile(0.95)
    latency_threshold = df['latency_ms'].quantile(0.90)
    retrans_threshold = df['retransmission_rate'].quantile(0.90)
    
    for label, steps in horizons.items():
        for m in metrics:
            df[f'target_{m}_{label}'] = df[m].shift(-steps)
            
        df[f'target_surge_{label}'] = (df[f'target_throughput_mbps_{label}'] > surge_threshold).astype(int)
        df[f'target_congestion_{label}'] = ((df[f'target_latency_ms_{label}'] > latency_threshold) | 
                                            (df[f'target_retransmission_rate_{label}'] > retrans_threshold)).astype(int)
                                            
    df.dropna(inplace=True)
    return df, metrics

def train_all_multi_horizon():
    df = load_data()
    if df.empty:
        print("Not enough data to train. Exiting.")
        return

    try:
        df, metrics = engineer_multi_horizon_all(df)
    except Exception as e:
        print("Error engineering features:", e)
        return
        
    feature_cols = [c for c in df.columns if not c.startswith('target_')]
    X = df[feature_cols]
    
    split_idx = int(len(df) * 0.8)
    X_train, X_test = X.iloc[:split_idx], X.iloc[split_idx:]
    
    models = {}
    horizons = ['2s', '5s', '10s', '1m', '5m', '60m']
               
    print("\n" + "="*60)
    print("TRAINING 60 MULTI-HORIZON MODELS (All 8 Metrics)")
    print("="*60)
    
    for h in horizons:
        print(f"\n{'='*20} Horizon: +{h} {'='*20}")
        
        for m in metrics:
            target_col = f'target_{m}_{h}'
            y_train = df[target_col].iloc[:split_idx]
            y_test = df[target_col].iloc[split_idx:]
            
            model = xgb.XGBRegressor(n_estimators=100, learning_rate=0.05, max_depth=4, random_state=42)
            model.fit(X_train, y_train)
            
            if len(y_test) > 0:
                mae = mean_absolute_error(y_test, model.predict(X_test))
                print(f"  [{m.upper()}] MAE: {mae:.4f}")
            models[f'{m}_{h}'] = model
            
        for c_target in ['surge', 'congestion']:
            target_col = f'target_{c_target}_{h}'
            y_train = df[target_col].iloc[:split_idx]
            y_test = df[target_col].iloc[split_idx:]
            
            pos_sum = max(1, sum(y_train))
            pos_weight = (len(y_train) - pos_sum) / pos_sum
            model = xgb.XGBClassifier(n_estimators=100, learning_rate=0.05, max_depth=4, scale_pos_weight=pos_weight, random_state=42)
            model.fit(X_train, y_train)
            
            if len(y_test) > 0:
                accuracy = model.score(X_test, y_test)
                print(f"  [{c_target.upper()}] Accuracy: {accuracy*100:.2f}%")
            models[f'{c_target}_{h}'] = model

    script_dir = os.path.dirname(os.path.abspath(__file__))
    joblib.dump(models, os.path.join(script_dir, 'all_60_multi_horizon_models.pkl'))
    joblib.dump(feature_cols, os.path.join(script_dir, 'model_features.pkl'))
    print(f"\nSuccess! All {len(models)} Multi-Horizon models saved into 'all_60_multi_horizon_models.pkl'.")

if __name__ == "__main__":
    train_all_multi_horizon()
