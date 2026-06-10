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
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
);

-- Tabel baru untuk menampung scan yang tidak dikenal agar bisa di-registrasi
CREATE TABLE IF NOT EXISTS scan_tidak_dikenal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fp_id INT NOT NULL,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
