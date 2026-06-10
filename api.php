<?php
// Letakkan CORS di paling atas sebelum output apapun
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "127.0.0.1"; // Gunakan 127.0.0.1 jika localhost tidak bisa
$user = "root";
$pass = "";
$db   = "db_fingerprint_smp";

// Jika Laragon menggunakan port berbeda (misal 3307), tambahkan parameter port:
// $conn = new mysqli($host, $user, $pass, $db, 3307);
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(["error" => "Koneksi database gagal: " . $conn->connect_error]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action == 'get_siswa') {
    $result = $conn->query("SELECT * FROM siswa ORDER BY nama ASC");
    echo json_encode($result ? $result->fetch_all(MYSQLI_ASSOC) : []);
} 

elseif ($action == 'get_next_fpid') {
    $result = $conn->query("SELECT MAX(fp_id) as max_id FROM siswa");
    $row = $result->fetch_assoc();
    $next_id = ($row['max_id'] ?? 0) + 1;
    // Hindari ID 0 jika sensor menggunakannya sebagai unknown
    if ($next_id == 0) $next_id = 1; 
    echo json_encode(["next_id" => $next_id]);
}

elseif ($action == 'add_siswa') {
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);
    
    if (!$data) {
        echo json_encode(["error" => "Data tidak valid atau JSON rusak"]);
        exit;
    }
    
    // Validasi field wajib
    if (empty($data['nis']) || empty($data['nama']) || empty($data['fp_id'])) {
        echo json_encode(["error" => "NIS, Nama, dan FP ID wajib diisi"]);
        exit;
    }
    
    $nis = $conn->real_escape_string($data['nis']);
    $nama = $conn->real_escape_string($data['nama']);
    $kelas = $conn->real_escape_string($data['kelas'] ?? '');
    $fp_id = (int)$data['fp_id'];
    $ket = $conn->real_escape_string($data['keterangan_masalah'] ?? '');

    $stmt = $conn->prepare("INSERT INTO siswa (nis, nama, kelas, fp_id, keterangan_masalah) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(["error" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("sssis", $nis, $nama, $kelas, $fp_id, $ket);
    
    if ($stmt->execute()) {
        // Jika berhasil tambah siswa, hapus ID ini dari tabel scan_tidak_dikenal
        $conn->query("DELETE FROM scan_tidak_dikenal WHERE fp_id = $fp_id");
        echo json_encode(["msg" => "ok"]);
    } else {
        echo json_encode(["error" => "Gagal simpan siswa: " . $stmt->error]);
    }
    $stmt->close();
}

elseif ($action == 'delete_siswa') {
    $id = (int)($_GET['id'] ?? 0);
    $conn->query("DELETE FROM siswa WHERE id = $id");
    echo json_encode(["msg" => "ok"]);
}

elseif ($action == 'delete_by_fpid') {
    $fp_id = (int)($_GET['fp_id'] ?? 0);
    $conn->query("DELETE FROM siswa WHERE fp_id = $fp_id");
    echo json_encode(["msg" => "ok"]);
}

elseif ($action == 'add_absen') {
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);
    if (!$data || !isset($data['fp_id'])) {
        echo json_encode(["error" => "Data absen tidak valid. Raw: " . $raw_data]);
        exit;
    }
    
    $fp_id = (int)$data['fp_id'];
    $tipe = $data['tipe'] ?? 'Masuk';
    
    // Cari data siswa berdasarkan FP_ID
    $res = $conn->query("SELECT id, nama, kelas FROM siswa WHERE fp_id = $fp_id");
    
    if ($res && $res->num_rows > 0) {
        $siswa = $res->fetch_assoc();
        $stmt = $conn->prepare("INSERT INTO absensi (siswa_id, tipe) VALUES (?, ?)");
        $stmt->bind_param("is", $siswa['id'], $tipe);
        if ($stmt->execute()) {
            echo json_encode([
                "msg" => "ok", 
                "status" => "terdaftar", 
                "nama" => $siswa['nama'], 
                "kelas" => $siswa['kelas']
            ]);
        } else {
            echo json_encode(["error" => "Gagal catat absen: " . $conn->error]);
        }
    } else {
        // ... (sisanya tetap sama)
        $check = $conn->query("SELECT id FROM scan_tidak_dikenal WHERE fp_id = $fp_id");
        if ($check && $check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO scan_tidak_dikenal (fp_id) VALUES (?)");
            $stmt->bind_param("i", $fp_id);
            if ($stmt->execute()) {
                echo json_encode(["msg" => "ok", "status" => "tidak_dikenal", "info" => "ID baru disimpan ke scan_tidak_dikenal"]);
            } else {
                echo json_encode(["error" => "Gagal simpan ID baru: " . $conn->error]);
            }
        } else {
            echo json_encode(["msg" => "ok", "status" => "tidak_dikenal", "info" => "ID sudah ada di antrian"]);
        }
    }
}

