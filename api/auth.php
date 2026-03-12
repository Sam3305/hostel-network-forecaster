<?php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $action = $data['action'] ?? '';

    if ($action === 'login') {
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');

        if (empty($username) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Username and password required']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, username, password, role, account_status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($row['account_status'] === 'suspended') {
                 echo json_encode(['status' => 'error', 'message' => 'Account is suspended']);
                 exit;
            }
            
            $passwordValid = false;
            if (password_verify($password, $row['password'])) {
                $passwordValid = true;
            } elseif ($password === $row['password']) {
                $passwordValid = true;
            }

            if (!$passwordValid) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
                exit;
            }

            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $row['id']);
            $updateStmt->execute();

            echo json_encode(['status' => 'success', 'role' => $row['role']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        }
        $stmt->close();
    } elseif ($action === 'logout') {
        session_destroy();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}
$conn->close();
?>
