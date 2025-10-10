#include <Arduino.h>
#include <SPI.h>
#include <MFRC522.h>
#include <SD.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "mbedtls/md.h"
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <RTClib.h>
#include <vector>
#include <sys/time.h>

#include "config.h"

// RFID
MFRC522 mfrc522(RFID_SS_PIN, RFID_RST_PIN);
RTC_DS3231 rtc;

Adafruit_SSD1306 display(OLED_WIDTH, OLED_HEIGHT, &Wire, -1);
bool rtcAvailable = false;
bool oledAvailable = false;

// Paths on SD (disabled for direct send)
#define QUEUE_FILE "/events_queue.jsonl"
#define LOG_FILE "/attendance_log.csv"
// Direct send mode mengikuti konfigurasi USE_SD
#if USE_SD
#define DIRECT_SEND false
#else
#define DIRECT_SEND true
#endif

// State
String lastUidHex = "";
unsigned long lastScanMs = 0;
unsigned long lastFlushMs = 0;
const unsigned long FLUSH_EVERY_MS = 5000;
unsigned long lastConfigFetchMs = 0;
const unsigned long CONFIG_FETCH_EVERY_MS = 30000;
bool gRegModeServer = false;
unsigned long lastDisplayMs = 0;
const unsigned long DISPLAY_REFRESH_MS = 3000;

// Forward declarations
void connectWiFi();
String generateNonce();
String hmacSha256Hex(const String &key, const String &message);
String eventsArrayFromQueue(int maxItems, String &leftoverOut, int &countOut);
bool removeProcessedFromQueue(const String &leftover);
bool postEventBatch(const String &eventsJson, int count);
String currentTimestampISO8601();
String currentDate();
String uidToHex(const MFRC522::Uid &uid);
void beepSuccess();
void beepError();
void logToSd(const String &ts, const String &uidHex, const String &deviceId);
void enqueueEvent(const String &uidHex, const String &ts, const String &eventType);
void flushQueueIfOnline();
void initI2CDevices();
void showIdle();
void showScan(const String &uidHex, const String &name, const String &status);
bool loadStudentsFromSd();
bool lookupNameByUid(const String &uidHex, String &outName);
bool fetchServerNameAndStatus(const String &uidHex, String &outName, String &statusMasuk, String &statusPulang);
// Flag status hari ini dari server
static bool gHasFirstToday = false;
static bool gHasLastToday = false;
bool registrationModeActive();
void updateServerConfigIfDue();
bool getAttendanceStatus(const String &uidHex, String &statusMasuk, String &statusPulang);

struct Student { String uid; String name; String room; };
std::vector<Student> students;

static void debugLog(const String &msg) {
#if DEBUG
  Serial.println(msg);
#endif
}

void setup() {
#if DEBUG
  Serial.begin(115200);
  delay(200);
  Serial.println("[BOOT] Attendance firmware starting...");
#endif

  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);
  pinMode(LED_GREEN_PIN, OUTPUT);
  pinMode(LED_RED_PIN, OUTPUT);
  digitalWrite(LED_GREEN_PIN, LOW);
  digitalWrite(LED_RED_PIN, LOW);
  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);

  // SPI bus shared by RC522 + SD
  SPI.begin(SPI_SCK_PIN, SPI_MISO_PIN, SPI_MOSI_PIN);

  // SD card (opsional)
  if (USE_SD) {
    if (!SD.begin(SD_CS_PIN, SPI)) {
      debugLog("[SD] Failed to mount SD card!");
    } else {
      debugLog("[SD] SD mounted.");
      if (!SD.exists(LOG_FILE)) {
        File lf = SD.open(LOG_FILE, FILE_WRITE);
        if (lf) {
          lf.println("timestamp_iso8601,uid_hex,device_id");
          lf.close();
        }
      }
    }
  }

  // RFID
  mfrc522.PCD_Init(); // Uses pins from constructor
  delay(50);
  debugLog("[RFID] RC522 initialized.");

  // I2C devices (OLED + RTC)
  Wire.begin(I2C_SDA_PIN, I2C_SCL_PIN);
  initI2CDevices();

  // WiFi
  WiFi.mode(WIFI_STA);
  // Matikan power-save agar respon HTTP lebih stabil/cepat
  WiFi.setSleep(false);
  connectWiFi();
  updateServerConfigIfDue();

  // Timezone Asia/Jakarta (UTC+7)
  setenv("TZ", "WIB-7", 1); // POSIX TZ: -7 means +07:00
  tzset();

  // Try SNTP if WiFi is up
  if (WiFi.status() == WL_CONNECTED) {
    configTime(7 * 3600, 0, "pool.ntp.org", "time.google.com");
    debugLog("[TIME] Sync NTP...");
    time_t now = 0;
    int tries = 0;
    while (now < 100000 && tries < 20) { // wait up to ~20s
      delay(1000);
      time(&now);
      tries++;
    }
    if (now >= 100000) {
      debugLog("[TIME] Time synced.");
      if (rtcAvailable) {
        struct tm info; localtime_r(&now, &info);
        rtc.adjust(DateTime(info.tm_year + 1900, info.tm_mon + 1, info.tm_mday, info.tm_hour, info.tm_min, info.tm_sec));
      }
    } else {
      debugLog("[TIME] NTP sync failed (will continue).\n");
    }
  }

  // Startup indicator
  digitalWrite(LED_PIN, HIGH);
  delay(100);
  digitalWrite(LED_PIN, LOW);

  loadStudentsFromSd();
  showIdle();
}

