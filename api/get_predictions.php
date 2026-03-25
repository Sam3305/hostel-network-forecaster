<?php
header('Content-Type: application/json');
require_once '../db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$requested_time = isset($_GET['time']) ? $_GET['time'] : null;

$horizon_offsets = [300 => '5m', 900 => '15m', 3600 => '60m'];
// Add 1s-60s dense offsets
for ($i = 1; $i <= 60; $i++) {
    $horizon_offsets[$i] = $i . 's';
}

$sql = "";
$stmt = null;

if ($requested_time) {
    $sql = "SELECT target_time, predicted_throughput_mbps, predicted_packet_rate, 
            surge_probability_pct, congestion_risk_pct, predicted_latency_ms, 
            predicted_active_flows, predicted_retrans_rate, predicted_burstiness,
            TIMESTAMPDIFF(SECOND, created_at, target_time) as offset_sec
            FROM forecast_results 
            WHERE created_at = (
                SELECT MAX(created_at) FROM forecast_results WHERE created_at <= ?
            )";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $requested_time);
} else {
    $sql = "SELECT target_time, predicted_throughput_mbps, predicted_packet_rate, 
            surge_probability_pct, congestion_risk_pct, predicted_latency_ms, 
            predicted_active_flows, predicted_retrans_rate, predicted_burstiness,
            TIMESTAMPDIFF(SECOND, created_at, target_time) as offset_sec
            FROM forecast_results 
            WHERE created_at = (SELECT MAX(created_at) FROM forecast_results)";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

$predictions_dict = [];
$latest_surge = 0;
$latest_congestion = 0;

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $offset = (int)$row['offset_sec'];
        $horizon_key = isset($horizon_offsets[$offset]) ? $horizon_offsets[$offset] : null;
        
        if ($horizon_key) {
            $predictions_dict[$horizon_key] = $row;
            if ($offset >= 60) {
                $latest_surge = $row['surge_probability_pct'];
                $latest_congestion = $row['congestion_risk_pct'];
            }
        }
    }
}

$eval_path = '../backend/ml/evaluation_results.json';
$eval_results = [];
if (file_exists($eval_path)) {
    $eval_results = json_decode(file_get_contents($eval_path), true);
}

$response = [
    'status' => 'success',
    'predictions' => $predictions_dict,
    'latest_surge_probability' => (float) $latest_surge,
    'latest_congestion_risk' => (float) $latest_congestion
];

// Attach accuracy for the default or requested horizon
// Defaulting to 5m for the summary metrics if not specified
$horizon = isset($_GET['horizon']) ? $_GET['horizon'] : '5m';
if (isset($eval_results[$horizon]['classification_accuracy']['congestion'])) {
    $response['model_accuracy'] = (float)$eval_results[$horizon]['classification_accuracy']['congestion'] * 100;
} else {
    $response['model_accuracy'] = 0;
}

echo json_encode($response);

$conn->close();
?>
