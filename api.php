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

// Set timezone agar sinkron dengan alat dan waktu lokal
date_default_timezone_set('Asia/Jakarta');

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

// Set timezone di level MySQL agar fungsi DATE() dan NOW() sinkron
$conn->query("SET time_zone = '+07:00'");

// Helper: Ambil konfigurasi jam yang berlaku
function getConfig($conn, $tanggal = null) {
    if (!$tanggal) $tanggal = date('Y-m-d');
    
    // Cek apakah ada config khusus tanggal ini
    $res = $conn->query("SELECT * FROM konfigurasi_jam WHERE tanggal = '$tanggal'");
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }
    
    // Jika tidak ada, ambil yang terbaru sebelum tanggal ini atau default
    $res = $conn->query("SELECT * FROM konfigurasi_jam ORDER BY tanggal DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }
    
    // Default fallback jika tabel kosong
    return ["jam_masuk" => "07:15:00", "jam_pulang" => "14:00:00"];
}

$action = $_GET['action'] ?? '';

if ($action == 'get_siswa') {
    $result = $conn->query("SELECT * FROM siswa ORDER BY nama ASC");
    echo json_encode($result ? $result->fetch_all(MYSQLI_ASSOC) : []);
} 

elseif ($action == 'get_config') {
    echo json_encode(getConfig($conn));
}

