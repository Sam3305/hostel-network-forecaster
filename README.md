# 🌐 Hostel Network Forecaster

An AI-powered network traffic intelligence dashboard that uses **XGBoost** machine learning to predict campus network behaviour across multiple time horizons. Built for real-time monitoring, historical analysis, and proactive congestion avoidance.

---

## 📂 Project Structure

```
hostel-network-forecaster/
├── frontend/                    # UI Layer
│   ├── index.html               # Public dashboard (no login required)
│   ├── login.php                # Authentication page
│   ├── user.php                 # User dashboard (authenticated)
│   ├── admin.php                # Admin control panel
│   ├── script.js                # All chart rendering + interaction logic
│   └── style.css                # Cyberpunk-themed styling
│
├── api/                         # PHP API Layer
│   ├── auth.php                 # Login / logout / session management
│   ├── get_metrics.php          # Fetch historical network metrics
│   ├── get_predictions.php      # Fetch stored ML predictions from DB
│   ├── get_dynamic_predictions.php  # On-demand ML inference (calls Python)
│   ├── get_capture_timeline.php # Timeline slider data + heatmap intensities
│   ├── admin_users.php          # User management API
│   ├── admin_system.php         # System config API (thresholds, banners)
│   └── admin_db.php             # Database health + purge API
│
├── backend/ml/                  # Machine Learning Layer
│   ├── train_multi_horizon.py   # Train all 60 XGBoost models
│   ├── predict_worker.py        # Batch predictions (DB → DB)
│   ├── predict_on_demand.py     # On-demand predictions (DB → JSON)
│   ├── all_60_multi_horizon_models.pkl  # Trained model weights (~10MB)
│   └── model_features.pkl       # Feature column list
│
├── db_connect.php               # MySQL connection config
└── README.md                    # This file
```

---

## 🗄️ Database Schema (MySQL — `networkDB`)

| Table | Purpose |
|-------|---------|
| `network_metrics` | Raw measurements: timestamp + 8 metrics per row |
| `network_captures` | PCAP file metadata (filename, timestamp, packets captured) |
| `forecast_results` | Stored ML predictions from `predict_worker.py` |
| `users` | Authentication (username, password, role, account status) |
| `system_config` | Admin-configurable thresholds and banner text |

### The 8 Network Metrics

| Metric | Column | Unit | Description |
|--------|--------|------|-------------|
| Throughput | `throughput_mbps` | Mbps | Data transfer rate |
| Packet Rate | `packet_rate` | packets/sec | Number of packets per second |
| Active Flows | `active_flows` | count | Number of concurrent network connections |
| Latency | `latency_ms` | ms | Round-trip delay time |
| Retransmission Rate | `retransmission_rate` | % | Percentage of packets that needed resending |
| Packet Drop Rate | `packet_drop_rate` | % | Percentage of lost packets |
| Burstiness | `burstiness` | index | Measure of traffic irregularity |
| Flow Entropy | `flow_entropy` | bits | Shannon entropy of flow distribution |

---

## 🧠 Machine Learning System

### Overview

The system trains **60 XGBoost models** from a single training script:

```
8 metrics × 6 horizons = 48 regression models  (predict exact future values)
2 risk events × 6 horizons = 12 classification models  (predict probability of events)
────────────────────────────────────────────────────────
Total: 60 models
```

**Prediction Horizons**: `+2 seconds`, `+5 seconds`, `+10 seconds`, `+1 minute`, `+5 minutes`, `+60 minutes`

---

### Step 1: Data Loading

```python
SELECT metric_timestamp, throughput_mbps, packet_rate, active_flows,
       latency_ms, retransmission_rate, packet_drop_rate,
       burstiness, flow_entropy
FROM network_metrics
ORDER BY metric_timestamp ASC
```

All historical measurements are loaded chronologically. The timestamp becomes the DataFrame index.

---

### Step 2: Feature Engineering

Raw metric values alone are not enough — the model needs **contextual features** to understand trends and patterns. For each of the 8 metrics, 3 derived features are created:

#### a) Lag Features (Recent Memory)

```
lag1(t) = metric(t - 1)
lag5(t) = metric(t - 5)
```

These tell the model "what just happened." If throughput was 50 one step ago and 45 five steps ago, it indicates a downward trend.

#### b) Rolling Mean (Smoothed Trend)

```
              1    t
roll15(t) = ── × Σ metric(i)
             15  i=t-14
```

A 15-sample moving average that smooths out noise. This captures the "general neighborhood" of the metric — is it in a high period or a low period?

#### c) Time-of-Day Features

