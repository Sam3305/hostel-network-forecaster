// Cyberpunk / GenZ Color Palette
const colors = {
    bg: '#050505', grid: 'rgba(255, 255, 255, 0.05)', text: '#8a8a93',
    cyan: '#00f0ff', purple: '#b14bf4', pink: '#ff007f', green: '#39ff14',
    orange: '#ff9d00', red: '#ff3333', blue: '#0066ff', neon_purple: '#b14bf4', neon_green: '#39ff14'
};

Chart.defaults.color = colors.text;
Chart.defaults.font.family = "'JetBrains Mono', monospace";
Chart.defaults.scale.grid.color = colors.grid;

const commonOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: 'rgba(10, 10, 15, 0.9)', titleColor: colors.cyan, bodyColor: '#fff', borderColor: colors.purple, borderWidth: 1, padding: 10, cornerRadius: 8, displayColors: false },
        title: {display: false}
    },
    scales: { 
        x: { grid: { display: false }, ticks: { color: colors.text, font: {size: 10} }, title: {display: true, text: 'Time', color: colors.text} }, 
        y: { 
            grid: { color: colors.grid }, 
            ticks: { color: colors.text, maxTicksLimit: 8 }, 
            border: { display: false },
            beginAtZero: true
        } 
    },
    elements: { point: { radius: 0, hitRadius: 10, hoverRadius: 4 } },
    interaction: { mode: 'index', intersect: false }
};

// Global Variables
let chartInstances = {};
let liveGauge = null;
let currentHorizon = '5m'; // Default to 5-minute ML horizon

// Time Machine Variables
let scrubbedTime = null; // Stays null when in "Live" mode
let validCaptureTimestamps = []; // Holds the exact chronological metadata array
let captureIntensities = []; // Stores the calculated tracking densities for background heatmaps
let autoRefreshInterval = null;

const createSmallChart = (id, color, label, historicalLabels, historicalData, futureLabel = null, futureDataPoint = null, isFilled = true, padCount = 0, groundTruthData = null) => {
    const el = document.getElementById(id);
    if (!el) return null;
    const ctx = el.getContext('2d');
    
    let bg = 'transparent';
    if (isFilled) {
        const gradient = ctx.createLinearGradient(0, 0, 0, 150);
        gradient.addColorStop(0, `${color}30`);
        gradient.addColorStop(1, `${color}00`);
        bg = gradient;
    }

    let customOptions = JSON.parse(JSON.stringify(commonOptions));
    customOptions.scales.y.title = {display: true, text: label, color: colors.text};

    const combinedLabels = [...historicalLabels];
    const combinedHistorical = [...historicalData];
    
    // Extend labels structure across the void
    if (futureLabel) {
        for (let i = 0; i < padCount; i++) {
            combinedLabels.push('');
            combinedHistorical.push(null);
        }
        combinedLabels.push(futureLabel);
        combinedHistorical.push(null);
    }
    
    const datasets = [
        { label: `Historical ${label}`, data: combinedHistorical, borderColor: color, backgroundColor: bg, borderWidth: 2, fill: isFilled, tension: 0.3 }
    ];

    // Plot: Ground Truth (What ACTUALLY Happened)
    if (groundTruthData && groundTruthData.length > 0) {
        const combinedTruth = Array(historicalLabels.length).fill(null);
        combinedTruth[combinedTruth.length - 1] = historicalData[historicalData.length - 1]; // Anchor connection to reality
        
        let truthIter = 0;
        for (let i = 0; i <= padCount; i++) {
            if (truthIter < groundTruthData.length) {
                combinedTruth.push(groundTruthData[truthIter]);
                truthIter++;
            } else {
                combinedTruth.push(null); 
            }
        }
        datasets.push({ label: `Actual Reality`, data: combinedTruth, borderColor: colors.cyan, borderDash: [2, 4], borderWidth: 2, tension: 0.3, fill: false });
    }

    // Plot: The Model's Prediction Curve
    if (futureLabel && futureDataPoint !== null && historicalData.length > 0) {
        const combinedFuture = Array(historicalLabels.length).fill(null);
        combinedFuture[combinedFuture.length - 1] = historicalData[historicalData.length - 1]; // Connect to last hist point
        
        // futureDataPoint is now a dictionary of offset lines { '2': 4.5, '5': 4.7, '60': 5.1 ... }
        for (let i = 1; i <= padCount; i++) {
            if (futureDataPoint[i] !== undefined) {
                combinedFuture.push(futureDataPoint[i]);
            } else {
                combinedFuture.push(null);
            }
        }
        datasets.push({ label: `Predicted ${label}`, data: combinedFuture, borderColor: colors.pink, borderDash: [5, 5], borderWidth: 2, tension: 0.4, fill: false, spanGaps: true, pointBackgroundColor: colors.pink, pointRadius: 3, pointHoverRadius: 6 });
    }

    return new Chart(ctx, {
        type: 'line',
        data: { labels: combinedLabels, datasets: datasets },
        options: customOptions
    });
};

