<?php
// Set timeout PHP ke 5 menit
set_time_limit(300);
ini_set('max_execution_time', 300);

require_once 'config/database.php';
require_once 'config/api_config.php';
require_once 'includes/gemini_helper.php';
require_once 'includes/csv_generator.php';

// Buat direktori upload jika belum ada
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

$message = '';

// Handle hapus semua data
if (isset($_GET['action']) && $_GET['action'] === 'delete_all' && isset($_GET['confirm']) && $_GET['confirm'] === 'true') {
    try {
        // Ambil semua nama file dari database
        $result = $conn->query("SELECT filename FROM uploads");
        while ($row = $result->fetch_assoc()) {
            $filePath = UPLOAD_DIR . $row['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Hapus semua data dari database
        $conn->query("TRUNCATE TABLE uploads");
        
        $message = "Semua data dan foto berhasil dihapus!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle hapus foto
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id = (int)$_GET['id'];
        
        // Ambil informasi file sebelum dihapus
        $stmt = $conn->prepare("SELECT filename FROM uploads WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $file = $result->fetch_assoc();
        
        if ($file) {
            // Hapus file fisik
            $filePath = UPLOAD_DIR . $file['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Hapus dari database
            $stmt = $conn->prepare("DELETE FROM uploads WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            $message = "Foto berhasil dihapus!";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle CSV download
if (isset($_GET['action']) && $_GET['action'] === 'download_csv') {
    try {
        $csvGenerator = new CSVGenerator($conn, UPLOAD_DIR);
        $csvGenerator->downloadCSV();
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Redirect jika ada parameter yang tidak diinginkan
if (isset($_GET['action']) && !in_array($_GET['action'], ['delete', 'delete_all', 'download_csv'])) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Photo Metadata Generator</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #22c55e;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #64748b;
            --glass: rgba(255, 255, 255, 0.9);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #a8c0ff 0%, #3f2b96 100%);
            color: var(--dark);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.2) 0%, transparent 50%),
                linear-gradient(45deg, transparent 48%, rgba(255, 255, 255, 0.1) 49%, rgba(255, 255, 255, 0.1) 51%, transparent 52%),
                linear-gradient(-45deg, transparent 48%, rgba(255, 255, 255, 0.1) 49%, rgba(255, 255, 255, 0.1) 51%, transparent 52%);
            background-size: 100% 100%, 100% 100%, 60px 60px, 60px 60px;
            pointer-events: none;
            z-index: 0;
        }

        /* Ornamen Dekoratif */
        .ornament {
            position: fixed;
            pointer-events: none;
            z-index: 0;
        }

        .ornament-1 {
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 20%, rgba(255,255,255,0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.15) 0%, transparent 50%);
            opacity: 0.5;
        }

        .ornament-2 {
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(45deg, transparent 48%, rgba(255,255,255,0.1) 49%, rgba(255,255,255,0.1) 51%, transparent 52%),
                linear-gradient(-45deg, transparent 48%, rgba(255,255,255,0.1) 49%, rgba(255,255,255,0.1) 51%, transparent 52%);
            background-size: 60px 60px;
            opacity: 0.3;
        }

        .container {
            position: relative;
            z-index: 1;
        }

        .navbar-brand {
            font-weight: 700;
            color: white !important;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        .card-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            letter-spacing: -0.5px;
        }

        .drop-zone {
            border: 2px dashed rgba(99, 102, 241, 0.3);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .drop-zone:hover {
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.8);
            transform: scale(1.02);
        }

        .drop-zone i {
            color: var(--primary);
            font-size: 3.5rem;
            margin-bottom: 1rem;
            transition: all 0.4s ease;
        }

        .drop-zone:hover i {
            transform: scale(1.1);
        }

        .preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .preview-item {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .preview-item:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .preview-item img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            transition: all 0.4s ease;
        }

        .preview-item:hover img {
            transform: scale(1.05);
        }

        .preview-item .remove-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            opacity: 0;
            transform: scale(0.8);
        }

        .preview-item:hover .remove-btn {
            opacity: 1;
            transform: scale(1);
        }

        .preview-item .remove-btn:hover {
            background: var(--danger);
            color: white;
            transform: scale(1.1);
        }

        .progress-container {
            margin-top: 2rem;
        }

        .progress-item {
            margin-bottom: 1rem;
            padding: 1.25rem;
            border-radius: 16px;
            background: var(--glass);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .progress-item:hover {
            transform: translateX(8px);
        }

        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn {
            padding: 0.875rem 1.75rem;
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #16a34a);
            border: none;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            border: none;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border-radius: 8px;
        }

        .btn-xs {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 6px;
        }

        .btn-xs i {
            font-size: 0.75rem;
        }

        .btn-compact {
            padding: 0.35rem 1rem;
            font-size: 0.8rem;
            border-radius: 6px;
            min-width: auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            letter-spacing: 0.2px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        }

        .btn-compact:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .btn-compact i {
            font-size: 0.8rem;
            line-height: 1;
            margin: 0;
            position: relative;
            top: 0.5px;
            color: rgba(255, 255, 255, 0.95);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.1));
        }

        .btn-compact:hover i {
            color: #ffffff;
            transform: scale(1.05);
            transition: all 0.2s ease;
        }

        .table {
            background: var(--glass);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-weight: 600;
            border: none;
            padding: 1.25rem;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 1.25rem;
            vertical-align: middle;
            border-color: rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .table tbody tr:hover td {
            background-color: rgba(99, 102, 241, 0.05);
        }

        .alert {
            border: none;
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.5s ease-out;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: currentColor;
            opacity: 0.5;
        }

        .alert-info {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(79, 70, 229, 0.1));
            color: var(--primary);
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.1);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.1));
            color: var(--success);
            box-shadow: 0 8px 32px rgba(34, 197, 94, 0.1);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            color: var(--danger);
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.1);
        }

        .alert i {
            font-size: 1.5rem;
            opacity: 0.8;
        }

        .alert .alert-content {
            flex: 1;
        }

        .alert .btn-close {
            background: none;
            border: none;
            color: currentColor;
            opacity: 0.5;
            transition: all 0.3s ease;
            padding: 0.5rem;
            margin: -0.5rem;
            border-radius: 8px;
        }

        .alert .btn-close:hover {
            opacity: 1;
            background: rgba(255, 255, 255, 0.1);
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .status {
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            background: var(--glass);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            transition: all 0.3s ease;
        }

        .status.success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
        }

        .status.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .status.processing {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .processing .status {
            animation: pulse 2s infinite;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Modern Success Toast */
        .success-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.95), rgba(22, 163, 74, 0.95));
            color: white;
            padding: 1.25rem 1.5rem;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(34, 197, 94, 0.3);
            display: flex;
            align-items: center;
            gap: 1rem;
            z-index: 9999;
            animation: slideInRight 0.5s ease-out;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .success-toast i {
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .success-toast .message {
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Efek Glass pada Card */
        .card {
            background: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Efek Glass pada Navbar */
        .navbar {
            background: rgba(99, 102, 241, 0.9) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Animasi Keren */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Animasi untuk Card */
        .card {
            animation: fadeInUp 0.6s ease-out;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        /* Animasi untuk Button */
        .btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            transform: rotate(45deg);
            transition: all 0.3s ease;
            opacity: 0;
        }

        .btn:hover::after {
            animation: shimmer 1.5s infinite;
        }

        /* Animasi untuk Navbar */
        .navbar {
            animation: fadeInUp 0.6s ease-out;
        }

        .navbar-brand {
            animation: pulse 2s infinite;
        }

        /* Animasi untuk Table */
        .table tbody tr {
            animation: fadeInUp 0.6s ease-out;
            animation-fill-mode: both;
        }

        .table tbody tr:nth-child(1) { animation-delay: 0.1s; }
        .table tbody tr:nth-child(2) { animation-delay: 0.2s; }
        .table tbody tr:nth-child(3) { animation-delay: 0.3s; }
        .table tbody tr:nth-child(4) { animation-delay: 0.4s; }
        .table tbody tr:nth-child(5) { animation-delay: 0.5s; }

        /* Animasi untuk Drop Zone */
        .drop-zone {
            animation: fadeInUp 0.6s ease-out;
            transition: all 0.3s ease;
        }

        .drop-zone:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .drop-zone i {
            animation: none;
        }

        /* Animasi untuk Preview Items */
        .preview-item {
            animation: fadeInUp 0.6s ease-out;
            transition: all 0.3s ease;
        }

        .preview-item:hover {
            transform: scale(1.05) rotate(2deg);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        /* Animasi untuk Progress Bar */
        .progress-bar {
            transition: width 0.6s ease;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            background-size: 200% 100%;
            animation: shimmer 2s infinite linear;
        }

        /* Animasi untuk Alert */
        .alert {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Animasi untuk Success Toast */
        .success-toast {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Efek Hover pada Table Row */
        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            transform: translateX(10px);
            background-color: rgba(99, 102, 241, 0.1);
        }

        /* Animasi Loading */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            animation: shimmer 1.5s infinite;
        }
    </style>
</head>
<body>
    <!-- Ornamen Dekoratif -->
    <div class="ornament ornament-1"></div>
    <div class="ornament ornament-2"></div>

    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-images me-2"></i>
                Stock Photo Metadata Generator
            </a>
        </div>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle"></i>
                <div class="alert-content">
                    <?php echo $message; ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Upload Foto</h5>
                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="drop-zone" id="dropZone">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p class="mb-4">Drag & drop foto di sini atau klik untuk memilih</p>
                                <input type="file" id="fileInput" multiple accept="image/*" style="display: none;">
                                <button type="button" class="btn btn-primary btn-compact" onclick="document.getElementById('fileInput').click()">
                                    <i class="fas fa-folder-open"></i>Pilih Foto
                                </button>
                            </div>
                            <div id="previewContainer" class="preview-container"></div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary" id="generateBtn" disabled>
                                    <i class="fas fa-magic me-2"></i>Generate Metadata
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="progress-container" id="progressContainer" style="display: none;"></div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Aksi</h5>
                        <div class="d-grid gap-3">
                            <a href="?action=download_csv" class="btn btn-success">
                                <i class="fas fa-download me-2"></i>Download CSV
                            </a>
                            <button type="button" class="btn btn-danger" onclick="handleDeleteAll()">
                                <i class="fas fa-trash me-2"></i>Hapus Semua Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Daftar Foto</h5>
                        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                                        <th>Foto</th>
                        <th>Judul</th>
                        <th>Keywords</th>
                        <th>Tanggal Upload</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM uploads ORDER BY upload_date DESC");
                    while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                                        <td>
                                            <img src="<?php echo UPLOAD_DIR . $row['filename']; ?>" 
                                                 alt="<?php echo htmlspecialchars($row['title']); ?>"
                                                 style="width: 100px; height: 100px; object-fit: cover; border-radius: 12px;">
                                        </td>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['keywords']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($row['upload_date'])); ?></td>
                        <td>
                                            <a href="?action=delete&id=<?php echo $row['id']; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Yakin ingin menghapus foto ini?')">
                                                <i class="fas fa-trash"></i>
                                </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const previewContainer = document.getElementById('previewContainer');
        const generateBtn = document.getElementById('generateBtn');
        const progressContainer = document.getElementById('progressContainer');

        let files = [];

        // Drag & Drop handlers
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('dragover');
        }

        function unhighlight(e) {
            dropZone.classList.remove('dragover');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const newFiles = [...dt.files];
            handleFiles(newFiles);
        }

        fileInput.addEventListener('change', function() {
            handleFiles([...this.files]);
        });

        function handleFiles(newFiles) {
            const validFiles = newFiles.filter(file => {
                if (!file.type.startsWith('image/')) {
                    alert('Hanya file gambar yang diperbolehkan');
                    return false;
                }
                return true;
            });

            files = [...files, ...validFiles];
            updatePreview();
            updateGenerateButton();
        }

        function updatePreview() {
            previewContainer.innerHTML = '';
            files.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="${file.name}">
                        <button type="button" class="remove-btn" onclick="removeFile(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    previewContainer.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        }

        function removeFile(index) {
            files.splice(index, 1);
            updatePreview();
            updateGenerateButton();
        }

        function updateGenerateButton() {
            generateBtn.disabled = files.length === 0;
        }

        function createProgressBar(filename) {
            const div = document.createElement('div');
            div.className = 'progress-item';
            div.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>${filename}</span>
                    <span class="status">Memproses...</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                </div>
            `;
            return div;
        }

        function handleDeleteAll() {
            if (confirm('PERHATIAN: Tindakan ini akan menghapus SEMUA data dan foto yang sudah diupload. Tindakan ini tidak dapat dibatalkan. Apakah Anda yakin ingin melanjutkan?')) {
                window.location.href = 'index.php?action=delete_all&confirm=true';
            }
        }

        function showSuccessMessage(message) {
            const toast = document.createElement('div');
            toast.className = 'success-toast';
            toast.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <div class="message">${message}</div>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.5s ease-out forwards';
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 500);
            }, 3000);
        }

        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (files.length === 0) {
                alert('Pilih foto terlebih dahulu');
                return;
            }

            generateBtn.disabled = true;
            progressContainer.style.display = 'block';
            progressContainer.innerHTML = '';

            const progressBars = {};
            const progressItems = {};
            
            files.forEach(file => {
                const progressItem = createProgressBar(file.name);
                progressContainer.appendChild(progressItem);
                progressBars[file.name] = progressItem.querySelector('.progress-bar');
                progressItems[file.name] = progressItem;
            });

            let successCount = 0;
            let failCount = 0;

            for (const file of files) {
                try {
                    const formData = new FormData();
                    formData.append('photo', file);

                    const progressItem = progressItems[file.name];
                    const progressBar = progressBars[file.name];
                    const statusElement = progressItem.querySelector('.status');

                    progressBar.style.width = '25%';
                    statusElement.textContent = 'Menyiapkan file...';

                    // Validasi ukuran file
                    if (file.size > 5 * 1024 * 1024) {
                        throw new Error('Ukuran file terlalu besar. Maksimal 5MB');
                    }

                    // Validasi tipe file
                    if (!file.type.startsWith('image/')) {
                        throw new Error('Format file tidak didukung. Gunakan JPG, PNG, atau GIF');
                    }

                    progressBar.style.width = '50%';
                    statusElement.textContent = 'Mengupload...';

                    const response = await fetch('process.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }

                    const result = await response.json();

                    if (result.success) {
                        progressBar.style.width = '100%';
                        progressBar.classList.add('bg-success');
                        statusElement.textContent = 'Berhasil';
                        successCount++;
                    } else {
                        progressBar.style.width = '100%';
                        progressBar.classList.add('bg-danger');
                        statusElement.textContent = 'Gagal: ' + result.error;
                        failCount++;
                    }
                } catch (error) {
                    const progressItem = progressItems[file.name];
                    const progressBar = progressBars[file.name];
                    const statusElement = progressItem.querySelector('.status');

                    progressBar.style.width = '100%';
                    progressBar.classList.add('bg-danger');
                    statusElement.textContent = 'Error: ' + error.message;
                    failCount++;
                }
            }

            if (successCount > 0) {
                showSuccessMessage(`Berhasil memproses ${successCount} foto${failCount > 0 ? `, ${failCount} gagal` : ''}`);
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 3000);
            }

            generateBtn.disabled = false;
        });

        // Fungsi untuk menghilangkan alert secara otomatis
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease-out';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 3000);
            });
        });
    </script>
</body>
</html> 