```
hour   ∈ {0, 1, 2, ..., 23}
minute ∈ {0, 1, 2, ..., 59}
```

Network traffic has strong daily patterns. Usage at 3 AM is fundamentally different from 8 PM.

#### Total Feature Vector

```
8 raw metrics
+ 8 × 3 derived features (lag1, lag5, roll15 per metric)
+ 2 time features (hour, minute)
= 34 input features
```

---

### Step 3: Target Engineering

#### Regression Targets (What value will this metric have in the future?)

```
target(t) = metric(t + horizon_steps)
```

For example, `target_throughput_mbps_5m` at row `t` is the throughput actually recorded 300 seconds later. The model learns the mapping:

```
features(t) → metric(t + Δ)
```

#### Classification Targets (Will a risk event occur?)

**Surge Detection** — will throughput exceed the top 5%?

```
surge_threshold = P₉₅(throughput_mbps)

surge(t) = {  1,  if throughput(t + Δ) > surge_threshold
           {  0,  otherwise
```

**Congestion Detection** — will latency OR packet loss spike?

```
latency_threshold = P₉₀(latency_ms)
retrans_threshold = P₉₀(retransmission_rate)

congestion(t) = {  1,  if latency(t + Δ) > latency_threshold
                {      OR retrans(t + Δ) > retrans_threshold
                {  0,  otherwise
```

Using quantile thresholds means the definition of "surge" adapts to your specific network — it's relative, not absolute.

---

### Step 4: Model Training (XGBoost)

#### Train/Test Split

```
Training set = first 80% of data (chronologically)
Test set     = last 20% of data (chronologically)
```

The split is **chronological, not random** — this is critical for time series. Random splitting would leak future data into training and give unrealistically good results.

#### XGBoost Regression (48 models)

XGBoost builds an **ensemble of decision trees** sequentially, where each new tree corrects the errors of all previous trees.

**Hyperparameters:**

| Parameter | Value | Meaning |
|-----------|-------|---------|
| `n_estimators` | 100 | Build 100 sequential decision trees |
| `learning_rate` | 0.05 | Each tree contributes only 5% — prevents overfitting |
| `max_depth` | 4 | Each tree has at most 4 levels of splits |

**How it works mathematically:**

The prediction is the sum of all trees:

```
ŷ = Σ fₖ(X)   for k = 1 to 100
```

Each tree `fₖ` is trained on the **residuals** (errors) of all previous trees:

```
residual_k(i) = y(i) - ŷ_{k-1}(i)
```

The objective function being minimized is:

```
L = Σ (yᵢ - ŷᵢ)² + Σ Ω(fₖ)
    ─────────────   ──────────
    prediction       regularisation
    accuracy         (prevents overfitting)
```

Where the regularisation term penalises complex trees:

```
Ω(f) = γ × T + ½λ × Σ wⱼ²
```
- `T` = number of leaves in the tree
- `wⱼ` = weight (prediction value) of each leaf
- `γ`, `λ` = regularisation coefficients

For each potential split in a tree node, XGBoost evaluates the **gain**:

```
           G_L²     G_R²     (G_L + G_R)²
Gain = ─────── + ─────── − ──────────────── − γ
        H_L + λ   H_R + λ   H_L + H_R + λ
```
- `G` = sum of gradients (first derivatives of loss)
- `H` = sum of hessians (second derivatives of loss)
- `L`, `R` = left and right child nodes

The split with the highest gain is chosen. This is what makes XGBoost so effective — it finds the optimal way to partition the feature space.

**Evaluation**: Mean Absolute Error (MAE)

```
         1   n
MAE = ── × Σ |yᵢ - ŷᵢ|
         n  i=1
```

#### XGBoost Classification (12 models)

Same tree-boosting algorithm, but with **logistic loss**:

```
L = -Σ [ yᵢ × log(pᵢ) + (1 - yᵢ) × log(1 - pᵢ) ]
```

The raw tree output is converted to probability via the **sigmoid function**:

```
           1
p = ──────────────
     1 + e^(-z)
```

Where `z` is the sum of all tree outputs.

**Class Imbalance Handling:**

Surges and congestion events are rare (maybe 5-10% of data). Without correction, the model would just predict "no event" always and be 90%+ accurate. The fix:

```
                   count(negative samples)
scale_pos_weight = ────────────────────────
                   count(positive samples)
```

This multiplies the loss of positive (event) samples, forcing the model to pay proportionally more attention to rare events.

---

### Step 5: Inference (Prediction)

There are two inference modes:

#### Batch Mode (`predict_worker.py`) — DB → DB