/* --- ASYNC DATA FETCHING (Initial load) --- */
async function fetchMetrics() {
    try {
        let url = '../api/get_metrics.php?';
        if (scrubbedTime) {
            url += `time=${encodeURIComponent(scrubbedTime)}&ground_truth=1`;
        }
        const res = await fetch(url);
        if (!res.ok) throw new Error('Network response was not ok');
        return await res.json();
    } catch (error) {
        return [];
    }
}

async function fetchPredictions() {
    try {
        let url = `../api/get_predictions.php?horizon=${currentHorizon}`;
        if (scrubbedTime) {
            // Reroute prediction queries explicitly to the Dynamic Python ML bridge when scrubbing
            url = `../api/get_dynamic_predictions.php?time=${encodeURIComponent(scrubbedTime)}`;
        }
        const res = await fetch(url);
        if (!res.ok) throw new Error('Network response was not ok');
        return await res.json();
    } catch (error) {
        return { predictions: [], latest_surge_probability: 0, latest_congestion_risk: 0 };
    }
}




/* --- DASHBOARD INITIALIZATION --- */
async function initDashboard() {
    await initTimelineScrubber();
    await pollLiveData(); // initial load


    // Fetch global announcement banner
    try {
        const sysRes = await fetch('../api/admin_system.php');
        const sysData = await sysRes.json();
        if (sysData.status === 'success' && sysData.data && sysData.data.announcement_banner) {
            const bannerEl = document.getElementById('global-banner');
            const bannerText = document.getElementById('global-banner-text');
            if (bannerEl && bannerText) {
                bannerText.innerText = sysData.data.announcement_banner;
                bannerEl.style.display = 'block';
            }
        }
    } catch (e) {
        console.error("Could not load system banner:", e);
    }

    // Init Admin Panel Controls if they exist
    if (document.getElementById('admin-user-list')) {
        initAdminControls();
    }
    
    setupHorizonToggles();

    // Set polling interval for LIVE real-time history appending!
    autoRefreshInterval = setInterval(pollLiveData, 10000); // 10 seconds
}

