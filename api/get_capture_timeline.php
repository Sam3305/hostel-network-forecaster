<?php
header('Content-Type: application/json');
require_once '../db_connect.php';

$sql = "SELECT capture_timestamp, SUM(packets_captured) as usage_metric FROM network_captures GROUP BY capture_timestamp ORDER BY capture_timestamp ASC";
$result = $conn->query($sql);

$timestamps = array();
$intensities = array();
$max_usage = 0;

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $usage = (float)$row['usage_metric'];
        $timestamps[] = $row['capture_timestamp'];
        $intensities[] = $usage;
        if ($usage > $max_usage) {
            $max_usage = $usage;
        }
    }
}

$normalized = array();
foreach ($intensities as $intensity) {
    if ($max_usage > 0) {
        $normalized[] = $intensity / $max_usage;
    } else {
        $normalized[] = 0;
    }
}

echo json_encode([
    'status' => 'success',
    'timestamps' => $timestamps,
    'intensities' => $normalized
]);

$conn->close();
?>
