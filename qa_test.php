<?php
// QA Test Script untuk WatchSMP
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "db_fingerprint_smp";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("QA FAIL: Koneksi DB Gagal\n");
}

echo "=== STARTING QA SIMULATION ===\n\n";

// 0. RESET DB
echo "[0] Mereset Database untuk testing...\n";
$conn->query("TRUNCATE TABLE absensi");
$conn->query("DELETE FROM siswa"); // Use DELETE to trigger cascade if any, or just clear
$conn->query("TRUNCATE TABLE scan_tidak_dikenal");
echo "    -> Database bersih.\n\n";

// Fungsi Helper untuk panggil API
function callAPI($url, $method = 'GET', $data = null) {
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => $method,
            'content' => $data ? json_encode($data) : null
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents("http://127.0.0.1/fp/api.php?action=" . $url, false, $context);
    return json_decode($result, true);
}

// 1. SIMULASI ESP32 SCAN JARI BARU (ID 99)
echo "[1] ESP32 mendeteksi jari baru (ID 99)...\n";
$res1 = callAPI('add_absen', 'POST', ['fp_id' => 99]);
echo "    -> Response API: " . json_encode($res1) . "\n";
if ($res1['status'] == 'tidak_dikenal') echo "    -> QA PASS: Jari ditolak dan masuk antrian.\n\n";
else echo "    -> QA FAIL: Status bukan tidak_dikenal.\n\n";

// 2. CEK ANTRIAN (Front-end)
echo "[2] Web mengecek antrian Sidik Jari Baru...\n";
$res2 = callAPI('get_unknown_scans');
echo "    -> Total Antrian: " . count($res2) . "\n";
if (count($res2) == 1 && $res2[0]['fp_id'] == 99) echo "    -> QA PASS: ID 99 ada di antrian.\n\n";
else echo "    -> QA FAIL: ID 99 tidak ada di antrian.\n\n";

// 3. SIMULASI PENDAFTARAN SISWA (Front-end Submit Form)
echo "[3] Web mendaftarkan ID 99 sebagai Siswa 'QA Tester'...\n";
$payload = [
    'nis' => 'QA-001',
    'nama' => 'QA Tester',
    'kelas' => '10A',
    'fp_id' => 99,
    'keterangan_masalah' => 'Testing'
];
$res3 = callAPI('add_siswa', 'POST', $payload);
echo "    -> Response API: " . json_encode($res3) . "\n";

// Cek apakah antrian kosong setelah daftar
$cekAntrian = callAPI('get_unknown_scans');
if (count($cekAntrian) == 0) echo "    -> QA PASS: Pendaftaran berhasil dan antrian ID 99 otomatis terhapus.\n\n";
else echo "    -> QA FAIL: Antrian masih ada isinya.\n\n";

// 4. SIMULASI ESP32 SCAN JARI YANG SUDAH TERDAFTAR
echo "[4] ESP32 mendeteksi jari (ID 99) lagi...\n";
$res4 = callAPI('add_absen', 'POST', ['fp_id' => 99]);
echo "    -> Response API: " . json_encode($res4) . "\n";
if ($res4['status'] == 'terdaftar') echo "    -> QA PASS: Absen berhasil dicatat!\n\n";
else echo "    -> QA FAIL: Jari tidak dikenali sebagai terdaftar.\n\n";

// 5. SIMULASI HAPUS SINKRONISASI
echo "[5] Web menghapus ID 99 dari Sensor dan Database...\n";
$res5 = callAPI('delete_by_fpid&fp_id=99');
echo "    -> Response Hapus API: " . json_encode($res5) . "\n";

$cekSiswa = callAPI('get_siswa');
$found = false;
foreach($cekSiswa as $s) {
    if($s['fp_id'] == 99) $found = true;
}
if (!$found) echo "    -> QA PASS: Siswa dengan ID 99 berhasil hilang dari Database.\n\n";
else echo "    -> QA FAIL: Siswa masih ada di DB.\n\n";

echo "=== QA SIMULATION COMPLETED ===\n";
?>