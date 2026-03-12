<?php
header('Content-Type: application/json');
require_once '../db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $query = "SELECT id, username, role, account_status, last_login, created_at FROM users";
    $result = $conn->query($query);
    
    $users = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $users]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch users']);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'update_user') {
        $id = $data['id'] ?? null;
        $role = $data['role'] ?? null;
        $status = $data['status'] ?? null;
        
        if ($id) {
            $stmt = $conn->prepare("UPDATE users SET role = ?, account_status = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ssi", $role, $status, $id);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'User updated successfully']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Database update failed']);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}

$conn->close();
?>
