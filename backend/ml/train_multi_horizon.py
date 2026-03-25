import pandas as pd
import xgboost as xgb
import mysql.connector
import joblib
import json
import numpy as np
from sklearn.metrics import mean_absolute_error, classification_report
from sklearn.preprocessing import RobustScaler
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
    df['day_of_week'] = df.index.dayofweek
    df['is_weekend'] = (df.index.dayofweek >= 5).astype(int)
    
    metrics = ['throughput_mbps', 'packet_rate', 'active_flows', 
               'latency_ms', 'retransmission_rate', 'packet_drop_rate', 
               'burstiness', 'flow_entropy']
               
    for col in metrics:
        df[f'{col}_lag1'] = df[col].shift(1)
        df[f'{col}_lag5'] = df[col].shift(5)
        df[f'{col}_velocity'] = df[col] - df[col].shift(1)
        
        # INCREASED FOCUS: Long-term vs Short-term context
        # Short-term (30s) context to capture immediate momentum
        df[f'{col}_roll30_mean'] = df[col].rolling(window=30, min_periods=1).mean()
        df[f'{col}_roll30_std'] = df[col].rolling(window=30, min_periods=1).std()
        
        # Stability (120s) context to anchor predictions (fixes 'bad spike' issues)
        df[f'{col}_roll120_mean'] = df[col].rolling(window=120, min_periods=1).mean()
        df[f'{col}_roll120_std'] = df[col].rolling(window=120, min_periods=1).std()
        
        # Baseline (5m) context to know the "normal" level
        df[f'{col}_roll300_mean'] = df[col].rolling(window=300, min_periods=1).mean()
        
    # Dense horizons (every second for the first minute) + long term anchors
    horizons = {f'{i}s': i for i in range(1, 61)}
    horizons.update({'5m': 300, '15m': 900, '60m': 3600})
    
    surge_threshold = df['throughput_mbps'].quantile(0.95)
    latency_threshold = df['latency_ms'].quantile(0.90)
    retrans_threshold = df['retransmission_rate'].quantile(0.90)
    
    for label, steps in horizons.items():
        for m in metrics:
            # Applying log1p transformation to stabilize variance-heavy metrics
            df[f'target_{m}_{label}'] = np.log1p(df[m].shift(-steps))
            
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
    X_raw_train, X_raw_test = X.iloc[:split_idx], X.iloc[split_idx:]
    
    # Feature Scaling: RobustScaler handles outliers (packet bursts) better than StandardScaler
    scaler = RobustScaler()
    X_train = scaler.fit_transform(X_raw_train)
    X_test = scaler.transform(X_raw_test)
    
    models = {}
    horizons = [f'{i}s' for i in range(1, 61)] + ['5m', '15m', '60m']
               
    print("\n" + "="*60)
    print("TRAINING 60 MULTI-HORIZON MODELS (All 8 Metrics)")
    print("="*60)
    
    eval_results = {}
    for h in horizons:
        print(f"\n{'='*20} Horizon: +{h} {'='*20}")
        eval_results[h] = {"regression_mae": {}, "classification_accuracy": {}}
        
        for m in metrics:
            target_col = f'target_{m}_{h}'
            y_train = df[target_col].iloc[:split_idx]
            y_test = df[target_col].iloc[split_idx:]
            
            # Optimized Regressor with Regularization
            model = xgb.XGBRegressor(
                n_estimators=100, 
                learning_rate=0.05, 
                max_depth=4, 
                subsample=0.8, 
                colsample_bytree=0.8,
                gamma=0.1,
                reg_lambda=1.5,
                reg_alpha=0.5,
                random_state=42
            )
            model.fit(X_train, y_train)
            
            if len(y_test) > 0:
                # Inverse transform predictions for real-world MAE comparison
                preds_log = model.predict(X_test)
                preds_real = np.expm1(preds_log)
                y_test_real = np.expm1(y_test)
                
                mae = mean_absolute_error(y_test_real, preds_real)
                print(f"  [{m.upper()}] MAE: {mae:.4f}")
                eval_results[h]["regression_mae"][m] = float(mae)
            models[f'{m}_{h}'] = model
            
        for c_target in ['surge', 'congestion']:
            target_col = f'target_{c_target}_{h}'
            y_train = df[target_col].iloc[:split_idx]
            y_test = df[target_col].iloc[split_idx:]
            
            pos_sum = max(0, sum(y_train))
            if pos_sum == 0 or pos_sum == len(y_train):
                # Fallback for empty or single-class targets (can happen with small datasets and high-density shifts)
                pos_weight = 1
            else:
                pos_weight = (len(y_train) - pos_sum) / pos_sum
            
            # Optimized Classifier with Regularization
            model = xgb.XGBClassifier(
                n_estimators=100, 
                learning_rate=0.05, 
                max_depth=4, 
                subsample=0.8,
                colsample_bytree=0.8,
                gamma=0.1,
                reg_lambda=1.5,
                reg_alpha=0.5,
                scale_pos_weight=pos_weight, 
                random_state=42
            )
            model.fit(X_train, y_train)
            
            if len(y_test) > 0:
                accuracy = model.score(X_test, y_test)
                print(f"  [{c_target.upper()}] Accuracy: {accuracy*100:.2f}%")
                eval_results[h]["classification_accuracy"][c_target] = float(accuracy)
            models[f'{c_target}_{h}'] = model

    script_dir = os.path.dirname(os.path.abspath(__file__))
    joblib.dump(models, os.path.join(script_dir, 'all_60_multi_horizon_models.pkl'))
    joblib.dump(feature_cols, os.path.join(script_dir, 'model_features.pkl'))
    joblib.dump(scaler, os.path.join(script_dir, 'scaler.pkl'))
    
    with open(os.path.join(script_dir, 'evaluation_results.json'), 'w') as f:
        json.dump(eval_results, f, indent=4)
        
    print(f"\nSuccess! All {len(models)} Multi-Horizon models saved into 'all_60_multi_horizon_models.pkl'.")
    print("Evaluation results saved to 'evaluation_results.json'.")

if __name__ == "__main__":
    train_all_multi_horizon()
