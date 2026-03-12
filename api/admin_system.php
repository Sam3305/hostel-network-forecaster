<?php
header('Content-Type: application/json');
require_once '../db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $query = "SELECT * FROM system_config LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode(['status' => 'success', 'data' => $row]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch configuration']);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'update_config') {
        $surge = $data['surge_threshold_mbps'] ?? null;
        $latency = $data['congestion_latency_ms'] ?? null;
        $drop = $data['congestion_drop_pct'] ?? null;
        $banner = $data['announcement_banner'] ?? '';
        
        $stmt = $conn->prepare("UPDATE system_config SET surge_threshold_mbps = ?, congestion_latency_ms = ?, congestion_drop_pct = ?, announcement_banner = ? WHERE id = 1");
        if ($stmt) {
            $stmt->bind_param("ddds", $surge, $latency, $drop, $banner);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Configuration updated successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database update failed']);
            }
            $stmt->close();
        } else {
             echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}

$conn->close();
?>