/* --- TIME MACHINE ENGINE (CAPTURE SYNCHRONIZED) --- */
async function initTimelineScrubber() {
    const slider = document.getElementById('timeline-slider');
    const label = document.getElementById('slider-time-label');
    const snapBtn = document.getElementById('btn-snap-live');
    if (!slider) return;

    try {
        const res = await fetch('../api/get_capture_timeline.php');
        const data = await res.json();
        
        if (data.status === 'success' && data.timestamps && data.timestamps.length > 0) {
            validCaptureTimestamps = data.timestamps;
            captureIntensities = data.intensities || [];
            
            // Map the slider 0-N purely to the array indexes, locking it strictly to metadata
            slider.min = 0;
            slider.max = validCaptureTimestamps.length - 1;
            slider.value = slider.max;

            // Render Dynamic Heatmap Dataset as the Input Backdrop
            if (captureIntensities.length > 0) {
                let gradientStops = [];
                const len = captureIntensities.length;
                for (let i = 0; i < len; i++) {
                    const pct = (i / (len - 1)) * 100;
                    const intensity = captureIntensities[i];
                    
                    // Map mathematical scale: 240 (blue = Low Traffic) to 0 (red = Extreme Surge)
                    const hue = (1.0 - intensity) * 240; 
                    gradientStops.push(`hsla(${hue}, 100%, 50%, 0.8) ${pct.toFixed(2)}%`);
                }
                const linearGradient = `linear-gradient(to right, ${gradientStops.join(', ')})`;
                slider.style.background = linearGradient;
            }

            // Fluid UI Update during dragging
            slider.addEventListener('input', (e) => {
                const idx = parseInt(e.target.value);
                // If dragged to the absolute edge, assume Live / Latest Mode
                if (idx === parseInt(slider.max)) {
                    label.innerText = 'LATEST (Real-Time)';
                    label.style.color = colors.green;
                    return;
                }
                const selectedTimeString = validCaptureTimestamps[idx];
                label.innerText = `Replaying: ${selectedTimeString}`;
                label.style.color = colors.pink;
            });

            // Hard Graph Rebuild on drag release
            slider.addEventListener('change', async (e) => {
                const idx = parseInt(e.target.value);
                
                if (idx === parseInt(slider.max)) {
                    // Snap back to live
                    scrubbedTime = null;
                    label.innerText = 'LATEST (Real-Time)';
                    label.style.color = colors.green;
                    if (!autoRefreshInterval) autoRefreshInterval = setInterval(pollLiveData, 10000);
                } else {
                    // Lock into exact Time Machine scrubbed timestamp from the captures array
                    const selectedTimeString = validCaptureTimestamps[idx];
                    scrubbedTime = selectedTimeString;
                    
                    label.innerText = `Replaying: ${selectedTimeString}`;
                    label.style.color = colors.pink;
                    
                    // Kill the live-polling interval so it doesn't overwrite the time-travel
                    if (autoRefreshInterval) {
                        clearInterval(autoRefreshInterval);
                        autoRefreshInterval = null;
                    }
                }
                await pollLiveData();
            });

            // Snap back to present button
            snapBtn.addEventListener('click', async () => {
                slider.value = slider.max;
                scrubbedTime = null;
                label.innerText = 'LATEST (Real-Time)';
                label.style.color = colors.green;
                if (!autoRefreshInterval) autoRefreshInterval = setInterval(pollLiveData, 10000);
                await pollLiveData();
            });
        }
    } catch (e) {
        console.error("Failed to load timeline metadata", e);
        slider.disabled = true;
        label.innerText = 'Metadata Unavailable';
    }
}

