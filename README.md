# 🛡️ WatchSMP: Sistem Monitoring Fingerprint Siswa Khusus

Sistem monitoring kehadiran siswa berbasis IoT menggunakan ESP32, sensor sidik jari AS608, dan Dashboard Web real-time. Project ini dirancang untuk memantau siswa tertentu (siswa pantauan) dengan notifikasi instan di dashboard tanpa refresh.

---

## 🚀 Fitur Utama

1.  **Push-to-Server Architecture**: ESP32 mengirim data langsung ke database. Data aman meskipun alat restart.
2.  **WiFi Manager & Persistent Config**: ESP32 masuk ke mode Access Point jika WiFi tidak ditemukan. Konfigurasi WiFi dan IP Server disimpan di memori internal (LittleFS).
3.  **Smart Attendance Logic**: Otomatis menentukan status "Masuk" atau "Keluar" berdasarkan jam operasional yang bisa dikonfigurasi.
4.  **Discovery-Based Registration**: Daftar sidik jari baru langsung dari dashboard tanpa input ID manual di kode.
5.  **Real-time Monitoring**: Dashboard otomatis mendeteksi kehadiran secara instan.
6.  **Remote Command**: Masuk ke mode "Enroll" atau "Delete" sensor langsung dari web.
7.  **LCD Interaktif**: Menampilkan nama dan kelas siswa dengan efek teks berjalan (*scrolling*).
8.  **Status Kehadiran Cerdas**: Mendeteksi status "Hadir", "Tidak Lengkap" (hanya satu kali scan), dan "Tidak Hadir".
9.  **Log & Export**: Rekap kehadiran lengkap dengan filter dan fitur ekspor ke Excel/CSV.

---

## 🛠️ Persiapan Hardware

### Komponen:
*   **ESP32 DevKit V1**
*   **Sensor Fingerprint** (AS608 / R307 / sejenis)
*   **LCD I2C 16x2**
*   **Kabel Jumper**

### Skema Pin (Wiring):
| Komponen | Pin Alat | Pin ESP32 | Keterangan |
| :--- | :--- | :--- | :--- |
| **Fingerprint** | VCC | 3.3V | |
| | GND | GND | |
| | TX | GPIO 16 (RX2) | |
| | RX | GPIO 17 (TX2) | |
| **LCD I2C** | VCC | 5V (VIN) | |
| | GND | GND | |
| | SDA | GPIO 21 | |
| | SCL | GPIO 22 | |

---

## 💻 Persiapan Software & Server

### 1. Setup Database (Laragon/XAMPP)
1.  Buat database baru bernama: `db_fingerprint_smp`.
2.  Import file `database.sql` ke database tersebut.

### 2. Konfigurasi Backend (`api.php`)
Pastikan project berada di folder server web (misal: `C:\laragon\www\fp\`).
Sesuaikan konfigurasi database jika Anda menggunakan password:
```php
$host = "127.0.0.1";
$user = "root";
$pass = ""; 
$db   = "db_fingerprint_smp";
```

### 3. Konfigurasi Frontend (`absensi.html`)
Buka `absensi.html`, sesuaikan `SERVER_URL` dengan alamat server Anda:
```javascript
const SERVER_URL = "http://localhost/fp"; 
```

---

## 📡 Flash ESP32 & Setup WiFi

1.  Buka file `absensi/absensi.ino` di Arduino IDE.
2.  Install Library:
    *   `Adafruit Fingerprint Sensor Library`
    *   `LiquidCrystal I2C`
    *   `ArduinoJson`
    *   `WiFiManager` (oleh tzapu)
3.  Upload ke ESP32.
4.  **Konfigurasi WiFi & IP Server:**
    *   Saat pertama kali nyala, ESP32 akan membuat hotspot bernama **"WatchSMP-Config"**.
    *   Hubungkan HP Anda ke WiFi tersebut.
    *   Akan muncul popup otomatis (Captive Portal). Jika tidak, buka `192.168.4.1` di browser.
    *   Pilih WiFi tujuan, masukkan password, dan isi **IP Server/Laptop** Anda.
    *   Klik **Save**. ESP32 akan restart dan terhubung otomatis.

---

## 📖 Cara Penggunaan (Workflow)

1.  **Mendaftarkan Jari**:
    *   Di Dashboard, klik **"Rekam Jari Baru di Sensor"**.
    *   Ikuti instruksi di LCD alat (Tempel jari 2x).
2.  **Identifikasi Awal**:
    *   Tempelkan jari yang baru direkam (mode scan normal).
    *   Dashboard akan mendeteksi **"SIDIK JARI BARU"** di menu Discovery.
3.  **Input Data Siswa**:
    *   Buka menu **"Sidik Jari Baru"** di sidebar web.
    *   Klik **"Daftarkan Sekarang"**, isi Nama, NIS, dan Kelas.
4.  **Selesai**: Setiap kali siswa absen, namanya akan muncul di Dashboard dan tersimpan di Log dengan status yang sesuai.

---

## 🧪 Testing & Maintenance

*   **Clear Logs**: Gunakan tombol "Hapus Log Hari Ini" di menu Log Kehadiran untuk mereset data absen harian saat testing.
*   **Troubleshooting**: Jika status "Disconnected", pastikan IP Server yang diinput saat konfigurasi WiFi sudah benar dan laptop tidak memblokir koneksi (Firewall).

---
*Dikembangkan untuk Monitoring Siswa Khusus - WatchSMP System.*
