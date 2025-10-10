# 🔄 WORKFLOW COMMIT & PUSH - PANDUAN LENGKAP

## 🎯 Overview
Dokumentasi lengkap tentang workflow commit dan push yang harus dilakukan setiap kali ada perubahan pada sistem attendance.

## 📋 Prinsip Dasar

### **1. Commit Setiap Perubahan**
- ✅ **Setiap perubahan** harus di-commit
- ✅ **Pesan commit** yang jelas dan deskriptif
- ✅ **Atomic commits** untuk kemudahan rollback
- ✅ **File terorganisir** dengan baik

### **2. Push Setiap Commit**
- ✅ **Push langsung** setelah commit
- ✅ **Sinkronisasi** dengan repository GitHub
- ✅ **Backup** perubahan di cloud
- ✅ **Kolaborasi** dengan tim

## 🔧 Workflow Standar

### **Step 1: Cek Status**
```bash
git status
```

### **Step 2: Add File yang Diubah**
```bash
# Add file spesifik
git add filename.php

# Add semua file yang diubah
git add .

# Add file dengan pattern
git add *.md
```

### **Step 3: Commit dengan Pesan Jelas**
```bash
git commit -m "Fix: Description of what was fixed"
git commit -m "Add: Description of what was added"
git commit -m "Update: Description of what was updated"
```

### **Step 4: Push ke GitHub**
```bash
git push origin main
```

## 📝 Format Pesan Commit

### **Prefix yang Digunakan:**
- **Fix**: Untuk perbaikan bug
- **Add**: Untuk fitur baru
- **Update**: Untuk update fitur existing
- **Remove**: Untuk menghapus fitur
- **Refactor**: Untuk refactoring code
- **Docs**: Untuk dokumentasi

### **Contoh Pesan Commit:**
```bash
# Fix
git commit -m "Fix: Enable SCHOOL_MODE by default to show edit buttons in recap view"
git commit -m "Fix: Correct JavaScript regex patterns for date and time validation"

# Add
git commit -m "Add: Complete documentation for RFID scan behavior"
git commit -m "Add: Selective cleanup logic for status independence"

# Update
git commit -m "Update: Replace LIKE with JSON_EXTRACT for reliable JSON querying"
git commit -m "Update: Improve subqueries for first_ts and last_ts retrieval"

# Docs
git commit -m "Docs: Add comprehensive system documentation"
git commit -m "Docs: Update commit history and workflow guide"
```

## 🗂️ Organisasi File

### **Struktur Direktori:**
```
attendance/
├── web/
│   ├── api/
│   ├── public/
│   └── bootstrap.php
├── firmware/
├── docs/
├── *.md (documentation)
└── README.md
```

### **File yang Harus Di-commit:**
- ✅ **Source code** (PHP, JS, CSS)
- ✅ **Configuration** files
- ✅ **Documentation** (MD files)
- ✅ **Database** schema/seed files
- ✅ **Firmware** code

### **File yang TIDAK Di-commit:**
- ❌ **Temporary** files
- ❌ **Test** files (kecuali yang penting)
- ❌ **Log** files
- ❌ **Cache** files
- ❌ **Personal** configuration

## 🔄 Workflow untuk Setiap Jenis Perubahan

### **1. Perbaikan Bug**
```bash
# 1. Fix the bug
# 2. Test the fix
git add web/api/set_event.php
git commit -m "Fix: Implement selective cleanup logic to prevent status interference"
git push origin main
```

### **2. Menambah Fitur Baru**
```bash
# 1. Implement feature
# 2. Test feature
git add web/public/index.php
git commit -m "Add: New feature for status editing"
git push origin main
```

### **3. Update Dokumentasi**
```bash
# 1. Write documentation
git add *.md
git commit -m "Docs: Add comprehensive system documentation"
git push origin main
```

### **4. Refactoring Code**
```bash
# 1. Refactor code
# 2. Test refactored code
git add web/bootstrap.php
git commit -m "Refactor: Replace LIKE with JSON_EXTRACT for better performance"
git push origin main
```

## 📊 Monitoring Commit History

### **Cek History:**
```bash
git log --oneline
git log --graph --oneline --all
```

### **Cek Status:**
```bash
git status
git diff
```

### **Cek Remote:**
```bash
git remote -v
git branch -a
```

## 🚨 Best Practices

### **1. Commit Sering**
- ✅ **Commit setiap** perubahan kecil
- ✅ **Jangan tunggu** sampai banyak perubahan
- ✅ **Atomic commits** untuk kemudahan tracking

### **2. Pesan yang Jelas**
- ✅ **Gunakan** prefix yang sesuai
- ✅ **Deskripsikan** apa yang diubah
- ✅ **Singkat** tapi informatif
- ✅ **Bahasa Inggris** untuk konsistensi

### **3. Test Sebelum Commit**
- ✅ **Test** perubahan sebelum commit
- ✅ **Pastikan** tidak ada error
- ✅ **Verifikasi** fungsionalitas

### **4. Push Segera**
- ✅ **Push** setelah commit
- ✅ **Jangan** menumpuk commit
- ✅ **Sinkronisasi** dengan tim

## 🔧 Konfigurasi Git

### **Set User Identity:**
```bash
git config user.name "Anam Syahrul"
git config user.email "anamsyahrul@example.com"
```

### **Set Default Branch:**
```bash
git config init.defaultBranch main
```

### **Set Push Default:**
```bash
git config push.default simple
```

## 📈 Contoh Workflow Lengkap

### **Skenario: Fix Bug Edit Status**
```bash
# 1. Cek status
git status

# 2. Fix bug di set_event.php
# (Edit file)

# 3. Test fix
php test_edit_status.php

# 4. Add file
git add web/api/set_event.php

# 5. Commit
git commit -m "Fix: Implement selective cleanup logic to prevent status interference between masuk and pulang"

# 6. Push
git push origin main

# 7. Verifikasi
git status
```

### **Skenario: Add Documentation**
```bash
# 1. Write documentation
# (Create new .md file)

# 2. Add file
git add *.md

# 3. Commit
git commit -m "Docs: Add comprehensive documentation for attendance system"

# 4. Push
git push origin main
```

## 🎉 Kesimpulan

### **Workflow yang Benar:**
1. ✅ **Edit** file
2. ✅ **Test** perubahan
3. ✅ **Add** file ke staging
4. ✅ **Commit** dengan pesan jelas
5. ✅ **Push** ke GitHub
6. ✅ **Verifikasi** status

### **Manfaat:**
- 🔄 **Version control** yang baik
- 📝 **History** perubahan yang jelas
- 🔒 **Backup** di cloud
- 👥 **Kolaborasi** dengan tim
- 🔄 **Rollback** jika diperlukan

---
**Status: ✅ PRODUCTION READY**  
**Last Updated**: 28 September 2025  
**Version**: 1.0.0 - Workflow Commit & Push Guide