/* --- MASTER RENDERER FOR LIVE GRAPHING --- */
async function pollLiveData() {
    const rawMetricsFetch = await fetchMetrics();
    const predictionData = await fetchPredictions();
    
    // Safety check nested JSON payload vs old flat array payload
    if (!rawMetricsFetch || (Array.isArray(rawMetricsFetch) && rawMetricsFetch.length === 0) || (!Array.isArray(rawMetricsFetch) && (!rawMetricsFetch.metrics || rawMetricsFetch.metrics.length === 0))) return;

    const metricsData = rawMetricsFetch.metrics ? rawMetricsFetch.metrics : rawMetricsFetch;
    const groundTruthData = rawMetricsFetch.ground_truth ? rawMetricsFetch.ground_truth : [];

    // Clear existing charts to safely redraw
    for (const key in chartInstances) {
        if (chartInstances[key]) chartInstances[key].destroy();
    }
    chartInstances = {};

    const labels = metricsData.map(m => {
        const d = new Date(m.metric_timestamp);
        return `${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}:${d.getSeconds().toString().padStart(2, '0')}`;
    });

    const mapCol = (dataArr, col, scale = 1, applyGlobalScale = false) => {
        if (!dataArr) return null;
        return dataArr.map(m => {
            let val = parseFloat(m[col]) * scale;
            if (applyGlobalScale) val = val * DATA_SCALE_FACTOR;
            return val.toFixed(2);
        });
    }

    const throughput = mapCol(metricsData, 'throughput_mbps', 1, false); 
    const packetRate = mapCol(metricsData, 'packet_rate', 1, false);
    const activeFlows = mapCol(metricsData, 'active_flows', 1, false);
    const latency = mapCol(metricsData, 'latency_ms', 1, false);
    const retrans = mapCol(metricsData, 'retransmission_rate', 1, false);
    const dropRate = mapCol(metricsData, 'packet_drop_rate', 1, false);
    const burstiness = mapCol(metricsData, 'burstiness', 1, false);
    const entropy = mapCol(metricsData, 'flow_entropy', 1, false);

    // Prepare Ground Truth Reality Arrays
    const gtThroughput = groundTruthData.length > 0 ? mapCol(groundTruthData, 'throughput_mbps', 1, false) : null;
    const gtPacketRate = groundTruthData.length > 0 ? mapCol(groundTruthData, 'packet_rate', 1, false) : null;
    const gtActiveFlows = groundTruthData.length > 0 ? mapCol(groundTruthData, 'active_flows', 1, false) : null;
    const gtLatency = groundTruthData.length > 0 ? mapCol(groundTruthData, 'latency_ms', 1, false) : null;
    const gtRetrans = groundTruthData.length > 0 ? mapCol(groundTruthData, 'retransmission_rate', 1, false) : null;
    const gtDropRate = groundTruthData.length > 0 ? mapCol(groundTruthData, 'packet_drop_rate', 1, false) : null;
    const gtBurstiness = groundTruthData.length > 0 ? mapCol(groundTruthData, 'burstiness', 1, false) : null;
    const gtEntropy = groundTruthData.length > 0 ? mapCol(groundTruthData, 'flow_entropy', 1, false) : null;

    let futureLabel = null;
    let predOutput = { throughput: {}, packet: {}, flows: {}, latency: {}, retrans: {}, burstiness: {}, entropy: {}, dropRate: {} };
    let surgeProbPlot = predictionData ? predictionData.latest_congestion_risk : 0;

    if (predictionData && predictionData.predictions) {
        futureLabel = `+${currentHorizon}`;
        const pDict = predictionData.predictions;
        const padOffsets = {'2s': 2, '5s': 5, '10s': 10, '1m': 60, '5m': 300, '15m': 900, '60m': 3600};
        const maxOffset = padOffsets[currentHorizon] || 300;

        for (const [horiz, mappedDict] of Object.entries(pDict)) {
            const offsetTick = padOffsets[horiz];
            if (offsetTick && offsetTick <= maxOffset) {
                predOutput.throughput[offsetTick] = parseFloat(mappedDict.predicted_throughput_mbps).toFixed(2);
                predOutput.packet[offsetTick] = parseFloat(mappedDict.predicted_packet_rate).toFixed(2);
                predOutput.flows[offsetTick] = parseFloat(mappedDict.predicted_active_flows).toFixed(2);
                predOutput.latency[offsetTick] = parseFloat(mappedDict.predicted_latency_ms).toFixed(2);
                
                // Allow fallback column name for older schema dictionaries if present
                const retransVal = mappedDict.predicted_retrans_rate !== undefined ? mappedDict.predicted_retrans_rate : mappedDict.predicted_retrans;
                predOutput.retrans[offsetTick] = parseFloat(retransVal).toFixed(2);
                
                predOutput.burstiness[offsetTick] = parseFloat(mappedDict.predicted_burstiness).toFixed(2);
                
                if (mappedDict.predicted_flow_entropy !== undefined) {
                    predOutput.entropy[offsetTick] = parseFloat(mappedDict.predicted_flow_entropy).toFixed(2);
                }
                if (mappedDict.predicted_packet_drop_rate !== undefined) {
                    predOutput.dropRate[offsetTick] = parseFloat(mappedDict.predicted_packet_drop_rate).toFixed(2);
                }
            }
            if (horiz === currentHorizon && mappedDict.congestion_risk_pct !== undefined) {
                surgeProbPlot = mappedDict.congestion_risk_pct;
            }
        }
    }

    let padCount = 0;
    if (currentHorizon === '2s') padCount = 2;
    else if (currentHorizon === '5s') padCount = 5;
    else if (currentHorizon === '10s') padCount = 10;
    else if (currentHorizon === '1m') padCount = 60;
    else if (currentHorizon === '5m') padCount = 300;
    else if (currentHorizon === '60m') padCount = 3600;
    
    const c = (id, color, label, hist, pKey = null, gtArr = null, isFill = true) => {
        chartInstances[id] = createSmallChart(id, color, label, labels, hist, futureLabel, pKey ? predOutput[pKey] : null, isFill, padCount, gtArr);
    };

    c('chart-public-throughput', colors.cyan, 'Throughput (Mbps)', throughput, 'throughput', gtThroughput);
    c('chart-public-packet', colors.blue, 'Packet Rate (p/s)', packetRate, 'packet', gtPacketRate);
    c('chart-user-throughput', colors.cyan, 'Throughput (Mbps)', throughput, 'throughput', gtThroughput);
    c('chart-user-packetrate', colors.blue, 'Packet Rate (p/s)', packetRate, 'packet', gtPacketRate);
    c('chart-user-activeflows', colors.purple, 'Active Flows', activeFlows, 'flows', gtActiveFlows);
    c('chart-user-latency', colors.orange, 'Latency (ms)', latency, 'latency', gtLatency);
    c('chart-user-retrans', colors.red, 'Retransmission Rate (%)', retrans, 'retrans', gtRetrans);
    c('chart-user-burstiness', colors.cyan, 'Burstiness Index', burstiness, 'burstiness', gtBurstiness);
    c('chart-user-entropy', colors.green, 'Flow Entropy Analysis', entropy, 'entropy', gtEntropy);
    


    // Update Live Congestion Gauge
    const congestionProb = surgeProbPlot || 0;
    if (liveGauge) { liveGauge.destroy(); }
    liveGauge = initGauge(congestionProb);
    const cHtml = document.getElementById('val-congestion-risk');
    if (cHtml) cHtml.innerText = Math.round(congestionProb) + '%';

    // Update Top KPIs
    const lMetric = metricsData[metricsData.length - 1];
    const updEl = (id, val) => { const e = document.getElementById(id); if (e) e.innerText = val; };
    updEl('val-thr', throughput[throughput.length - 1] + ' Mbps');
    updEl('val-pkt', packetRate[packetRate.length - 1] + ' p/s');
    updEl('val-lat', latency[latency.length - 1] + ' ms');
    updEl('val-flw', Math.floor(lMetric.active_flows));
    updEl('val-drp', dropRate[dropRate.length - 1] + '%');
}


