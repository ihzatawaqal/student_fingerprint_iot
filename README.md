# 🛡️ WatchSMP: Sistem Monitoring Fingerprint Siswa Khusus

Sistem monitoring kehadiran siswa berbasis IoT menggunakan ESP32, sensor sidik jari AS608, dan Dashboard Web real-time. Project ini dirancang untuk memantau siswa tertentu (siswa pantauan) dengan notifikasi instan di dashboard tanpa refresh.

---

## 🚀 Fitur Utama

1.  **Push-to-Server Architecture**: ESP32 mengirim data langsung ke database. Data aman meskipun alat restart.
2.  **Discovery-Based Registration**: Daftar sidik jari baru langsung dari dashboard. Tidak perlu input ID manual di kode.
3.  **Real-time Monitoring**: Dashboard otomatis mendeteksi kehadiran dalam < 3 detik menggunakan polling API.
4.  **Remote Command**: Masuk ke mode "Enroll" (rekam jari) atau "Delete" sensor langsung dari web.
5.  **LCD Interaktif**: Menampilkan nama dan kelas siswa dengan efek teks berjalan (*scrolling*).
6.  **Log & Export**: Rekap kehadiran lengkap dengan filter tanggal, pencarian, dan fitur ekspor ke Excel/CSV.
7.  **Integrated Emulator**: Bisa diuji coba tanpa alat fisik menggunakan file `emulator.php`.

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
1.  Buka terminal database Anda (HeidiSQL/phpMyAdmin).
2.  Buat database baru bernama: `db_fingerprint_smp`.
3.  Import file `database.sql` yang ada di folder root project ini.

### 2. Konfigurasi Backend (`api.php`)
1.  Pastikan project berada di folder server web (misal: `C:\laragon\www\fp\`).
2.  Buka `api.php`, sesuaikan konfigurasi database jika Anda menggunakan password:
    ```php
    $host = "127.0.0.1";
    $user = "root";
    $pass = ""; // Isi jika ada
    $db   = "db_fingerprint_smp";
    ```

### 3. Konfigurasi Frontend (`absensi.html`)
Buka `absensi.html`, cari bagian `// CONFIG JARINGAN`:
```javascript
const ESP32_IP  = "http://192.168.x.x"; // Isi IP ESP32 (lihat di Serial Monitor)
const SERVER_URL = "http://localhost/fp"; // URL folder project Anda
```

---

## 📡 Flash ESP32 (Arduino IDE)

1.  Buka file `absensi/absensi.ino` di Arduino IDE.
2.  Install Library:
    *   `Adafruit Fingerprint Sensor Library`
    *   `LiquidCrystal I2C`
    *   `ArduinoJson`
3.  Konfigurasi WiFi di dalam kode:
    ```cpp
    const char* ssid     = "NAMA_WIFI";
    const char* password = "PASS_WIFI";
    const char* serverIP = "192.168.x.x"; // IP Laptop Anda (Cek via ipconfig)
    ```
4.  Upload ke ESP32 dan buka **Serial Monitor** (Baudrate 115200) untuk melihat IP Address alat.

---

## 📖 Cara Penggunaan (Workflow)

1.  **Mendaftarkan Jari**:
    *   Di Dashboard, klik **"Rekam Jari Baru di Sensor"**.
    *   Ikuti instruksi di LCD alat (Tempel jari 2x).
2.  **Identifikasi Awal**:
    *   Tempelkan jari yang baru direkam (mode scan normal).
    *   Dashboard akan mendeteksi **"SIDIK JARI BARU"**.
3.  **Input Data Siswa**:
    *   Buka menu **"Sidik Jari Baru"** di sidebar web.
    *   Klik **"Daftarkan Sekarang"**, isi Nama, NIS, dan Kelas.
4.  **Selesai**: Setiap kali siswa tersebut absen, namanya akan muncul di Dashboard dan tersimpan di Log.

---

## 🧪 Testing Tanpa Alat (Emulator)

Jika Anda tidak memiliki hardware, Anda bisa menggunakan emulator:
1.  Di `absensi.html`, ganti `ESP32_IP` menjadi: `http://localhost/fp/emulator.php`.
2.  Buka `emulator.php?send_id=99` di browser untuk mensimulasikan scan jari ID 99.
3.  Dashboard akan merespon seolah-olah ada hardware yang mengirim data.

---

## ⚠️ Troubleshooting

*   **Status Disconnected**: Cek apakah Laptop dan ESP32 berada di WiFi yang sama. Matikan Firewall Windows jika perlu.
*   **Sensor Error**: Pastikan kabel TX dan RX sensor fingerprint tidak tertukar.
*   **Database Gagal**: Pastikan nama database sama persis dengan yang ada di `api.php`.

---
*Dikembangkan untuk Monitoring Siswa Khusus - WatchSMP System.*
