<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$file = 'emulator_state.json';

// Load state
if (file_exists($file)) {
    $state = json_decode(file_get_contents($file), true);
} else {
    $state = ["id" => -1, "last_enrolled" => 0];
}

$path = $_SERVER['PATH_INFO'] ?? '';

// Trigger scan: ?send_id=12
if (isset($_GET['send_id'])) {
    $state['id'] = (int)$_GET['send_id'];
    file_put_contents($file, json_encode($state));
    echo json_encode(["msg" => "Simulasi Jari ID " . $state['id'] . " Aktif"]);
    exit;
}

// Endpoint /scan (dipanggil web)
if (strpos($path, '/scan') !== false) {
    echo json_encode(["id" => $state['id'], "confidence" => 150]);
    if ($state['id'] != -1) {
        $state['id'] = -1; // Reset setelah dibaca
        file_put_contents($file, json_encode($state));
    }
} 

// Endpoint /enroll?id=N
elseif (strpos($path, '/enroll') !== false) {
    $enroll_id = (int)$_GET['id'];
    $state['last_enrolled'] = $enroll_id;
    file_put_contents($file, json_encode($state));
    echo json_encode(["msg" => "ok", "enrolling_id" => $enroll_id]);
}

// Endpoint /delete?id=N
elseif (strpos($path, '/delete') !== false) {
    $del_id = (int)$_GET['id'];
    echo json_encode(["msg" => "ok", "deleted_id" => $del_id]);
}

// Endpoint /status
elseif (strpos($path, '/status') !== false) {
    echo json_encode(["status" => "ready"]);
}

else {
    echo json_encode(["status" => "ready", "emulator" => true]);
}
?>
