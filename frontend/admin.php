<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Predictor - Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar glass-panel-nav">
        <div class="logo">
            <h1>NET<span class="gradient-text">PREDICT</span> <span class="badge badge-admin">ADMIN MODE</span></h1>
        </div>
        <div class="nav-links">
            <a href="#" onclick="logoutUser(); return false;" class="nav-link text-danger">Disconnect</a>
        </div>
    </nav>

    <div class="dashboard admin-dashboard">

        <section class="system-monitor-grid">
             <div class="kpi-card glass-panel kpi-admin">
                <div class="kpi-title">Dataset Monitoring</div>
                <div class="sys-metrics">
                    <div>Total PCAP Captures: <span class="highlight-cyan" id="admin-total-captures">...</span></div>
                    <div>Dataset Size: <span class="highlight-cyan" id="admin-dataset-size">...</span></div>
                    <div>Dataset Rows: <span class="highlight-cyan" id="admin-dataset-rows">...</span></div>
                    <div>Capture Status: <span id="admin-capture-status" class="highlight-green">...</span></div>
                    <div>Packets Captured: <span class="highlight-purple" id="admin-packets-captured">...</span></div>
                </div>
            </div>
            
            <div class="kpi-card glass-panel kpi-admin">
                <div class="kpi-title">Prediction Monitoring</div>
                 <div class="sys-metrics">
                    <div>Avg Surge Accuracy: <span class="highlight-pink" id="admin-surge-accuracy">...</span></div>
                    <div>Avg Congestion Accuracy: <span class="highlight-cyan" id="admin-congestion-accuracy">...</span></div>
                    <div>Training Dataset Size: <span class="highlight-cyan" id="admin-training-size">...</span></div>
                    <div>Last Training: <span class="highlight-orange" id="admin-last-training">...</span></div>
                    <div style="margin-top: 10px; border-top: 1px solid var(--border-color); padding-top: 5px;">System Log:</div>
                    <div id="admin-system-log" class="highlight-red">...</div>
                </div>
            </div>
        </section>

        <h2 class="section-title text-red" style="margin-top: 2rem;">ADMINISTRATIVE CONTROLS</h2>
        <div class="admin-controls-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            
            <section class="glass-panel group-panel">
                <h3 class="kpi-title" style="margin-bottom: 1rem; color: var(--neon-cyan);">1. User Management</h3>
                <div id="admin-user-list" class="user-list-container" style="max-height: 200px; overflow-y: auto;">
                    <div style="color: var(--text-muted); font-size: 0.8rem;">Loading users...</div>
                </div>
                <div style="margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 0.8rem;">
                    <div id="add-user-toggle" style="text-align: center;">
                        <button class="neon-btn" style="width: 100%; padding: 0.4rem; font-size: 0.8rem;" onclick="document.getElementById('add-user-form').style.display='flex'; this.parentElement.style.display='none';">+ Add User</button>
                    </div>
                    <form id="add-user-form" style="display: none; flex-direction: column; gap: 0.5rem;">
                        <input type="text" id="new-username" placeholder="Username" required style="background: rgba(0,0,0,0.5); border: 1px solid var(--border-color); color: white; padding: 0.35rem 0.5rem; border-radius: 4px; font-size: 0.8rem; outline: none;">
                        <input type="password" id="new-password" placeholder="Password" required style="background: rgba(0,0,0,0.5); border: 1px solid var(--border-color); color: white; padding: 0.35rem 0.5rem; border-radius: 4px; font-size: 0.8rem; outline: none;">
                        <select id="new-role" style="background: rgba(0,0,0,0.5); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; padding: 0.35rem; font-size: 0.8rem; outline: none;">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="submit" class="neon-btn" style="flex: 1; padding: 0.4rem; font-size: 0.8rem;">Create</button>
                            <button type="button" class="neon-btn" style="flex: 1; padding: 0.4rem; font-size: 0.8rem; color: var(--text-muted); border-color: var(--text-muted);" onclick="document.getElementById('add-user-form').style.display='none'; document.getElementById('add-user-toggle').style.display='block';">Cancel</button>
                        </div>
                        <div id="add-user-message" style="font-size: 0.75rem; text-align: center;"></div>
                    </form>
                </div>
            </section>

            <section class="glass-panel group-panel">
                <h3 class="kpi-title" style="margin-bottom: 1rem; color: var(--neon-purple);">2. Thresholds</h3>
                <form id="admin-config-form" style="display: flex; flex-direction: column; gap: 0.8rem;">
                    <div class="input-group" style="margin-bottom: 0;">
                        <label>Surge Sensitivity (Mbps)</label>
                        <input type="number" id="cfg-surge" step="0.1" required>
                    </div>
                    <div class="input-group" style="margin-bottom: 0;">
                        <label>Congestion Trigger (ms)</label>
                        <input type="number" id="cfg-latency" step="0.1" required>
                    </div>
                    <div class="input-group" style="margin-bottom: 0;">
                        <label>Max Drop Rate (%)</label>
                        <input type="number" id="cfg-drop" step="0.1" required>
                    </div>
                    <div class="input-group" style="margin-bottom: 0;">
                        <label>Global Announcement Banner</label>
                        <input type="text" id="cfg-banner" placeholder="Empty to hide banner">
                    </div>
                    <button type="submit" class="neon-btn" style="margin-top: 0.5rem; padding: 0.5rem;">Save Rules</button>
                    <div id="cfg-message" style="font-size: 0.8rem; text-align: center; margin-top: 0.5rem;"></div>
                </form>
            </section>

            <section class="glass-panel group-panel" style="position: relative;">
                <h3 class="kpi-title" style="margin-bottom: 1rem; color: var(--neon-orange);">3. DB Health</h3>
                <div class="sys-metrics" id="db-stats-container">
                    <div>Metrics Rows: <span class="highlight-cyan" id="db-metrics-count">Loading...</span></div>
                    <div>Capture Files: <span class="highlight-cyan" id="db-captures-count">Loading...</span></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Data Available From:</div>
                    <div id="db-date-range" style="font-size: 0.8rem; color: var(--text-main);">...</div>
                </div>
                
                <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                    <label style="font-size: 0.8rem; color: var(--neon-red); display:block; margin-bottom: 0.5rem;">Purge Data Older Than:</label>
                    <input type="date" id="purge-date" style="width: 100%; background: rgba(0,0,0,0.5); border: 1px solid var(--neon-red); color: white; padding: 0.4rem; border-radius: 4px; color-scheme: dark; margin-bottom: 0.5rem;">
                    <button id="btn-purge" class="neon-btn" style="width: 100%; color: var(--neon-red); border-color: var(--neon-red); padding: 0.5rem;">Execute Clean Up</button>
                    <div id="purge-message" style="font-size: 0.8rem; text-align: center; margin-top: 0.5rem;"></div>
                </div>
            </section>

        </div>

    </div>

    <script src="script.js"></script>
</body>
</html>
