#include <Adafruit_Fingerprint.h>
#include <LiquidCrystal_I2C.h>
#include <Wire.h>
#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <ArduinoJson.h> 

// ================= CONFIG JARINGAN =================
const char* ssid          = "NAMA_WIFI_ANDA";      
const char* password      = "PASSWORD_WIFI_ANDA";   
const char* serverIP      = "192.168.1.10"; // Ganti dengan IP Laptop/Server Anda
// ===================================================

String getApiUrl() {
  return "http://" + String(serverIP) + "/fp/api.php?action=add_absen";
}

WebServer server(80);

// ================= FINGERPRINT =================
#define FINGERPRINT_RX 16
#define FINGERPRINT_TX 17

HardwareSerial mySerial(2);
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&mySerial);
LiquidCrystal_I2C lcd(0x27, 16, 2);

// ================= GLOBAL =================
String  webStatus   = "Sistem siap";
bool    isEnrolling = false;
uint8_t targetID    = 0;

// ================= LCD + SERIAL DEBUG =================
void tampil(String baris1, String baris2 = "") {
  lcd.clear();
  lcd.setCursor(0, 0); lcd.print(baris1);
  lcd.setCursor(0, 1); lcd.print(baris2);
  
  // Debug to Serial
  Serial.println("\n[LCD] Line 1: " + baris1);
  if(baris2 != "") Serial.println("[LCD] Line 2: " + baris2);
  Serial.println("-------------------------");
}

// ================= LCD SCROLLING =================
void scrollText(String text, int row, int delayTime = 300) {
  if (text.length() <= 16) {
    lcd.setCursor(0, row);
    lcd.print(text);
    for (int i = text.length(); i < 16; i++) lcd.print(" ");
  } else {
    String displayStr = text + "   "; // Beri jarak
    for (int i = 0; i < displayStr.length(); i++) {
      lcd.setCursor(0, row);
      String part = displayStr.substring(i, i + 16);
      if (part.length() < 16) {
        part += displayStr.substring(0, 16 - part.length());
      }
      lcd.print(part);
      delay(delayTime);
      server.handleClient(); // Tetap handle web server saat scroll
    }
  }
}

void debug(String msg) {
  Serial.print("[DEBUG] ");
  Serial.println(msg);
}

// ================= PUSH DATA TO SERVER =================
void pushData(int fpID) {
  if (WiFi.status() == WL_CONNECTED) {
    debug("Pushing ID " + String(fpID) + " to server...");
    
    HTTPClient http;
    http.begin(getApiUrl());
    http.addHeader("Content-Type", "application/json");
    
    String httpRequestData = "{\"fp_id\":" + String(fpID) + "}";
    int httpCode = http.POST(httpRequestData);
    
    if (httpCode == 200) {
      String payload = http.getString();
      StaticJsonDocument<256> doc;
      deserializeJson(doc, payload);
      
      if (doc["status"] == "terdaftar") {
        String nama = doc["nama"].as<String>();
        String kelas = doc["kelas"].as<String>();
        
        tampil("ABSEN BERHASIL", "");
        scrollText(nama + " (" + kelas + ")", 1);
      } else {
        tampil("ID: " + String(fpID), "BELUM TERDAFTAR");
      }
    } else {
      debug("HTTP Error: " + String(httpCode));
      tampil("Error Server", "Code: " + String(httpCode));
    }
    http.end();
  } else {
    debug("WiFi Disconnected, cannot push data");
  }
}

// ================= CORS =================
void setCORS() {
  server.sendHeader("Access-Control-Allow-Origin",  "*");
  server.sendHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
  server.sendHeader("Access-Control-Allow-Headers", "*");
}

// ================= HANDLERS =================

void handleStatus() {
  setCORS();
  debug("Request: GET /status");
  server.send(200, "application/json", "{\"status\":\"" + webStatus + "\"}");
}

