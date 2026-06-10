# 🛡️ WatchSMP — Sistem Absensi Sidik Jari Real-Time

WatchSMP adalah sistem absensi berbasis ESP32 dan Fingerprint Sensor yang terintegrasi dengan dashboard monitoring berbasis web. Sistem ini menggunakan arsitektur **Push Mechanism** untuk menjamin data tidak hilang meskipun alat mengalami restart mendadak.

## 🚀 Fitur Utama

1.  **Push-to-Server Architecture**: ESP32 langsung mengirim data ke database sesaat setelah jari terdeteksi. Solusi efektif untuk masalah *brownout* atau restart pada hardware.
2.  **LCD Interaktif dengan Scrolling**: Menampilkan Nama dan Kelas siswa pada LCD alat dengan efek teks berjalan (scrolling).
3.  **Advanced Log Filtering**:
    *   Cari berdasarkan Nama atau NIS.
    *   Filter berdasarkan rentang Tanggal dan Jam.
    *   Fitur **Hide Duplicates**: Menghilangkan duplikasi absen orang yang sama dalam jangka waktu tertentu.
4.  **Export to Excel**: Mengunduh laporan kehadiran dalam format CSV yang kompatibel dengan Excel.
5.  **Smart Enrollment**: Pendaftaran sidik jari baru langsung dari web dashboard dengan pencarian ID otomatis dari database.
6.  **Remote Data Management**: Edit dan hapus data siswa (termasuk menghapus data di memori sensor) langsung dari dashboard.

---

## 🛠️ Persiapan Hardware & Software

### Hardware:
*   ESP32 DevKit V1
*   Sensor Sidik Jari (AS608 / Adafruit)
*   LCD I2C 16x2
*   Kabel Jumper & Breadboard

### Software:
*   **Laragon** (PHP 7.4+ & MySQL)
*   **Arduino IDE** (Dengan library: `Adafruit Fingerprint`, `LiquidCrystal_I2C`, `ArduinoJson`)

---

## ⚙️ Konfigurasi Jaringan (PENTING)

Sistem ini sangat bergantung pada alamat IP. Konfigurasi dipusatkan di bagian atas file agar mudah diubah.

### 1. Konfigurasi ESP32 (`absensi.ino`)
Buka file di Arduino IDE, cari bagian paling atas:
```cpp
// ================= CONFIG JARINGAN =================
const char* ssid     = "NAMA_WIFI_ANDA";      
const char* password = "PASSWORD_WIFI_ANDA";   
const char* serverIP = "192.168.100.14"; // IP Laptop/Server (Cek via ipconfig)
// ===================================================
```

### 2. Konfigurasi Dashboard (`absensi.html`)
Buka file dengan Text Editor, cari bagian tag `<script>` paling atas:
```javascript
// ================= CONFIG JARINGAN =================
const ESP32_IP  = "http://192.168.100.20"; // IP Alat (Cek di Serial Monitor)
const SERVER_IP = "http://localhost/fp";   
// ===================================================
```

---

## 📖 Panduan Penggunaan

### Pendaftaran Siswa Baru:
1.  Klik tombol **"Rekam Jari Baru di Sensor"** di Dashboard.
2.  Tempel jari di sensor (2x) sesuai instruksi di LCD alat.
3.  Buka menu **"Sidik Jari Baru"** di sidebar.
4.  Klik **"Daftarkan Sekarang"**, isi nama dan NIS, lalu simpan.

### Monitoring & Laporan:
1.  Buka menu **"Log Kehadiran"**.
2.  Gunakan kolom **"Cari Nama"** atau pilih rentang tanggal.
3.  Centang **"Sembunyikan Duplikat"** untuk mendapatkan laporan yang bersih (1 baris per orang).
4.  Klik **"Export Excel"** untuk mengunduh laporan ke komputer.

---

## 📝 Detail API (Endpoint)

| Method | Endpoint | Fungsi |
| :--- | :--- | :--- |
| `POST` | `api.php?action=add_absen` | ESP32 mengirim data scan (ID Jari) |
| `GET` | `api.php?action=get_logs` | Web mengambil data kehadiran dengan filter |
| `GET` | `api.php?action=export_logs` | Mengunduh file CSV berdasarkan filter |
| `POST` | `api.php?action=update_siswa` | Mengubah data siswa di database |

---

## ⚠️ Troubleshooting

1.  **Status Disconnected**: 
    *   Pastikan Laptop dan ESP32 terhubung ke WiFi yang sama.
    *   Pastikan IP di `absensi.html` sesuai dengan IP yang muncul di Serial Monitor Arduino.
2.  **Data Tidak Masuk ke DB**:
    *   Cek apakah IP laptop (`serverIP`) di kodingan Arduino sudah benar.
    *   Matikan Firewall Windows jika koneksi dari ESP32 diblokir.
3.  **LCD Kosong/Kotak-kotak**:
    *   Putar potensiometer di belakang modul I2C LCD untuk mengatur kontras.