void loop() {
  // Periodic queue flush
  if (millis() - lastFlushMs > FLUSH_EVERY_MS) {
    flushQueueIfOnline();
    lastFlushMs = millis();
  }
  updateServerConfigIfDue();

  // Refresh display clock/status
  if (millis() - lastDisplayMs > DISPLAY_REFRESH_MS) {
    showIdle();
    lastDisplayMs = millis();
  }

  // RFID read
  if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
    delay(10);
    return;
  }

  String uidHex = uidToHex(mfrc522.uid);
  unsigned long nowMs = millis();
  if (uidHex == lastUidHex && (nowMs - lastScanMs) < SCAN_DEBOUNCE_MS) {
    debugLog("[RFID] Debounced duplicate scan: " + uidHex);
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    return;
  }
  lastUidHex = uidHex;
  lastScanMs = nowMs;

  String ts = currentTimestampISO8601();
  debugLog("[RFID] UID=" + uidHex + " TS=" + ts);

  if (USE_SD) logToSd(ts, uidHex, DEVICE_ID);

  // Hindari GET sebelum kirim untuk mengurangi latensi
  String name;
  bool hasName = lookupNameByUid(uidHex, name);

  // BYPASS: Mode Registrasi -> jangan kirim event dan jangan cek status
  if (registrationModeActive()) {
    beepSuccess();
    showScan(uidHex, hasName ? name : String("(Belum terdaftar)"), String("REGISTRASI"));
    delay(DISPLAY_HOLD_MS);
    lastDisplayMs = millis();
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    return;
  }

  // Tentukan jenis event berdasarkan cache flag lokal (gHasFirstToday/gHasLastToday)
  bool isCheckIn = true;
  if (!gHasFirstToday) {
    isCheckIn = true; // scan pertama hari ini
  } else if (!gHasLastToday) {
    isCheckIn = false; // sudah check-in tapi belum check-out
  } else {
    // sudah complete hari ini
    beepError();
    showScan(uidHex, hasName ? name : String("(Sudah Selesai)"), String("TOLAK"));
    delay(DISPLAY_HOLD_MS);
    lastDisplayMs = millis();
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    // Tunggu kartu diangkat agar tidak double
    unsigned long t0 = millis();
    while (millis() - t0 < 2000) {
      if (!mfrc522.PICC_IsNewCardPresent()) break;
      delay(20);
    }
    return;
  }

  bool httpSuccess = false;
#ifdef DIRECT_SEND
  // Send directly without SD queue
  if (WiFi.status() == WL_CONNECTED) {
    String eventType = isCheckIn ? "checkin" : "checkout";
    String eventsJson = "[" + String("{\"uid\":\"") + uidHex + "\",\"ts\":\"" + ts + "\",\"type\":\"" + eventType + "\"}" + "]";
    httpSuccess = postEventBatch(eventsJson, 1);
    if (httpSuccess) {
      debugLog("[HTTP] Direct send success for " + eventType);
    } else {
      debugLog("[HTTP] Direct send failed for " + eventType);
    }
  }
#else
  // For queue, need to update enqueueEvent to include type
  enqueueEvent(uidHex, ts, isCheckIn ? "checkin" : "checkout");
