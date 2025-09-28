# 📡 SISTEM RFID SCAN BEHAVIOR - PENJELASAN LENGKAP

## 🎯 Overview
Dokumentasi lengkap tentang bagaimana sistem attendance menangani scan RFID dan apa yang terjadi ketika scan tidak sesuai dengan database.

## 🔧 Konfigurasi Sistem

### **AUTO_CREATE_UNKNOWN**
- **Status**: `false` (default)
- **Deskripsi**: Apakah sistem otomatis membuat user baru untuk UID yang tidak dikenal
- **Lokasi**: `attendance/web/bootstrap.php` baris 55

## 📊 Behavior Sistem RFID Scan

### **1. Scan RFID - UID yang ADA di Database**

#### **Proses:**
1. **ESP32** mengirim data scan ke `web/api/ingest.php`
2. **Sistem** memverifikasi HMAC untuk keamanan
3. **Sistem** mencari UID di tabel `users`
4. **UID ditemukan** → Data tersimpan dengan `user_id` yang sesuai
5. **Response**: `{"ok":true,"saved":1,"errors":[]}`

#### **Contoh:**
```json
{
    "device_id": "esp32-01",
    "nonce": "test123",
    "ts": "2025-09-28T17:15:49+07:00",
    "hmac": "36aafefb6514cc5ddddaf76f1f6c32a348b0ac2e2c93a06c2bbbca32ab8f8fa9",
    "events": [
        {
            "uid": "04a1b2c3d4",
            "ts": "2025-09-28T17:15:49+07:00"
        }
    ]
}
```

#### **Hasil:**
- ✅ **Data tersimpan** dengan `user_id` yang sesuai
- ✅ **Name** ditampilkan dengan benar (Alice)
- ✅ **Status** muncul di dashboard
- ✅ **Real-time update** langsung terlihat

---

### **2. Scan RFID - UID yang TIDAK ADA di Database**

#### **Proses:**
1. **ESP32** mengirim data scan ke `web/api/ingest.php`
2. **Sistem** memverifikasi HMAC untuk keamanan
3. **Sistem** mencari UID di tabel `users`
4. **UID tidak ditemukan** → Data tersimpan dengan `user_id = NULL`
5. **Response**: `{"ok":true,"saved":1,"errors":[]}`

#### **Contoh:**
```json
{
    "device_id": "esp32-01",
    "nonce": "test123",
    "ts": "2025-09-28T17:15:49+07:00",
    "hmac": "532a22870d8588490894c388786f64e739ea16d8053879d42efcab610b91e741",
    "events": [
        {
            "uid": "99unknown99",
            "ts": "2025-09-28T17:15:49+07:00"
        }
    ]
}
```

#### **Hasil:**
- ✅ **Data tersimpan** dengan `user_id = NULL`
- ❌ **Name** tidak ditampilkan (NULL)
- ❌ **Status** tidak muncul di dashboard
- ⚠️ **Data tersimpan** tapi tidak terlihat di interface

---

## 🔍 Detail Behavior

### **Data yang Tersimpan:**
```sql
-- UID yang ADA di database
ID: 15867, UID: 04a1b2c3d4, Name: Alice, TS: 2025-09-28 17:15:49, Device: esp32-01

-- UID yang TIDAK ADA di database
ID: 15868, UID: 9999, Name: NULL, TS: 2025-09-28 17:15:49, Device: esp32-01
```

### **Tabel Attendance:**
- **user_id**: NULL untuk UID yang tidak dikenal
- **device_id**: ID device yang melakukan scan
- **ts**: Timestamp scan
- **uid_hex**: UID yang di-scan
- **raw_json**: Data lengkap dari ESP32

### **Tabel Users:**
- **Tidak berubah** untuk UID yang tidak dikenal
- **AUTO_CREATE_UNKNOWN = false** mencegah pembuatan user otomatis

## 🚨 Apa yang Terjadi Ketika Scan Tidak Sesuai Database?

