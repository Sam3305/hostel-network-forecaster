<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Predictor - User Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar glass-panel-nav">
        <div class="logo">
            <h1>NET<span class="gradient-text">PREDICT</span> <span class="badge badge-user">USER MODE</span></h1>
        </div>
        <div class="nav-links">
            <a href="#" onclick="logoutUser(); return false;" class="nav-link text-danger">Disconnect</a>
        </div>
    </nav>

    <div class="dashboard">
        <div id="global-banner" class="glass-panel" style="display: none; background: rgba(0, 240, 255, 0.1); border-color: var(--cyan); color: var(--text-main); text-align: center; padding: 1rem; margin-bottom: 1.5rem; font-weight: 600; letter-spacing: 0.05em;">
            <span id="global-banner-text"></span>
        </div>

        <header class="glass-panel">
            <h1>NETWORK OVERVIEW</h1>
            <div class="live-indicator"><span class="pulse"></span> AI ANALYZING</div>
        </header>

        <section class="glass-panel timeline-panel" style="margin-bottom: 1.5rem; text-align: center;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <h3 style="margin: 0; color: var(--cyan); font-size: 0.9rem; text-transform: uppercase;">Time Machine Scrubber</h3>
                <span id="slider-time-label" style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-main); font-weight: bold;">LIVE (Real-Time)</span>
            </div>
            <input type="range" id="timeline-slider" min="0" max="100" value="100" style="width: 100%; cursor: pointer;">
            <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                <button id="btn-snap-live" class="action-btn" style="font-size: 0.7rem; padding: 0.3rem 0.6rem;">Snap to Live</button>
            </div>
        </section>

        <section class="kpi-grid">
            <div class="kpi-card glass-panel"><div class="kpi-title">Throughput</div><div class="kpi-value" id="val-thr">1.2 Gbps</div></div>
            <div class="kpi-card glass-panel"><div class="kpi-title">Packet Rate</div><div class="kpi-value" id="val-pkt">45k p/s</div></div>
            <div class="kpi-card glass-panel kpi-warning"><div class="kpi-title">Latency</div><div class="kpi-value" id="val-lat">42 ms</div></div>
            <div class="kpi-card glass-panel"><div class="kpi-title">Active Flows</div><div class="kpi-value" id="val-flw">3210</div></div>
            <div class="kpi-card glass-panel"><div class="kpi-title">Drop Rate</div><div class="kpi-value" id="val-drp">0.01%</div></div>
        </section>

        <div class="split-view">
            <div class="column">
                <section class="glass-panel group-panel">
                    <h2 class="section-title">TRAFFIC ANALYTICS</h2>
                    <div class="chart-container third-chart"><canvas id="chart-user-throughput"></canvas></div>
                    <div class="chart-container third-chart"><canvas id="chart-user-packetrate"></canvas></div>
                    <div class="chart-container third-chart"><canvas id="chart-user-activeflows"></canvas></div>
                </section>

                <section class="glass-panel group-panel">
                    <h2 class="section-title">FLOW ANALYSIS</h2>
                    <div class="chart-container half-chart"><canvas id="chart-user-entropy"></canvas></div>
                </section>
            </div>

            <div class="column">
                <section class="glass-panel group-panel">
                    <h2 class="section-title">CONGESTION SIGNALS</h2>
                    <div class="chart-container third-chart"><canvas id="chart-user-latency"></canvas></div>
                    <div class="chart-container third-chart"><canvas id="chart-user-retrans"></canvas></div>
                    <div class="chart-container third-chart"><canvas id="chart-user-burstiness"></canvas></div>
                </section>

                <section class="glass-panel group-panel ai-panel">
                    <h2 class="section-title gradient-text" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>AI PREDICTION PANEL</span>
                        <div class="horizon-toggles">
                            <button class="toggle-btn" data-horizon="2s">2 Sec</button>
                            <button class="toggle-btn" data-horizon="5s">5 Sec</button>
                            <button class="toggle-btn" data-horizon="10s">10 Sec</button>
                            <button class="toggle-btn" data-horizon="60s">1 Min</button>
                            <button class="toggle-btn active" data-horizon="5m">5 Min</button>
                            <button class="toggle-btn" data-horizon="60m">1 Hour</button>
                        </div>
                    </h2>
                    <div class="ai-metrics" style="display: flex; gap: 2rem;">
                        <div class="ai-metric">Congestion Risk: <span class="highlight-orange" id="val-congestion-risk">--%</span></div>
                        <div class="ai-metric">Surge Risk: <span class="highlight-cyan" id="val-surge-risk">--%</span></div>
                        <div class="ai-metric">Model Accuracy: <span class="highlight-pink" id="val-model-accuracy">--%</span></div>
                    </div>
                    <div class="ai-grid">
                        <div class="gauges-wrapper">
                            <div class="gauge-container">
                                <span style="font-size: 0.6rem; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Congestion</span>
                                <canvas id="gauge-congestion" style="max-height: 120px;"></canvas>
                            </div>
                            <div class="gauge-container">
                                <span style="font-size: 0.6rem; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Surge</span>
                                <canvas id="gauge-surge" style="max-height: 120px;"></canvas>
                            </div>
                        </div>
                        <div class="prediction-chart-container">
                            <canvas id="chart-user-predicted"></canvas>
                        </div>
                    </div>
                </section>
                
                <section class="glass-panel log-panel">
                    <h2 class="section-title text-cyan">INSIGHTS PANEL</h2>
                    <ul id="insight-list" class="log-list">
                        <li class="log-item log-info"><span class="log-time">[System]</span> <span class="log-msg">Traffic increasing rapidly.</span></li>
                        <li class="log-item log-alert"><span class="log-time">[System]</span> <span class="log-msg">High burstiness detected.</span></li>
                        <li class="log-item log-ai"><span class="log-time">[System]</span> <span class="log-msg">Possible surge in 15 minutes.</span></li>
                    </ul>
                </section>

                <section class="glass-panel audit-panel" style="margin-top: 1.5rem;">
                    <h2 class="section-title gradient-text">AI PERFORMANCE AUDIT (GROUND TRUTH COMPARISON)</h2>
                    <div id="audit-grid" class="audit-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                        <div class="audit-card glass-panel-dark" style="padding: 1rem; border-radius: 10px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.05);">
                            <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Average Fidelity</div>
                            <div id="audit-global-fidelity" style="font-size: 1.5rem; font-weight: 800; color: var(--green);">Waiting...</div>
                        </div>
                        <!-- Individual metrics will be populated here by JS -->
                    </div>
                </section>
            </div>
        </div>
    </div>

    </div>

    <script src="script.js"></script>
</body>
</html>