elseif ($action == 'update_siswa') {
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);
    
    $id = (int)($data['id'] ?? 0);
    $nis = $conn->real_escape_string($data['nis'] ?? '');
    $nama = $conn->real_escape_string($data['nama'] ?? '');
    $kelas = $conn->real_escape_string($data['kelas'] ?? '');
    $ket = $conn->real_escape_string($data['keterangan_masalah'] ?? '');

    $stmt = $conn->prepare("UPDATE siswa SET nis=?, nama=?, kelas=?, keterangan_masalah=? WHERE id=?");
    $stmt->bind_param("ssssi", $nis, $nama, $kelas, $ket, $id);
    
    if ($stmt->execute()) {
        echo json_encode(["msg" => "ok"]);
    } else {
        echo json_encode(["error" => "Gagal update: " . $conn->error]);
    }
    $stmt->close();
}

elseif ($action == 'get_siswa_by_id') {
    $id = (int)($_GET['id'] ?? 0);
    $res = $conn->query("SELECT * FROM siswa WHERE id = $id");
    echo json_encode($res ? $res->fetch_assoc() : ["error" => "Not found"]);
}

elseif ($action == 'get_unknown_scans') {
    $result = $conn->query("SELECT * FROM scan_tidak_dikenal ORDER BY waktu DESC LIMIT 10");
    echo json_encode($result ? $result->fetch_all(MYSQLI_ASSOC) : []);
}

elseif ($action == 'clear_unknown') {
    $id = (int)($_GET['id'] ?? 0);
    $conn->query("DELETE FROM scan_tidak_dikenal WHERE id = $id");
    echo json_encode(["msg" => "ok"]);
}

elseif ($action == 'clear_all_unknown') {
    $conn->query("TRUNCATE TABLE scan_tidak_dikenal");
    echo json_encode(["msg" => "ok"]);
}

elseif ($action == 'get_logs' || $action == 'export_logs') {
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    $search = $_GET['search'] ?? '';
    $hide_dup = ($_GET['hide_duplicates'] ?? 'false') === 'true';
    
    $where = [];
    if ($start) $where[] = "a.waktu >= '" . $conn->real_escape_string($start) . "'";
    if ($end) $where[] = "a.waktu <= '" . $conn->real_escape_string($end) . "'";
    if ($search) {
        $s = $conn->real_escape_string($search);
        $where[] = "(s.nama LIKE '%$s%' OR s.nis LIKE '%$s%')";
    }
    
    $where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
    
    if ($hide_dup) {
        // Ambil ID pertama dari tiap siswa dalam kriteria yang dipilih
        $sub_where = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT a.id, a.waktu, a.tipe, s.nama, s.nis, s.kelas 
                FROM absensi a 
                JOIN siswa s ON a.siswa_id = s.id 
                WHERE a.id IN (SELECT MIN(id) FROM absensi a $sub_where GROUP BY siswa_id)
                ORDER BY a.waktu DESC LIMIT 1000";
    } else {
        $sql = "SELECT a.*, s.nama, s.nis, s.kelas 
                FROM absensi a 
                JOIN siswa s ON a.siswa_id = s.id 
                $where_sql
                ORDER BY a.waktu DESC LIMIT 1000";
    }
    
    $result = $conn->query($sql);
    $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    if ($action == 'export_logs') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=log_kehadiran_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Waktu', 'Nama', 'NIS', 'Kelas', 'Tipe']);
        foreach ($data as $row) {
            fputcsv($output, [$row['waktu'], $row['nama'], $row['nis'], $row['kelas'], $row['tipe']]);
        }
        fclose($output);
        exit;
    }

    echo json_encode($data);
}

elseif ($action == 'get_latest_event') {
    $last_absen_id = (int)($_GET['last_absen_id'] ?? 0);
    $last_unknown_id = (int)($_GET['last_unknown_id'] ?? 0);
    
    // Cek apakah ada absensi baru (sudah terdaftar)
    $resAbsen = $conn->query("SELECT a.id, s.nama, s.kelas, s.fp_id 
                             FROM absensi a 
                             JOIN siswa s ON a.siswa_id = s.id 
                             WHERE a.id > $last_absen_id 
                             ORDER BY a.id DESC LIMIT 1");
                             
    if ($resAbsen && $resAbsen->num_rows > 0) {
        $row = $resAbsen->fetch_assoc();
        echo json_encode(["type" => "terdaftar", "data" => $row]);
        exit;
    }
    
    // Cek apakah ada scan baru yang tidak dikenal
    $resUnknown = $conn->query("SELECT id, fp_id 
                               FROM scan_tidak_dikenal 
                               WHERE id > $last_unknown_id 
                               ORDER BY id DESC LIMIT 1");
                               
    if ($resUnknown && $resUnknown->num_rows > 0) {
        $row = $resUnknown->fetch_assoc();
        echo json_encode(["type" => "tidak_dikenal", "data" => $row]);
        exit;
    }
    
    echo json_encode(["type" => "none"]);
}
?>