#endif

  bool regMode = registrationModeActive();
  if (hasName || regMode || httpSuccess) {
    beepSuccess();
    String statusMsg;
    if (httpSuccess) {
      // Ambil status terbaru setelah kirim agar konsisten dengan dashboard dan update flag lokal
      String newMasuk, newPulang;
      if (getAttendanceStatus(uidHex, newMasuk, newPulang)) {
        if (isCheckIn) {
          statusMsg = String("Check-in: ") + newMasuk;
        } else {
          statusMsg = String("Check-out: ") + (newPulang.length() ? newPulang : String("Belum Pulang"));
        }
        // Update flag kasar: jika ada newMasuk -> first today, jika ada newPulang -> last today
        gHasFirstToday = newMasuk.length() > 0;
        gHasLastToday = newPulang.length() > 0;
      } else {
        statusMsg = (WiFi.status()==WL_CONNECTED?"Terekam":"Tersimpan offline");
      }
    } else {
      statusMsg = (WiFi.status()==WL_CONNECTED?"Terekam":"Tersimpan offline");
    }
    showScan(uidHex, hasName ? name : String("(Tidak terdaftar)"), statusMsg);
    delay(DISPLAY_HOLD_MS); // Add delay to keep notification visible longer
    lastDisplayMs = millis(); // Reset display timer to prevent immediate idle refresh
  } else {
    // Unknown and not in registration mode: give different feedback
    beepError();
    showScan(uidHex, String("(Tidak terdaftar)"), String("TOLAK"));
    delay(DISPLAY_HOLD_MS); // Add delay to keep notification visible longer
    lastDisplayMs = millis(); // Reset display timer to prevent immediate idle refresh
  }

#ifndef DIRECT_SEND
  flushQueueIfOnline();
#endif

  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  // Wajib angkat kartu: tunggu hingga kartu tidak terdeteksi untuk mencegah double scan saat menunggu respon
  unsigned long t0 = millis();
  while (millis() - t0 < 2000) { // max 2 detik
    if (!mfrc522.PICC_IsNewCardPresent()) break;
    delay(20);
  }
}

void connectWiFi() {
  if (String(WIFI_SSID).length() == 0 || String(WIFI_SSID) == "YOUR_SSID") {
    debugLog("[WiFi] WIFI_SSID not set; running offline.");
    return;
  }
  if (WiFi.status() == WL_CONNECTED) return;
  debugLog("[WiFi] Connecting to " + String(WIFI_SSID));
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && (millis() - start) < 15000) {
    delay(500);
    debugLog(".");
  }
  if (WiFi.status() == WL_CONNECTED) {
    debugLog("\n[WiFi] Connected. IP=" + WiFi.localIP().toString());
  } else {
    debugLog("\n[WiFi] Failed to connect (offline mode).\n");
  }
}

String currentTimestampISO8601() {
  time_t now;
  if (rtcAvailable) {
    DateTime dt = rtc.now();
    struct tm tminfo = {0};
    tminfo.tm_year = dt.year() - 1900;
    tminfo.tm_mon  = dt.month() - 1;
    tminfo.tm_mday = dt.day();
    tminfo.tm_hour = dt.hour();
    tminfo.tm_min = dt.minute();
    tminfo.tm_sec = dt.second();
    now = mktime(&tminfo);
  } else {
    time(&now);
  }
  struct tm info;
  localtime_r(&now, &info);
  char buf[40];
  // %z is +0700, then insert a colon to become +07:00
  strftime(buf, sizeof(buf), "%Y-%m-%dT%H:%M:%S%z", &info);
  String s(buf);
  if (s.length() >= 5) {
    s = s.substring(0, s.length() - 2) + ":" + s.substring(s.length() - 2);
  }
  return s;
}

String currentDate() {
  time_t now;
  if (rtcAvailable) {
    DateTime dt = rtc.now();
    struct tm tminfo = {0};
    tminfo.tm_year = dt.year() - 1900;
    tminfo.tm_mon  = dt.month() - 1;
    tminfo.tm_mday = dt.day();
    tminfo.tm_hour = dt.hour();
    tminfo.tm_min = dt.minute();
    tminfo.tm_sec = dt.second();
    now = mktime(&tminfo);
  } else {
    time(&now);
  }
  struct tm info;
  localtime_r(&now, &info);
  char buf[11];
  strftime(buf, sizeof(buf), "%Y-%m-%d", &info);
  return String(buf);
}

String uidToHex(const MFRC522::Uid &uid) {
  String out;
  for (byte i = 0; i < uid.size; i++) {
    if (uid.uidByte[i] < 0x10) out += "0";
    out += String(uid.uidByte[i], HEX);
  }
  out.toLowerCase();
  return out;
}

