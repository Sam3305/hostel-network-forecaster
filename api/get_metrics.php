<?php
header('Content-Type: application/json');
require_once '../db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$requested_time = isset($_GET['time']) ? $_GET['time'] : null;
$wants_ground_truth = isset($_GET['ground_truth']) ? (int)$_GET['ground_truth'] : 0;
$horizon = isset($_GET['horizon']) ? $_GET['horizon'] : '5m';

$where_clause = "";
$param_type = "";
$param_value = [];

if ($requested_time) {
    $where_clause = "WHERE metric_timestamp <= ?";
    $param_type = "s";
    $param_value[] = $requested_time;
}

$sql = "SELECT metric_timestamp, throughput_mbps, packet_rate, active_flows, latency_ms, 
        retransmission_rate, packet_drop_rate, burstiness, flow_entropy 
        FROM network_metrics 
        $where_clause
        ORDER BY metric_timestamp DESC 
        LIMIT 60";

$stmt = $conn->prepare($sql);
if ($requested_time) {
    $stmt->bind_param($param_type, ...$param_value);
}
$stmt->execute();
$result = $stmt->get_result();

$response = array();
$response['metrics'] = array();
$response['ground_truth'] = array();

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $response['metrics'][] = $row;
    }
}

$response['metrics'] = array_reverse($response['metrics']);

if ($requested_time && $wants_ground_truth) {
    $pad_counts = ['2s' => 2, '5s' => 5, '10s' => 10, '1m' => 60, '5m' => 300, '15m' => 900, '60m' => 3600];
    $limit = isset($pad_counts[$horizon]) ? $pad_counts[$horizon] : 300;

    $gt_sql = "SELECT metric_timestamp, throughput_mbps, packet_rate, active_flows, latency_ms, 
            retransmission_rate, packet_drop_rate, burstiness, flow_entropy 
            FROM network_metrics 
            WHERE metric_timestamp > ?
            ORDER BY metric_timestamp ASC 
            LIMIT ?";
            
    $gt_stmt = $conn->prepare($gt_sql);
    $gt_stmt->bind_param("si", $requested_time, $limit);
    $gt_stmt->execute();
    $gt_result = $gt_stmt->get_result();

    if ($gt_result && $gt_result->num_rows > 0) {
        while($gt_row = $gt_result->fetch_assoc()) {
            $response['ground_truth'][] = $gt_row;
        }
    }
}

echo json_encode($response);

$conn->close();
?>