```
1. Fetch latest 15 rows from network_metrics
2. Engineer features (same lags, rolling means, hour/minute)
3. Extract last row only (it has all computed features)
4. Run all 60 models on that single feature vector
5. INSERT predictions into forecast_results table
```

#### On-Demand Mode (`predict_on_demand.py`) — DB → JSON

```
1. Receive target timestamp via --time argument
2. Fetch 15 rows BEFORE that timestamp
3. Engineer features → last row only
4. Run all 60 models
5. Print JSON to stdout (consumed by PHP API)
```

**Why 15 rows?** The `rolling(window=15).mean()` feature requires exactly 15 data points. With fewer, you get NaN and predictions fail.

**Why only the last row?** After computing lags and rolling means on 15 rows, only the final row has all features properly computed. Earlier rows have NaN for lag values.

---

## 🎨 Frontend System

### Pages

| Page | Access | Features |
|------|--------|----------|
| `index.html` | Public | KPIs, Time Machine slider with heatmap, 2 charts with predictions |
| `login.php` | Public | Username/password authentication |
| `user.php` | Authenticated | 8 metric charts, congestion gauge, prediction horizon toggles |
| `admin.php` | Admin only | User management, threshold config, DB health, data purge |

### Time Machine Slider

The slider background shows a **network usage heatmap** generated from actual capture data:

```
1. API returns: [{ timestamp, intensity }, ...] where intensity ∈ [0, 1]
2. For each capture, intensity = packets_captured / max(all_packets)
3. Each intensity maps to an HSL color:
      hue = (1 - intensity) × 240    // 240° (blue) → 0° (red)
      color = hsla(hue, 100%, 55%, 0.8)
4. All colors combined into a CSS linear-gradient applied to the slider
```

Result: blue regions = low traffic, red regions = high traffic.

### Chart Rendering

Each chart plots up to 3 data layers:

1. **Historical** (solid colored line) — last 60 data points from `get_metrics.php`
2. **Predicted** (dashed pink line) — sparse anchor points from ML models at ticks corresponding to horizons (2, 5, 10, 60, 300, 3600), connected by Chart.js with Bezier smoothing (`tension: 0.4`)
3. **Ground Truth** (dashed cyan line) — actual future data for verification, fetched separately

---

## 🔐 Authentication Flow

```
Public Page → Click "Login" → login.php?logout=1
    → Destroys existing session
    → Shows login form
    → POST {username, password} → api/auth.php
        → Query DB for user → Verify password → Set $_SESSION['role']
        → Redirect to user.php or admin.php based on role

Disconnect button → POST {action: 'logout'} → Session destroyed → index.html
```

---

## 🚀 Setup & Usage

### Prerequisites

- **XAMPP** (Apache + MySQL + PHP)
- **Python 3.x** with: `pandas`, `xgboost`, `scikit-learn`, `mysql-connector-python`, `joblib`

### Installation

1. Clone the repo into `c:\xampp\htdocs\hostel-network-forecaster`
2. Start Apache and MySQL via XAMPP Control Panel
3. Create the `networkDB` database and import the schema
4. Insert network metric data into `network_metrics` table
5. Create user accounts in the `users` table

### Training Models

```bash
cd backend/ml
python train_multi_horizon.py
```

This will:
- Load all data from `network_metrics`
- Train 60 XGBoost models
- Save to `all_60_multi_horizon_models.pkl` (~10MB)
- Save feature list to `model_features.pkl`

### Running Predictions

**Batch mode** (run periodically for latest data):
```bash
python predict_worker.py
```

**On-demand** (called automatically by the frontend slider):
```bash
python predict_on_demand.py --time "2026-03-07 15:00:00"
```

### Accessing the Dashboard

- **Public**: `http://localhost/hostel-network-forecaster/frontend/index.html`
- **Login**: `http://localhost/hostel-network-forecaster/frontend/login.php`
- **User Dashboard**: `http://localhost/hostel-network-forecaster/frontend/user.php`
- **Admin Dashboard**: `http://localhost/hostel-network-forecaster/frontend/admin.php`

---

## 📊 Model Performance

The models are evaluated on a chronological 80/20 split:

- **Regression models**: Evaluated using MAE (Mean Absolute Error) — lower is better
- **Classification models**: Evaluated using accuracy percentage
- **Shorter horizons** (2s, 5s, 10s) typically have lower error than longer ones (5m, 60m) because near-future is more predictable

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML, CSS, JavaScript, Chart.js |
| Backend API | PHP 8.x |
| Database | MySQL (via XAMPP) |
| ML Framework | XGBoost (Python) |
| ML Pipeline | pandas, scikit-learn, joblib |
| Styling | Custom cyberpunk CSS with glassmorphism |