<?php
header('Content-Type: application/json');

date_default_timezone_set('Asia/Kolkata');

$time_anchor = isset($_GET['time']) ? $_GET['time'] : null;

if (!$time_anchor) {
    echo json_encode(["status" => "error", "message" => "Missing required ?time parameter"]);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $time_anchor)) {
    echo json_encode(["status" => "error", "message" => "Invalid time format"]);
    exit;
}

$python_script = realpath(__DIR__ . '/../backend/ml/predict_on_demand.py');

if (!$python_script) {
    echo json_encode(["status" => "error", "message" => "Critical ML Inference Engine missing"]);
    exit;
}

$cmd = 'python ' . escapeshellarg($python_script) . ' --time ' . escapeshellarg($time_anchor);

$output = shell_exec($cmd);

if ($output === null) {
    echo json_encode(["status" => "error", "message" => "Python inference execution failed entirely"]);
    exit;
}

echo $output;
?>