### **Skenario 1: UID Tidak Dikenal**
- ✅ **Data tersimpan** di tabel `attendance`
- ❌ **Tidak muncul** di dashboard
- ❌ **Tidak ada** user baru yang dibuat
- ⚠️ **Data "tersembunyi"** sampai user ditambahkan manual

### **Skenario 2: Device Tidak Terdaftar**
- ❌ **Data tidak tersimpan**
- ❌ **Response error**: `"Device not authorized"`
- ❌ **HTTP 401** Unauthorized

### **Skenario 3: HMAC Invalid**
- ❌ **Data tidak tersimpan**
- ❌ **Response error**: `"Invalid HMAC"`
- ❌ **HTTP 401** Unauthorized

### **Skenario 4: Format Data Salah**
- ❌ **Data tidak tersimpan**
- ❌ **Response error**: `"Missing required fields"`
- ❌ **HTTP 400** Bad Request

## 🔧 Konfigurasi untuk Auto-Create User

### **Aktifkan Auto-Create:**
```php
// Di attendance/web/config.php
'AUTO_CREATE_UNKNOWN' => true,
```

### **Behavior dengan Auto-Create:**
- ✅ **User baru** otomatis dibuat
- ✅ **Name**: "Unknown [UID]"
- ✅ **Room**: Kosong
- ✅ **Data muncul** di dashboard

### **Contoh User yang Dibuat:**
```sql
INSERT INTO users (name, uid_hex, room) VALUES ('Unknown 99UNKNOWN99', '99unknown99', '');
```

## 📱 Real-time Update

### **Data Langsung Berubah:**
- ✅ **Scan RFID** → Data langsung tersimpan
- ✅ **Dashboard** → Update real-time
- ✅ **Status** → Langsung terlihat
- ✅ **Tidak perlu refresh** halaman

### **Proses Real-time:**
1. **ESP32** scan RFID
2. **Data dikirim** ke server
3. **Database** diupdate
4. **Dashboard** otomatis refresh
5. **Status** langsung terlihat

## 🎯 Rekomendasi Konfigurasi

### **Untuk Produksi:**
```php
'AUTO_CREATE_UNKNOWN' => false,  // Aman, tidak ada user random
```

### **Untuk Testing:**
```php
'AUTO_CREATE_UNKNOWN' => true,   // Mudah testing
```

### **Untuk Keamanan:**
- ✅ **HMAC verification** aktif
- ✅ **Device registration** required
- ✅ **Input validation** ketat
- ✅ **SQL injection** protection

## 🔍 Monitoring dan Debugging

### **Cek Data Tersembunyi:**
```sql
SELECT a.id, a.uid_hex, a.ts, a.device_id, u.name 
FROM attendance a 
LEFT JOIN users u ON a.user_id = u.id 
WHERE u.name IS NULL
ORDER BY a.ts DESC;
```

### **Cek Data Valid:**
```sql
SELECT a.id, a.uid_hex, a.ts, a.device_id, u.name 
FROM attendance a 
LEFT JOIN users u ON a.user_id = u.id 
WHERE u.name IS NOT NULL
ORDER BY a.ts DESC;
```

## 🎉 Kesimpulan

### **Sistem RFID Scan:**
- ✅ **Data langsung berubah** ketika ada scan
- ✅ **Real-time update** di dashboard
- ✅ **Keamanan** dengan HMAC verification
- ✅ **Fleksibilitas** dengan auto-create option

### **Ketika Scan Tidak Sesuai Database:**
- ✅ **Data tetap tersimpan** (dengan user_id = NULL)
- ❌ **Tidak muncul** di dashboard
- ⚠️ **Perlu** menambahkan user manual
- 🔧 **Bisa** diaktifkan auto-create

### **Rekomendasi:**
- **Gunakan** `AUTO_CREATE_UNKNOWN = false` untuk produksi
- **Monitor** data tersembunyi secara berkala
- **Tambahkan** user baru melalui admin panel
- **Aktifkan** auto-create hanya untuk testing

---
**Status: ✅ PRODUCTION READY**  
**Last Updated**: 28 September 2025  
**Version**: 1.0.0 - RFID Scan Behavior Documentation