/* --- AI GAUGE --- */
const initGauge = (initialProb) => {
    const el = document.getElementById('gauge-surge');
    if (!el) return null;
    const ctx = el.getContext('2d');
    const congestionProb = Math.round(initialProb || 0); 
    
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Congestion Risk', 'Safe'],
            datasets: [{ data: [congestionProb, 100 - congestionProb], backgroundColor: [colors.orange, '#1a1a24'], borderWidth: 0, circumference: 180, rotation: 270 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '80%',
            plugins: { legend: { display: false }, tooltip: { enabled: false }, textCenter: congestionProb }
        },
        plugins: [{
            id: 'textCenter',
            beforeDraw: function(chart) {
                var width = chart.width, height = chart.height, ctx = chart.ctx;
                ctx.restore();
                var fontSize = (height / 60).toFixed(2);
                ctx.font = `bold ${fontSize}em "Outfit"`;
                ctx.textBaseline = "middle";
                ctx.fillStyle = colors.orange;
                
                // Read from options so we can dynamically update text
                let currentVal = chart.options.plugins.textCenter || 0;
                var text = currentVal + "%",
                    textX = Math.round((width - ctx.measureText(text).width) / 2),
                    textY = height / 1.3;
                ctx.fillText(text, textX, textY);
                ctx.save();
            }
        }]
    });
};