void beepSuccess() {
  digitalWrite(LED_PIN, HIGH);
  digitalWrite(LED_GREEN_PIN, HIGH);
  digitalWrite(BUZZER_PIN, HIGH);
  delay(80);
  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(LED_PIN, LOW);
  digitalWrite(LED_GREEN_PIN, LOW);
}

void beepError() {
  for (int i = 0; i < 2; i++) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(100);
    digitalWrite(BUZZER_PIN, LOW);
    delay(80);
  }
  digitalWrite(LED_RED_PIN, HIGH);
  delay(120);
  digitalWrite(LED_RED_PIN, LOW);
}

void logToSd(const String &ts, const String &uidHex, const String &deviceId) {
  if (!SD.begin(SD_CS_PIN, SPI)) return; // ensure mounted (no-op if already)
  File f = SD.open(LOG_FILE, FILE_WRITE);
  if (!f) {
    debugLog("[SD] Failed to open log file");
    return;
  }
  f.print(ts); f.print(","); f.print(uidHex); f.print(","); f.println(deviceId);
  f.close();
}

void enqueueEvent(const String &uidHex, const String &ts, const String &eventType) {
  if (!SD.begin(SD_CS_PIN, SPI)) return;
  File f = SD.open(QUEUE_FILE, FILE_WRITE);
  if (!f) {
    debugLog("[SD] Failed to open queue file");
    return;
  }
  StaticJsonDocument<128> doc;
  doc["uid"] = uidHex;
  doc["ts"] = ts;
  doc["type"] = eventType;
  String line;
  serializeJson(doc, line);
  f.println(line);
  f.close();
}

String generateNonce() {
  uint32_t a = esp_random();
  uint32_t b = esp_random();
  char buf[17];
  snprintf(buf, sizeof(buf), "%08x%08x", a, b);
  return String(buf);
}

String hmacSha256Hex(const String &key, const String &message) {
  const mbedtls_md_info_t *md_info = mbedtls_md_info_from_type(MBEDTLS_MD_SHA256);
  mbedtls_md_context_t ctx;
  mbedtls_md_init(&ctx);
  mbedtls_md_setup(&ctx, md_info, 1);
  mbedtls_md_hmac_starts(&ctx, (const unsigned char *)key.c_str(), key.length());
  mbedtls_md_hmac_update(&ctx, (const unsigned char *)message.c_str(), message.length());
  unsigned char hmac[32];
  mbedtls_md_hmac_finish(&ctx, hmac);
  mbedtls_md_free(&ctx);
  char out[65];
  for (int i = 0; i < 32; i++) sprintf(out + (i * 2), "%02x", hmac[i]);
  out[64] = 0;
  return String(out);
}

String eventsArrayFromQueue(int maxItems, String &leftoverOut, int &countOut) {
  countOut = 0;
  leftoverOut = "";
  if (!SD.begin(SD_CS_PIN, SPI)) return String("[]");
  if (!SD.exists(QUEUE_FILE)) return String("[]");

  File f = SD.open(QUEUE_FILE, FILE_READ);
  if (!f) return String("[]");

  String events = "[";
  bool first = true;
  while (f.available()) {
    String line = f.readStringUntil('\n');
    line.trim();
    if (line.length() == 0) continue;
    if (countOut < maxItems) {
      if (!first) events += ",";
      events += line; // each line is already a JSON object
      first = false;
      countOut++;
    } else {
      leftoverOut += line + "\n";
    }
  }
  f.close();
  events += "]";
  return events;
}

bool removeProcessedFromQueue(const String &leftover) {
  if (!SD.begin(SD_CS_PIN, SPI)) return false;
  if (SD.exists(QUEUE_FILE)) {
    SD.remove(QUEUE_FILE);
  }
  File f = SD.open(QUEUE_FILE, FILE_WRITE);
  if (!f) return false;
  if (leftover.length() > 0) {
    f.print(leftover);
  }
  f.close();
  return true;
}