void handleEnroll() {
  setCORS();
  if (server.hasArg("id")) {
    targetID    = server.arg("id").toInt();
    isEnrolling = true;
    webStatus   = "Mulai daftar ID " + String(targetID);
    debug("Request: GET /enroll -> Target ID: " + String(targetID));
    tampil("Enroll Mode", "Target ID: " + String(targetID));
    server.send(200, "application/json", "{\"msg\":\"ok\"}");
  } else {
    debug("Request: GET /enroll -> ERROR: ID Missing");
    server.send(400, "application/json", "{\"msg\":\"id tidak ada\"}");
  }
}

void handleDelete() {
  setCORS();
  if (server.hasArg("id")) {
    uint8_t delID = server.arg("id").toInt();
    Serial.print("[DELETE] Request to delete ID #"); Serial.println(delID);
    
    uint8_t p = finger.deleteModel(delID);
    
    if (p == FINGERPRINT_OK) {
      Serial.println("[DELETE] Success! Model deleted from sensor memory.");
      webStatus = "ID " + String(delID) + " dihapus";
      tampil("Hapus OK", "ID: " + String(delID));
      server.send(200, "application/json", "{\"msg\":\"ok\"}");
    } else {
      String errMsg = "Error Sensor: ";
      if (p == FINGERPRINT_PACKETRECIEVEERR) errMsg += "Komunikasi Error";
      else if (p == FINGERPRINT_BADLOCATION) errMsg += "ID Tidak Ditemukan";
      else if (p == FINGERPRINT_FLASHERR) errMsg += "Error Flash Memori";
      else errMsg += "Kode: " + String(p);

      Serial.print("[DELETE] FAILED: "); Serial.println(errMsg);
      webStatus = "Gagal Hapus ID " + String(delID);
      tampil("Gagal Hapus", "ID: " + String(delID));
      server.send(500, "application/json", "{\"msg\":\"error\", \"info\":\"" + errMsg + "\"}");
    }
  } else {
    server.send(400, "application/json", "{\"msg\":\"id tidak ada\"}");
  }
}

void handleOptions() {
  setCORS();
  server.send(200);
}

// ================= SETUP =================
void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n\n==============================");
  Serial.println("   WATCH SMP SYSTEM STARTING  ");
  Serial.println("==============================");

  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  tampil("Mencari WiFi...");

  debug("Menghubungkan ke: " + String(ssid));
  WiFi.begin(ssid, password);
  int retry = 0;
  while (WiFi.status() != WL_CONNECTED && retry < 20) {
    delay(500);
    Serial.print(".");
    retry++;
  }

  if(WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WiFi] Terhubung!");
    Serial.print("[WiFi] IP Address: ");
    Serial.println(WiFi.localIP());
    tampil("WiFi OK", WiFi.localIP().toString());
  } else {
    Serial.println("\n[WiFi] GAGAL TERHUBUNG!");
    tampil("WiFi Error", "Cek SSID/Pass");
  }
  delay(2000);

  // Routes
  server.on("/status",  HTTP_GET,     handleStatus);
  server.on("/enroll",  HTTP_GET,     handleEnroll);
  server.on("/delete",  HTTP_GET,     handleDelete);
  server.on("/status",  HTTP_OPTIONS, handleOptions);
  server.on("/enroll",  HTTP_OPTIONS, handleOptions);
  server.on("/delete",  HTTP_OPTIONS, handleOptions);
  server.begin();
  debug("HTTP Server Dimulai di Port 80");

  // Fingerprint sensor
  debug("Inisialisasi Sensor Fingerprint...");
  mySerial.begin(57600, SERIAL_8N1, FINGERPRINT_RX, FINGERPRINT_TX);
  finger.begin(57600);
  if (finger.verifyPassword()) {
    debug("Sensor Fingerprint Terdeteksi!");
  } else {
    Serial.println("[ERROR] SENSOR TIDAK DITEMUKAN!");
    tampil("Sensor Error", "Cek Kabel");
    while (1);
  }

  tampil("Sistem Siap", "Silakan Scan");
  debug("Loop Utama Dimulai...");
}

