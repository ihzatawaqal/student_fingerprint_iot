CREATE DATABASE IF NOT EXISTS db_fingerprint_smp;
USE db_fingerprint_smp;

CREATE TABLE IF NOT EXISTS siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(20) UNIQUE NOT NULL,
    nama VARCHAR(100) NOT NULL,
    kelas VARCHAR(20),
    fp_id INT UNIQUE NOT NULL,
    keterangan_masalah TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS absensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipe ENUM('Masuk', 'Keluar') DEFAULT 'Masuk',
    status VARCHAR(50) DEFAULT 'Tepat Waktu',
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
);

-- Tabel baru untuk konfigurasi jam harian
CREATE TABLE IF NOT EXISTS konfigurasi_jam (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE UNIQUE,
    jam_masuk TIME NOT NULL,
    jam_pulang TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default config (berlaku jika tidak ada config di tanggal tertentu)
-- Kita buat satu entri default dengan tanggal jauh di masa lalu
INSERT IGNORE INTO konfigurasi_jam (tanggal, jam_masuk, jam_pulang) 
VALUES ('2000-01-01', '07:15:00', '14:00:00');

-- Tabel baru untuk menampung scan yang tidak dikenal agar bisa di-registrasi
CREATE TABLE IF NOT EXISTS scan_tidak_dikenal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fp_id INT NOT NULL,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