bool postEventBatch(const String &eventsJson, int count) {
  if (count <= 0) return true;
  if (WiFi.status() != WL_CONNECTED) return false;

  String nonce = generateNonce();
  time_t now; time(&now);
  String tsHeader = String((uint32_t)now);

  // HMAC message: device_id|ts|nonce|<eventsJson>
  String message = String(DEVICE_ID) + "|" + tsHeader + "|" + nonce + "|" + eventsJson;
  String hmac = hmacSha256Hex(DEVICE_SECRET, message);

  // Build payload (embed events as raw JSON)
  DynamicJsonDocument doc(2048 + (count * 64));
  doc["device_id"] = DEVICE_ID;
  doc["nonce"] = nonce;
  doc["ts"] = tsHeader;
  doc["hmac"] = hmac;
  doc["events"] = serialized(eventsJson);
  String body;
  serializeJson(doc, body);

  String url = String(API_BASE) + "/ingest.php";
  HTTPClient http;
  http.begin(url);
  http.setTimeout(2500);
  http.addHeader("Content-Type", "application/json");
  int code = http.POST(body);
  if (code <= 0) {
    debugLog("[HTTP] POST failed: " + String(code));
    http.end();
    return false;
  }

  String resp = http.getString();
  http.end();

  DynamicJsonDocument rdoc(512);
  DeserializationError err = deserializeJson(rdoc, resp);
  if (err) {
    debugLog("[HTTP] Invalid JSON response");
    return false;
  }
  bool ok = rdoc["ok"].is<bool>() ? rdoc["ok"].as<bool>() : false;
  int saved = rdoc["saved"].is<int>() ? rdoc["saved"].as<int>() : 0;
  JsonArray errors = rdoc["errors"];
  if (ok && saved >= 0 && errors.size() == 0) {
    debugLog("[HTTP] Batch posted. saved=" + String(saved));
    return true;
  }
  debugLog("[HTTP] Server error or has errors: " + resp);
  return false;
}

void flushQueueIfOnline() {
  if (WiFi.status() != WL_CONNECTED) return;
  String leftover;
  int count = 0;
  String eventsJson = eventsArrayFromQueue(BATCH_SIZE, leftover, count);
  if (count <= 0) return;

  if (postEventBatch(eventsJson, count)) {
    if (removeProcessedFromQueue(leftover)) {
      beepSuccess();
      showScan("", "Synced", "Sent to server");
    }
  } else {
    beepError();
    showScan("", "Sync Failed", "Will retry");
  }
}

bool registrationModeActive() {
  // Check presence of flag file on microSD to enable registration-friendly feedback
  if (!SD.begin(SD_CS_PIN, SPI)) return false;
  if (SD.exists(REG_MODE_FLAG_FILE)) return true;
  return gRegModeServer;
}

void updateServerConfigIfDue() {
  if (WiFi.status() != WL_CONNECTED) return;
  if (millis() - lastConfigFetchMs < CONFIG_FETCH_EVERY_MS) return;
  lastConfigFetchMs = millis();
  String url = String(API_BASE) + "/device_config.php?device_id=" + DEVICE_ID;
  HTTPClient http;
  http.begin(url);
  http.setTimeout(2500);
  int code = http.GET();
  if (code == 200) {
    String resp = http.getString();
    DynamicJsonDocument doc(256);
    if (deserializeJson(doc, resp) == DeserializationError::Ok) {
      if (doc["ok"].is<bool>() && doc["ok"].as<bool>()) {
        gRegModeServer = doc["reg_mode"].is<bool>() ? doc["reg_mode"].as<bool>() : false;
      }
    }
  }
  http.end();
}

void initI2CDevices() {
  // OLED
  if (display.begin(SSD1306_SWITCHCAPVCC, OLED_ADDRESS)) {
    oledAvailable = true;
    display.clearDisplay();
    display.setTextSize(1);
    display.setTextColor(SSD1306_WHITE);
    display.setCursor(0, 0);
    display.println("Attendance v0.1");
    display.display();
  } else {
    debugLog("[OLED] Not found.");
  }
  // RTC
  if (rtc.begin()) {
    rtcAvailable = true;
    if (rtc.lostPower()) {
      debugLog("[RTC] Lost power, setting to compile time.");
      rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
    }
    // Set system time from RTC if possible
    DateTime dt = rtc.now();
    struct tm tminfo = {0};
    tminfo.tm_year = dt.year() - 1900;
    tminfo.tm_mon = dt.month() - 1;
    tminfo.tm_mday = dt.day();
    tminfo.tm_hour = dt.hour();
    tminfo.tm_min = dt.minute();
    tminfo.tm_sec = dt.second();
    time_t now = mktime(&tminfo);
    struct timeval tv = { .tv_sec = now, .tv_usec = 0 };
    settimeofday(&tv, nullptr);
  } else {
    debugLog("[RTC] Not found.");
  }
}