elseif ($action == 'save_config') {
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);
    
    $tanggal = $data['tanggal'] ?? date('Y-m-d');
    $jam_masuk = $data['jam_masuk'];
    $jam_pulang = $data['jam_pulang'];
    
    $stmt = $conn->prepare("INSERT INTO konfigurasi_jam (tanggal, jam_masuk, jam_pulang) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE jam_masuk=?, jam_pulang=?");
    $stmt->bind_param("sssss", $tanggal, $jam_masuk, $jam_pulang, $jam_masuk, $jam_pulang);
    
    if ($stmt->execute()) {
        echo json_encode(["msg" => "ok"]);
    } else {
        echo json_encode(["error" => $conn->error]);
    }
    $stmt->close();
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
    $now_time = date('H:i:s');
    $today = date('Y-m-d');
    $config = getConfig($conn, $today);
    
    // Tentukan Tipe dan Status Berdasarkan Aturan Baru:
    // 1. Sebelum Jam Pulang -> Selalu Masuk (bisa Tepat Waktu atau Terlambat)
    // 2. Sesudah Jam Pulang -> Selalu Keluar
    
    if ($now_time < $config['jam_pulang']) {
        $tipe = 'Masuk';
        $status = ($now_time <= $config['jam_masuk']) ? 'Tepat Waktu' : 'Terlambat';
    } else {
        $tipe = 'Keluar';
        $status = 'Selesai';
    }
    
    // Cari data siswa berdasarkan FP_ID
    $res = $conn->query("SELECT id, nama, kelas FROM siswa WHERE fp_id = $fp_id");
    
    if ($res && $res->num_rows > 0) {
        $siswa = $res->fetch_assoc();
        
        // Cek apakah sudah ada absen dengan tipe yang sama hari ini (Anti-spam)
        $check = $conn->query("SELECT id FROM absensi WHERE siswa_id = {$siswa['id']} AND tipe = '$tipe' AND DATE(waktu) = '$today'");
        if ($check && $check->num_rows > 0) {
             echo json_encode([
                 "msg" => "ok", 
                 "status" => "terdaftar", 
                 "info" => "Sudah absen $tipe hari ini",
                 "nama" => $siswa['nama'],
                 "kelas" => $siswa['kelas'],
                 "tipe" => $tipe
             ]);
             exit;
        }

        $stmt = $conn->prepare("INSERT INTO absensi (siswa_id, tipe, status) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $siswa['id'], $tipe, $status);
        if ($stmt->execute()) {
            echo json_encode([
                "msg" => "ok", 
                "status" => "terdaftar", 
                "nama" => $siswa['nama'], 
                "kelas" => $siswa['kelas'],
                "tipe" => $tipe,
                "ket" => ($tipe == 'Masuk' ? $status : 'Selesai')
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

elseif ($action == 'clear_today_logs') {
    $today = date('Y-m-d');
    if ($conn->query("DELETE FROM absensi WHERE DATE(waktu) = '$today'")) {
        echo json_encode(["msg" => "ok"]);
    } else {
        echo json_encode(["error" => $conn->error]);
    }
}

elseif ($action == 'get_logs' || $action == 'export_logs') {
    $start = $_GET['start'] ?? date('Y-m-d');
    $end = $_GET['end'] ?? date('Y-m-d');
    $search = $_GET['search'] ?? '';
    
    // Kita buat query yang lebih canggih untuk dashboard harian
    // Mengelompokkan berdasarkan Siswa dan Tanggal
    
    $where_siswa = "";
    if ($search) {
        $s = $conn->real_escape_string($search);
        $where_siswa = "AND (s.nama LIKE '%$s%' OR s.nis LIKE '%$s%')";
    }

    $sql = "SELECT 
                s.id as siswa_id, s.nama, s.nis, s.kelas,
                DATE(a_masuk.waktu) as tanggal,
                TIME(a_masuk.waktu) as jam_datang,
                a_masuk.status as status_masuk,
                TIME(a_keluar.waktu) as jam_pulang,
                CASE 
                    WHEN a_masuk.id IS NOT NULL AND a_keluar.id IS NOT NULL THEN 'Hadir'
                    WHEN a_masuk.id IS NOT NULL OR a_keluar.id IS NOT NULL THEN 'Tidak Lengkap'
                    ELSE 'Tidak Hadir'
                END as status_kehadiran,
                c.jam_masuk as config_masuk,
                c.jam_pulang as config_pulang
            FROM siswa s
            LEFT JOIN absensi a_masuk ON s.id = a_masuk.siswa_id AND a_masuk.tipe = 'Masuk' AND DATE(a_masuk.waktu) BETWEEN '$start' AND '$end'
            LEFT JOIN absensi a_keluar ON s.id = a_keluar.siswa_id AND a_keluar.tipe = 'Keluar' AND DATE(a_keluar.waktu) = DATE(a_masuk.waktu)
            LEFT JOIN konfigurasi_jam c ON (c.tanggal = DATE(a_masuk.waktu) OR c.tanggal = '2000-01-01')
            WHERE 1=1 $where_siswa
            GROUP BY s.id, DATE(a_masuk.waktu)
            ORDER BY DATE(a_masuk.waktu) DESC, s.nama ASC";

    // Jika filter tanggal sama, pastikan juga menampilkan yang TIDAK HADIR hari itu
    if ($start == $end) {
        $sql = "SELECT 
                    s.id as siswa_id, s.nama, s.nis, s.kelas,
                    '$start' as tanggal,
                    (SELECT TIME(waktu) FROM absensi WHERE siswa_id = s.id AND tipe = 'Masuk' AND DATE(waktu) = '$start' LIMIT 1) as jam_datang,
                    (SELECT status FROM absensi WHERE siswa_id = s.id AND tipe = 'Masuk' AND DATE(waktu) = '$start' LIMIT 1) as status_masuk,
                    (SELECT TIME(waktu) FROM absensi WHERE siswa_id = s.id AND tipe = 'Keluar' AND DATE(waktu) = '$start' LIMIT 1) as jam_pulang,
                    (SELECT COUNT(*) FROM absensi WHERE siswa_id = s.id AND DATE(waktu) = '$start') as total_scan,
                    CASE 
                        WHEN (SELECT COUNT(*) FROM absensi WHERE siswa_id = s.id AND DATE(waktu) = '$start' AND tipe = 'Masuk') > 0 
                             AND (SELECT COUNT(*) FROM absensi WHERE siswa_id = s.id AND DATE(waktu) = '$start' AND tipe = 'Keluar') > 0 THEN 'Hadir'
                        WHEN (SELECT COUNT(*) FROM absensi WHERE siswa_id = s.id AND DATE(waktu) = '$start') > 0 THEN 'Tidak Lengkap'
                        ELSE 'Tidak Hadir'
                    END as status_kehadiran,
                    (SELECT jam_masuk FROM konfigurasi_jam WHERE tanggal = '$start' OR tanggal = '2000-01-01' ORDER BY tanggal DESC LIMIT 1) as config_masuk,
                    (SELECT jam_pulang FROM konfigurasi_jam WHERE tanggal = '$start' OR tanggal = '2000-01-01' ORDER BY tanggal DESC LIMIT 1) as config_pulang
                FROM siswa s
                WHERE 1=1 $where_siswa
                ORDER BY status_kehadiran DESC, s.nama ASC";
    }

    $result = $conn->query($sql);
    $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    if ($action == 'export_logs') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=rekap_kehadiran_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Tanggal', 'Nama', 'NIS', 'Kelas', 'Jam Datang', 'Status Masuk', 'Jam Pulang', 'Kehadiran']);
        foreach ($data as $row) {
            fputcsv($output, [$row['tanggal'], $row['nama'], $row['nis'], $row['kelas'], $row['jam_datang'], $row['status_masuk'], $row['jam_pulang'], $row['status_kehadiran']]);
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