// Set up prediction toggles for Admin view
function setupHorizonToggles() {
    const buttons = document.querySelectorAll('.toggle-btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', async (e) => {
            buttons.forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            currentHorizon = e.target.getAttribute('data-horizon');
            await pollLiveData(); // Immediately redraw all graphs to the new prediction dot
        });
    });
}

/* --- NEW ADMIN CONTROLS LOGIC --- */
function initAdminControls() {
    fetchAdminUsers();
    fetchAdminConfig();
    fetchDBStats();

    const configForm = document.getElementById('admin-config-form');
    if (configForm) {
        configForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSystemConfig();
        });
    }

    const purgeBtn = document.getElementById('btn-purge');
    if (purgeBtn) {
        purgeBtn.addEventListener('click', async () => {
            await purgeDatabase();
        });
    }
}

// 1. User Management
async function fetchAdminUsers() {
    try {
        const res = await fetch('../api/admin_users.php');
        const data = await res.json();
        const container = document.getElementById('admin-user-list');
        
        if (data.status === 'success' && data.data) {
            container.innerHTML = ''; // Clear loading text
            data.data.forEach(user => {
                const userDiv = document.createElement('div');
                userDiv.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.8rem;';
                
                const infoPane = document.createElement('div');
                infoPane.innerHTML = `<span style="color:var(--text-main); font-weight: 600;">${user.username}</span> <br> <span style="font-size: 0.7rem; color: var(--text-muted);">Last Login: ${user.last_login || 'Never'}</span>`;
                
                const actionPane = document.createElement('div');
                actionPane.style.display = 'flex';
                actionPane.style.gap = '0.5rem';

                // Role Select
                const roleSelect = document.createElement('select');
                roleSelect.style.cssText = 'background: rgba(0,0,0,0.5); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; padding: 0.2rem; outline: none; font-size: 0.75rem;';
                roleSelect.innerHTML = `<option value="user" ${user.role === 'user' ? 'selected' : ''}>User</option><option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>`;
                roleSelect.addEventListener('change', () => updateUser(user.id, roleSelect.value, statusSelect.value));

                // Status Select
                const statusSelect = document.createElement('select');
                statusSelect.style.cssText = 'background: rgba(0,0,0,0.5); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; padding: 0.2rem; outline: none; font-size: 0.75rem;';
                statusSelect.innerHTML = `<option value="active" ${user.account_status === 'active' ? 'selected' : ''}>Active</option><option value="suspended" ${user.account_status === 'suspended' ? 'selected' : ''}>Suspended</option>`;
                statusSelect.addEventListener('change', () => updateUser(user.id, roleSelect.value, statusSelect.value));

                actionPane.appendChild(roleSelect);
                actionPane.appendChild(statusSelect);
                
                userDiv.appendChild(infoPane);
                userDiv.appendChild(actionPane);
                container.appendChild(userDiv);
            });
        }
    } catch (e) {
        console.error("Failed to load users", e);
    }
}

async function updateUser(userId, role, status) {
    try {
        const res = await fetch('../api/admin_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_user', id: userId, role: role, status: status })
        });
        const data = await res.json();
        console.log("User Update:", data.message);
    } catch (e) {
        console.error("Failed to update user", e);
    }
}

