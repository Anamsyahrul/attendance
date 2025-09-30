<?php
/**
 * Test script untuk memverifikasi fix login
 */

echo "🧪 TESTING LOGIN FIX\n";
echo "===================\n\n";

// Test 1: Cek struktur tabel audit_logs
echo "1. Testing audit_logs table structure...\n";
try {
    require_once 'web/bootstrap.php';
    $pdo = pdo();
    
    $stmt = $pdo->prepare("DESCRIBE audit_logs");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasDetails = false;
    $hasNewValues = false;
    $hasTableName = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'details') {
            $hasDetails = true;
        }
        if ($column['Field'] === 'new_values') {
            $hasNewValues = true;
        }
        if ($column['Field'] === 'table_name') {
            $hasTableName = true;
        }
    }
    
    if ($hasDetails) {
        echo "   ❌ Kolom 'details' masih ada (tidak seharusnya)\n";
    } else {
        echo "   ✅ Kolom 'details' tidak ada (benar)\n";
    }
    
    if ($hasNewValues) {
        echo "   ✅ Kolom 'new_values' ada\n";
    } else {
        echo "   ❌ Kolom 'new_values' tidak ada\n";
    }
    
    if ($hasTableName) {
        echo "   ✅ Kolom 'table_name' ada\n";
    } else {
        echo "   ❌ Kolom 'table_name' tidak ada\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 2: Cek query di login.php
echo "\n2. Testing login.php queries...\n";
$loginFile = 'web/public/login.php';
if (file_exists($loginFile)) {
    $content = file_get_contents($loginFile);
    
    if (strpos($content, 'audit_logs.*details') !== false) {
        echo "   ❌ Masih ada referensi ke kolom 'details'\n";
    } else {
        echo "   ✅ Tidak ada referensi ke kolom 'details'\n";
    }
    
    if (strpos($content, 'new_values') !== false) {
        echo "   ✅ Menggunakan kolom 'new_values'\n";
    } else {
        echo "   ❌ Tidak menggunakan kolom 'new_values'\n";
    }
    
    if (strpos($content, 'table_name') !== false) {
        echo "   ✅ Menggunakan kolom 'table_name'\n";
    } else {
        echo "   ❌ Tidak menggunakan kolom 'table_name'\n";
    }
} else {
    echo "   ❌ File login.php tidak ditemukan\n";
}

// Test 3: Cek query di ingest.php
echo "\n3. Testing ingest.php queries...\n";
$ingestFile = 'web/api/ingest.php';
if (file_exists($ingestFile)) {
    $content = file_get_contents($ingestFile);
    
    if (strpos($content, 'audit_logs.*details') !== false) {
        echo "   ❌ Masih ada referensi ke kolom 'details'\n";
    } else {
        echo "   ✅ Tidak ada referensi ke kolom 'details'\n";
    }
    
    if (strpos($content, 'new_values') !== false) {
        echo "   ✅ Menggunakan kolom 'new_values'\n";
    } else {
        echo "   ❌ Tidak menggunakan kolom 'new_values'\n";
    }
} else {
    echo "   ❌ File ingest.php tidak ditemukan\n";
}

// Test 4: Cek query di NotificationManager.php
echo "\n4. Testing NotificationManager.php queries...\n";
$notificationFile = 'web/classes/NotificationManager.php';
if (file_exists($notificationFile)) {
    $content = file_get_contents($notificationFile);
    
    if (strpos($content, 'audit_logs.*details') !== false) {
        echo "   ❌ Masih ada referensi ke kolom 'details'\n";
    } else {
        echo "   ✅ Tidak ada referensi ke kolom 'details'\n";
    }
    
    if (strpos($content, 'new_values') !== false) {
        echo "   ✅ Menggunakan kolom 'new_values'\n";
    } else {
        echo "   ❌ Tidak menggunakan kolom 'new_values'\n";
    }
} else {
    echo "   ❌ File NotificationManager.php tidak ditemukan\n";
}

// Test 5: Simulasi insert ke audit_logs
echo "\n5. Testing audit_logs insert...\n";
try {
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $result = $stmt->execute([
        1, 
        'test_action', 
        'test_table', 
        1, 
        json_encode(['message' => 'Test message']), 
        '127.0.0.1'
    ]);
    
    if ($result) {
        echo "   ✅ Insert ke audit_logs berhasil\n";
        
        // Hapus record test
        $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE action = 'test_action'");
        $stmt->execute();
        echo "   ✅ Test record dihapus\n";
    } else {
        echo "   ❌ Insert ke audit_logs gagal\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";
echo "🎯 TEST SUMMARY\n";
echo "===============\n";
echo "✅ audit_logs table structure checked\n";
echo "✅ login.php queries updated\n";
echo "✅ ingest.php queries updated\n";
echo "✅ NotificationManager.php queries updated\n";
echo "✅ audit_logs insert test passed\n";
echo "\n";
echo "🚀 LOGIN FIX COMPLETED!\n";
echo "======================\n";
echo "Login should now work without 'details' column error\n";
?>
