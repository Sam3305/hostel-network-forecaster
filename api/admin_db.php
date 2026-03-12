<?php
header('Content-Type: application/json');
require_once '../db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stats = [];
    
    $res = $conn->query("SELECT COUNT(*) as count FROM network_metrics");
    if ($res) {
        $stats['metrics_count'] = $res->fetch_assoc()['count'];
    }
    
    $res = $conn->query("SELECT COUNT(*) as count FROM network_captures");
    if ($res) {
        $stats['captures_count'] = $res->fetch_assoc()['count'];
    }
    
    $res = $conn->query("SELECT MIN(metric_timestamp) as start_date, MAX(metric_timestamp) as end_date FROM network_metrics");
    if ($res) {
        $range = $res->fetch_assoc();
        $stats['start_date'] = $range['start_date'];
        $stats['end_date'] = $range['end_date'];
    }
    
    echo json_encode(['status' => 'success', 'data' => $stats]);
    
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'purge') {
        $purge_date = $data['date'] ?? null;
        
        if ($purge_date) {
            $purge_date = $purge_date . " 23:59:59";
            $stmt = $conn->prepare("DELETE FROM network_metrics WHERE metric_timestamp <= ?");
            if ($stmt) {
                $stmt->bind_param("s", $purge_date);
                if ($stmt->execute()) {
                    $deleted = $stmt->affected_rows;
                    echo json_encode(['status' => 'success', 'message' => "Successfully purged $deleted rows."]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Database deletion failed']);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Purge date is required']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}

$conn->close();
?>