// 2. System Thresholds
async function fetchAdminConfig() {
    try {
        const res = await fetch('../api/admin_system.php');
        const data = await res.json();
        if (data.status === 'success' && data.data) {
            document.getElementById('cfg-surge').value = data.data.surge_threshold_mbps;
            document.getElementById('cfg-latency').value = data.data.congestion_latency_ms;
            document.getElementById('cfg-drop').value = data.data.congestion_drop_pct;
            document.getElementById('cfg-banner').value = data.data.announcement_banner;
        }
    } catch (e) {
        console.error("Failed to load config", e);
    }
}

async function saveSystemConfig() {
    const surge = parseFloat(document.getElementById('cfg-surge').value);
    const latency = parseFloat(document.getElementById('cfg-latency').value);
    const drop = parseFloat(document.getElementById('cfg-drop').value);
    const banner = document.getElementById('cfg-banner').value;

    const msgEl = document.getElementById('cfg-message');
    msgEl.style.color = 'var(--text-muted)';
    msgEl.innerText = 'Saving...';

    try {
        const res = await fetch('../api/admin_system.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'update_config', 
                surge_threshold_mbps: surge,
                congestion_latency_ms: latency,
                congestion_drop_pct: drop,
                announcement_banner: banner
            })
        });
        const data = await res.json();
        if(data.status === 'success') {
            msgEl.style.color = 'var(--neon-green)';
            msgEl.innerText = 'Rules Updated!';
            setTimeout(() => msgEl.innerText = '', 3000);
        } else {
            msgEl.style.color = 'var(--neon-red)';
            msgEl.innerText = 'Failed to save.';
        }
    } catch (e) {
        console.error("Failed to save config", e);
        msgEl.style.color = 'var(--neon-red)';
        msgEl.innerText = 'Network error.';
    }
}

// 3. Database Health & Purge
async function fetchDBStats() {
    try {
        const res = await fetch('../api/admin_db.php');
        const data = await res.json();
        if (data.status === 'success' && data.data) {
            document.getElementById('db-metrics-count').innerText = parseInt(data.data.metrics_count).toLocaleString();
            document.getElementById('db-captures-count').innerText = parseInt(data.data.captures_count).toLocaleString();
            
            if(data.data.start_date && data.data.end_date) {
                const sDate = new Date(data.data.start_date).toLocaleDateString();
                const eDate = new Date(data.data.end_date).toLocaleDateString();
                document.getElementById('db-date-range').innerText = `${sDate} - ${eDate}`;
            } else {
                document.getElementById('db-date-range').innerText = "No data points located.";
            }
        }
    } catch (e) {
        console.error("Failed to load DB stats", e);
    }
}

async function purgeDatabase() {
    const purgeDate = document.getElementById('purge-date').value;
    const msgEl = document.getElementById('purge-message');
    
    if (!purgeDate) {
        msgEl.style.color = 'var(--neon-red)';
        msgEl.innerText = 'Please select a date.';
        return;
    }

    if (!confirm(`WARNING: Are you sure you want to delete ALL metric data prior to ${purgeDate}? This cannot be undone.`)) {
        return;
    }

    msgEl.style.color = 'var(--text-muted)';
    msgEl.innerText = 'Purging...';

    try {
        const res = await fetch('../api/admin_db.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'purge', date: purgeDate })
        });
        const data = await res.json();
        if(data.status === 'success') {
            msgEl.style.color = 'var(--neon-green)';
            msgEl.innerText = data.message;
            // Refresh stats to show new count
            fetchDBStats();
        } else {
            msgEl.style.color = 'var(--neon-red)';
            msgEl.innerText = 'Purge failed.';
        }
    } catch (e) {
        console.error("Failed to purge db", e);
        msgEl.style.color = 'var(--neon-red)';
        msgEl.innerText = 'Network error.';
    }
}

// Kick off dashboard initialization on page load
document.addEventListener("DOMContentLoaded", initDashboard);

// Global Secure Logout
async function logoutUser() {
    try {
        await fetch('../api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'logout' })
        });
        window.location.href = 'index.html';
    } catch (e) {
        console.error('Logout failed', e);
        window.location.href = 'index.html';
    }
}
