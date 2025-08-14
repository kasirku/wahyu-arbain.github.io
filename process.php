<?php
require_once 'config/database.php';
require_once 'config/api_config.php';
require_once 'includes/gemini_helper.php';

header('Content-Type: application/json');

try {
    // Cek apakah ada file yang diupload
    if (!isset($_FILES['photo']) || empty($_FILES['photo']['name'])) {
        throw new Exception('Tidak ada file yang diupload');
    }

    $file = $_FILES['photo'];
    
    // Validasi file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi batas maksimum yang diizinkan',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas maksimum yang diizinkan',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
        ];
        $errorMessage = isset($errorMessages[$file['error']]) 
            ? $errorMessages[$file['error']] 
            : 'Error saat upload file: ' . $file['error'];
        throw new Exception($errorMessage);
    }

    // Validasi tipe file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Format file tidak didukung. Gunakan JPG, PNG, atau GIF');
    }

    // Validasi ukuran file (maksimal 5MB)
    $maxFileSize = 5 * 1024 * 1024; // 5MB dalam bytes
    if ($file['size'] > $maxFileSize) {
        throw new Exception('Ukuran file terlalu besar. Maksimal 5MB');
    }

    // Validasi file upload
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('File tidak valid atau tidak diupload dengan benar');
    }

    // Buat direktori upload jika belum ada
    if (!file_exists(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0777, true)) {
            throw new Exception('Gagal membuat direktori upload');
        }
    }

    // Generate nama file unik
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $file['name']; // Gunakan nama file asli
    $filepath = UPLOAD_DIR . $filename;

    // Cek apakah file dengan nama yang sama sudah ada
    $counter = 1;
    $originalName = pathinfo($filename, PATHINFO_FILENAME);
    while (file_exists($filepath)) {
        $filename = $originalName . '_' . $counter . '.' . $extension;
        $filepath = UPLOAD_DIR . $filename;
        $counter++;
    }

    // Pindahkan file ke direktori upload
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        $uploadError = error_get_last();
        throw new Exception('Gagal menyimpan file: ' . ($uploadError ? $uploadError['message'] : 'Unknown error'));
    }

    // Verifikasi file berhasil diupload
    if (!file_exists($filepath)) {
        throw new Exception('File tidak ditemukan setelah upload');
    }

    // Verifikasi file bisa dibaca
    if (!is_readable($filepath)) {
        throw new Exception('File tidak bisa dibaca setelah upload');
    }

    // Generate metadata menggunakan Gemini
    $gemini = new GeminiHelper();
    try {
        $metadata = $gemini->generateMetadata($filepath);
        
        if (!$metadata || !isset($metadata['title']) || !isset($metadata['keywords'])) {
            error_log("Invalid metadata structure: " . json_encode($metadata));
            throw new Exception('Gagal generate metadata: Format tidak sesuai');
        }
    } catch (Exception $e) {
        error_log("Metadata generation error: " . $e->getMessage());
        // Hapus file jika gagal generate metadata
        unlink($filepath);
        throw new Exception('Gagal generate metadata: ' . $e->getMessage());
    }

    // Simpan ke database
    $stmt = $conn->prepare("INSERT INTO uploads (filename, title, keywords, upload_date) VALUES (?, ?, ?, NOW())");
    if (!$stmt) {
        throw new Exception('Gagal menyiapkan query database: ' . $conn->error);
    }

    $keywords = implode(', ', $metadata['keywords']);
    $stmt->bind_param("sss", $filename, $metadata['title'], $keywords);
    
    if (!$stmt->execute()) {
        // Hapus file jika gagal menyimpan ke database
        unlink($filepath);
        throw new Exception('Gagal menyimpan ke database: ' . $stmt->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'File berhasil diupload dan metadata berhasil digenerate',
        'data' => [
            'filename' => $filename,
            'title' => $metadata['title'],
            'keywords' => $metadata['keywords']
        ]
    ]);

} catch (Exception $e) {
    // Log error untuk debugging
    error_log('Upload Error: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 