<?php
error_reporting(0);
header('Content-Type: application/json');
require_once '../db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    $conn->close();
    exit;
}

$stats = [];

$res = $conn->query("SELECT COUNT(*) as count FROM network_captures");
if ($res) {
    $stats['total_captures'] = (int)$res->fetch_assoc()['count'];
}

$res = $conn->query("SELECT COUNT(*) as count FROM network_metrics");
if ($res) {
    $stats['dataset_rows'] = (int)$res->fetch_assoc()['count'];
}

$res = $conn->query("SELECT COALESCE(SUM(packets_captured), 0) as total FROM network_captures");
if ($res) {
    $stats['total_packets'] = (int)$res->fetch_assoc()['total'];
}

$res = $conn->query("SELECT MAX(capture_timestamp) as last_capture FROM network_captures");
if ($res) {
    $row = $res->fetch_assoc();
    $stats['last_capture_time'] = $row['last_capture'];

    if ($row['last_capture']) {
        $lastTs = strtotime($row['last_capture']);
        $diff = time() - $lastTs;
        if ($diff < 120) {
            $stats['capture_status'] = 'Active';
        } elseif ($diff < 3600) {
            $stats['capture_status'] = 'Idle (' . round($diff / 60) . 'm ago)';
        } else {
            $stats['capture_status'] = 'Offline';
        }
        $lastTime = date('H:i', $lastTs);
        $stats['capture_status_detail'] = $stats['capture_status'] . ' (Last ' . $lastTime . ')';
    } else {
        $stats['capture_status'] = 'No Data';
        $stats['capture_status_detail'] = 'No captures yet';
    }
}

$capturesDir = realpath(__DIR__ . '/../backend/live/captures');
$totalSize = 0;
if ($capturesDir && is_dir($capturesDir)) {
    $files = glob($capturesDir . '/*.pcap');
    foreach ($files as $file) {
        $totalSize += filesize($file);
    }
}
if ($totalSize >= 1073741824) {
    $stats['dataset_size'] = number_format($totalSize / 1073741824, 1) . ' GB';
} elseif ($totalSize >= 1048576) {
    $stats['dataset_size'] = number_format($totalSize / 1048576, 1) . ' MB';
} elseif ($totalSize > 0) {
    $stats['dataset_size'] = number_format($totalSize / 1024, 1) . ' KB';
} else {
    $stats['dataset_size'] = '0 KB';
}

$evalFile = realpath(__DIR__ . '/../backend/ml/evaluation_results.json');
if ($evalFile && file_exists($evalFile)) {
    $evalData = json_decode(file_get_contents($evalFile), true);

    $surgeSum = 0; $congSum = 0; $horizonCount = 0;
    foreach ($evalData as $horizon => $hData) {
        if (isset($hData['classification_accuracy'])) {
            $surgeSum += ($hData['classification_accuracy']['surge'] ?? 0);
            $congSum += ($hData['classification_accuracy']['congestion'] ?? 0);
            $horizonCount++;
        }
    }
    if ($horizonCount > 0) {
        $stats['model_surge_accuracy'] = round($surgeSum / $horizonCount, 4);
        $stats['model_congestion_accuracy'] = round($congSum / $horizonCount, 4);
        $stats['model_avg_accuracy'] = round(($surgeSum + $congSum) / (2 * $horizonCount), 4);
        $stats['horizon_count'] = $horizonCount;
    }

    $stats['last_training'] = date('Y-m-d H:i', filemtime($evalFile));
    $stats['training_dataset_size'] = $stats['dataset_rows'];
} else {
    $stats['model_surge_accuracy'] = 0;
    $stats['model_congestion_accuracy'] = 0;
    $stats['model_avg_accuracy'] = 0;
    $stats['last_training'] = 'Never';
    $stats['training_dataset_size'] = 0;
}

$res = $conn->query("SELECT metric_timestamp, throughput_mbps, burstiness FROM network_metrics ORDER BY metric_timestamp DESC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $ts = date('H:i', strtotime($row['metric_timestamp']));
    $burst = (float)$row['burstiness'];
    if ($burst > 10000) {
        $stats['system_log'] = $ts . ' Burst spike detected (' . number_format($burst, 0) . ')';
        $stats['system_log_level'] = 'warning';
    } else {
        $stats['system_log'] = $ts . ' System nominal';
        $stats['system_log_level'] = 'ok';
    }
} else {
    $stats['system_log'] = 'No telemetry data';
    $stats['system_log_level'] = 'muted';
}

echo json_encode(['status' => 'success', 'data' => $stats]);

$conn->close();
?>