void showIdle() {
  if (!oledAvailable) return;
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(0, 0);
  // Time line
  time_t now; time(&now); struct tm info; localtime_r(&now, &info);
  char tbuf[20]; strftime(tbuf, sizeof(tbuf), "%H:%M:%S", &info);
  display.print(tbuf);
  display.print("  ");
  display.println(DEVICE_ID);
  // Date line (YYYY-MM-DD)
  char dbuf[20]; strftime(dbuf, sizeof(dbuf), "%Y-%m-%d", &info);
  display.setCursor(0, 12);
  display.print("Date: "); display.println(dbuf);
  // WiFi line
  display.setCursor(0, 24);
  display.print("WiFi: "); display.println(WiFi.status()==WL_CONNECTED?"ON":"OFF");
  // Hint
  display.setCursor(0, 38);
  display.print("Scan kartu...");
  display.display();
}

void showScan(const String &uidHex, const String &name, const String &status) {
  if (!oledAvailable) return;
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(0, 0);
  display.println(status);
  display.setCursor(0, 14);
  display.print("UID: "); display.println(uidHex);
  display.setCursor(0, 28);
  display.print("Nama: "); display.println(name);
  display.display();
}

bool loadStudentsFromSd() {
  students.clear();
  if (!SD.begin(SD_CS_PIN, SPI)) return false;
  if (!SD.exists(STUDENTS_CSV)) return false;
  File f = SD.open(STUDENTS_CSV, FILE_READ);
  if (!f) return false;
  while (f.available()) {
    String line = f.readStringUntil('\n');
    line.trim();
    if (line.length() == 0) continue;
    // Expect: uid_hex,name,room
    int p1 = line.indexOf(',');
    int p2 = line.indexOf(',', p1+1);
    if (p1 <= 0) continue;
    String uid = line.substring(0, p1); uid.toLowerCase(); uid.replace(" ", "");
    String name = (p2>p1)? line.substring(p1+1, p2) : line.substring(p1+1);
    String room = (p2>p1)? line.substring(p2+1) : String("");
    Student s{uid, name, room};
    students.push_back(s);
    if (students.size() >= 500) break; // cap
  }
  f.close();
  debugLog("[SD] Loaded students: " + String(students.size()));
  return students.size() > 0;
}

bool lookupNameByUid(const String &uidHex, String &outName) {
  for (auto &s : students) {
    if (s.uid == uidHex) { outName = s.name; return true; }
  }
  return false;
}

bool getAttendanceStatus(const String &uidHex, String &statusMasuk, String &statusPulang) {
  if (WiFi.status() != WL_CONNECTED) return false;
  String date = currentDate();
  String url = String(API_BASE) + "/recap_row.php?uid=" + uidHex + "&date=" + date;
  HTTPClient http;
  http.begin(url);
  int code = http.GET();
  if (code == 200) {
    String resp = http.getString();
    DynamicJsonDocument doc(512);
    if (deserializeJson(doc, resp) == DeserializationError::Ok && doc["ok"]) {
      statusMasuk = doc["row"]["masuk_status"] | "Tidak Hadir";
      statusPulang = doc["row"]["pulang_status"] | "Belum Pulang";
      return true;
    }
  }
  http.end();
  return false;
}

bool fetchServerNameAndStatus(const String &uidHex, String &outName, String &statusMasuk, String &statusPulang) {
  if (WiFi.status() != WL_CONNECTED) return false;
  String date = currentDate();
  String url = String(API_BASE) + "/recap_row.php?uid=" + uidHex + "&date=" + date;
  HTTPClient http;
  http.begin(url);
  int code = http.GET();
  if (code == 200) {
    String resp = http.getString();
    DynamicJsonDocument doc(768);
    if (deserializeJson(doc, resp) == DeserializationError::Ok && doc["ok"]) {
      outName = doc["row"]["name"].is<const char*>() ? String(doc["row"]["name"].as<const char*>()) : String("");
      statusMasuk = doc["row"]["masuk_status"].is<const char*>() ? String(doc["row"]["masuk_status"].as<const char*>()) : String("Tidak Hadir");
      statusPulang = doc["row"]["pulang_status"].is<const char*>() ? String(doc["row"]["pulang_status"].as<const char*>()) : String("Belum Pulang");
      // set flags first/last
      gHasFirstToday = doc["row"]["first_ts"].is<const char*>() && String(doc["row"]["first_ts"].as<const char*>()).length() > 0;
      gHasLastToday  = doc["row"]["last_ts"].is<const char*>() && String(doc["row"]["last_ts"].as<const char*>()).length() > 0;
      http.end();
      return true;
    }
  }
  http.end();
  return false;
}
