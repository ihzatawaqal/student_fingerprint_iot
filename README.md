# 🛡️ WatchSMP: Sistem Monitoring Fingerprint Siswa Khusus

Sistem monitoring real-time berbasis Web dan ESP32 untuk memantau kehadiran siswa tertentu (siswa pantauan) menggunakan sensor sidik jari.

## 📋 Fitur Utama
- **Discovery-Based Registration**: Daftar siswa baru langsung dari deteksi sensor (tidak perlu tebak ID).
- **Real-time Dashboard**: Status kehadiran muncul dalam < 2 detik tanpa refresh.
- **MySQL Integration**: Data aman tersimpan di database Laragon (bukan di browser).
- **Log Kehadiran**: Rekap otomatis waktu masuk siswa.
- **Modern UI**: Tampilan Dark Mode yang bersih dan responsif.

---

## 🛠️ Persiapan Hardware
1. **Komponen**:
   - ESP32 DevKit V1
   - Sensor Fingerprint (AS608 / R307 / sejenis)
   - LCD I2C 16x2
   - Kabel Jumper
2. **Wiring (Skema Pin)**:
   - **Sensor Fingerprint**:
     - VCC -> 3.3V ESP32
     - GND -> GND ESP32
     - TX  -> GPIO 16 (RX2)
     - RX  -> GPIO 17 (TX2)
   - **LCD I2C**:
     - VCC -> 5V (VIN) ESP32
     - GND -> GND ESP32
     - SDA -> GPIO 21
     - SCL -> GPIO 22

---

## 💻 Persiapan Software & Database

### 1. Setup Database (Laragon)
1. Jalankan **Laragon**, klik **Start All**.
2. Klik tombol **Terminal** di Laragon, lalu ketik perintah ini:
   ```bash
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS db_fingerprint_smp;"
   ```
3. Eksekusi file `database.sql` yang sudah ada di folder `fp` melalui Database Manager (HeidiSQL) atau Terminal:
   ```bash
   mysql -u root db_fingerprint_smp < D:\laragon\www\fp\database.sql
   ```

### 2. Setup Web Server
1. Pastikan folder `fp` berada di: `C:\laragon\www\fp\`
2. Pastikan file-file berikut ada di dalamnya:
   - `absensi.html` (Frontend utama)
   - `api.php` (Penghubung database)
   - `database.sql` (Skema DB)
   - `emulator.php` (Hanya untuk testing tanpa alat)

---

## 📡 Integrasi ke Alat (ESP32)

### 1. Flash Kodingan Arduino
1. Buka file `absensi.ino` di folder `absensi/` menggunakan **Arduino IDE**.
2. Instal Library yang dibutuhkan (Sketch -> Include Library -> Manage Libraries):
   - `Adafruit Fingerprint Sensor Library`
   - `LiquidCrystal I2C`
3. Ubah bagian WiFi di kodingan:
   ```cpp
   const char* ssid     = "NAMA_WIFI_ANDA";
   const char* password = "PASSWORD_WIFI_ANDA";
   ```
4. Upload kodingan ke ESP32.
5. Buka **Serial Monitor** (Baudrate 115200), catat **IP Address** yang muncul (misal: `192.168.1.15`).

### 2. Hubungkan Website ke ESP32
1. Buka file `C:\laragon\www\fp\absensi.html`.
2. Cari bagian `const ESP32_IP` (sekitar baris 295).
3. Ubah dari emulator ke IP asli ESP32:
   ```javascript
   // SEBELUMNYA (Emulator):
   // const ESP32_IP = "http://localhost/fp/emulator.php"; 

   // UBAH JADI (Alat Asli):
   const ESP32_IP = "http://192.168.1.15"; // Ganti dengan IP dari Serial Monitor
   ```
4. Simpan file.

### 3. Query Database (Jika Perlu Buat Ulang)
Jika Anda ingin mereset atau membuat ulang database secara manual, gunakan query berikut:
```sql
-- Hapus jika sudah ada (PERINGATAN: Menghapus semua data!)
DROP DATABASE IF EXISTS db_fingerprint_smp;
CREATE DATABASE db_fingerprint_smp;
USE db_fingerprint_smp;

-- Tabel Siswa
CREATE TABLE siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(20) UNIQUE NOT NULL,
    nama VARCHAR(100) NOT NULL,
    kelas VARCHAR(20),
    fp_id INT UNIQUE NOT NULL,
    keterangan_masalah TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Log Absensi
CREATE TABLE absensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipe ENUM('Masuk', 'Keluar') DEFAULT 'Masuk',
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
);

-- Tabel Penampung ID Baru (Belum Terdaftar)
CREATE TABLE scan_tidak_dikenal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fp_id INT NOT NULL,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🚀 Alur Penggunaan (Workflow)

1. **Rekam Jari Baru**:
   - Di Dashboard, klik tombol **✨ Rekam Jari Baru di Sensor**.
   - Masukkan nomor ID (1-127) yang diinginkan.
   - Ikuti instruksi di LCD alat (Tempel jari 2x).
2. **Identifikasi ID**:
   - Setelah berhasil terekam di alat, tempelkan jari tersebut sekali lagi (mode absen normal).
   - Website akan mendeteksi: "SIDIK JARI BARU!".
3. **Pemberian Nama**:
   - Buka menu **✨ Sidik Jari Baru** di sidebar web.
   - Klik **Daftarkan Sekarang** pada ID yang muncul.
   - Masukkan Nama, NIS, dan Kelas siswa tersebut.
4. **Monitoring Selesai**:
   - Sekarang, setiap kali siswa tersebut menempelkan jari, namanya akan langsung muncul di Dashboard dan tersimpan di menu Log Kehadiran.

---

## ❓ Troubleshooting
- **Status Offline di Web**: Pastikan Laptop dan ESP32 berada di WiFi yang sama.
- **Database Error**: Cek file `api.php`, pastikan user `root` dan password sesuai dengan settingan Laragon Anda (default: password kosong).
- **Sensor Tidak Respon**: Periksa kabel TX/RX, pastikan tidak tertukar.

---
 dikembangkan untuk SMP - Monitoring Siswa Khusus.