// ================= LOOP =================
void loop() {
  server.handleClient();

  // Mode enroll
  if (isEnrolling) {
    debug("Menjalankan Proses Enroll untuk ID: " + String(targetID));
    prosesEnroll(targetID);
    isEnrolling = false;
    tampil("Sistem Siap", "Silakan Scan");
    webStatus   = "Sistem siap";
    return;
  }

  // Mode scan normal (Non-blocking check)
  uint8_t p = finger.getImage();
  if (p == FINGERPRINT_OK) {
    debug("Jari terdeteksi di sensor, memproses...");
    p = finger.image2Tz();
    if (p == FINGERPRINT_OK) {
      p = finger.fingerSearch();
      if (p == FINGERPRINT_OK) {
        // Dikenali
        Serial.print("[MATCH] Found ID #"); Serial.print(finger.fingerID); 
        Serial.print(" with confidence "); Serial.println(finger.confidence);

        webStatus = "Dikenali ID: " + String(finger.fingerID);
        tampil("Dikenali", "ID: " + String(finger.fingerID));
        
        // PUSH DATA LANGSUNG KE SERVER
        pushData(finger.fingerID);
        
      } else if (p == FINGERPRINT_NOTFOUND) {
        // Tidak dikenal (ID 0 atau ID sensor lainnya)
        Serial.println("[MATCH] Fingerprint NOT FOUND in sensor database");
        webStatus = "Tidak dikenal";
        tampil("Tidak Dikenal", "ID: Baru");
        
        // PUSH DATA UNKNOWN KE SERVER (Biasanya dikirim ID 0 atau ID yang discan)
        pushData(finger.fingerID == 0 ? 0 : finger.fingerID);
      } else {
        Serial.print("[MATCH] Error search: "); Serial.println(p);
      }
      
      // Tunggu jari dilepas sebentar agar tidak double scan
      delay(1000); 
      tampil("Sistem Siap", "Silakan Scan");
      webStatus = "Sistem siap";
    }
  }

  delay(50); // Delay kecil untuk stabilitas CPU
}

// ================= ENROLL =================
void prosesEnroll(uint8_t id) {
  int p = -1;

  debug("ENROLL: Tahap 1 (Ambil gambar pertama)");
  tampil("Tempel Jari", "ID: " + String(id));
  while (p != FINGERPRINT_OK) {
    p = finger.getImage();
    server.handleClient();
  }
  finger.image2Tz(1);

  debug("ENROLL: Tahap 2 (Lepas jari)");
  tampil("Angkat Jari", "");
  delay(1500);
  while (finger.getImage() != FINGERPRINT_NOFINGER) {
    server.handleClient();
  }

  debug("ENROLL: Tahap 3 (Ambil gambar kedua)");
  tampil("Tempel Lagi", "ID: " + String(id));
  p = -1;
  while (p != FINGERPRINT_OK) {
    p = finger.getImage();
    server.handleClient();
  }
  finger.image2Tz(2);

  debug("ENROLL: Tahap 4 (Membuat model & menyimpan)");
  if (finger.createModel() == FINGERPRINT_OK) {
    if (finger.storeModel(id) == FINGERPRINT_OK) {
      debug("ENROLL: BERHASIL! Tersimpan di ID " + String(id));
      webStatus = "Berhasil daftar ID " + String(id);
      tampil("Berhasil!", "ID: " + String(id));
    } else {
      debug("ENROLL: GAGAL menyimpan ke memori");
      tampil("Gagal Simpan", "ID: " + String(id));
    }
  } else {
    debug("ENROLL: GAGAL membuat model (sidik jari tidak cocok)");
    tampil("Gagal Daftar", "Coba Lagi");
  }

  delay(2000);
}
