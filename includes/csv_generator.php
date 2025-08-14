<?php
require_once __DIR__ . '/../config/database.php';

class CSVGenerator {
    private $conn;
    private $uploadDir;

    public function __construct($conn, $uploadDir) {
        $this->conn = $conn;
        $this->uploadDir = $uploadDir;
    }

    public function generateCSV() {
        // Query untuk mendapatkan data upload
        $query = "SELECT filename, title, keywords, upload_date FROM uploads ORDER BY upload_date DESC";
        $result = $this->conn->query($query);

        if (!$result) {
            throw new Exception("Error mengambil data: " . $this->conn->error);
        }

        // Buat file CSV
        $csvFile = 'adobe_stock_metadata_' . date('Y-m-d_H-i-s') . '.csv';
        $fp = fopen($csvFile, 'w');

        // Header CSV sesuai format Adobe Stock
        $headers = [
            'Filename',
            'Title',
            'Keywords',
            'Category',
            'Releases',
            'Editorial',
            'Mature Content',
            'Location',
            'Date Created',
            'Date Submitted'
        ];
        fputcsv($fp, $headers);

        // Isi data
        while ($row = $result->fetch_assoc()) {
            $data = [
                $row['filename'],
                $row['title'],
                $row['keywords'],
                '', // Category (kosong, bisa diisi manual)
                '', // Releases (kosong, bisa diisi manual)
                'No', // Editorial (default No)
                'No', // Mature Content (default No)
                '', // Location (kosong, bisa diisi manual)
                date('Y-m-d'), // Date Created (hari ini)
                $row['upload_date'] // Date Submitted
            ];
            fputcsv($fp, $data);
        }

        fclose($fp);
        return $csvFile;
    }

    public function downloadCSV() {
        try {
            $csvFile = $this->generateCSV();
            
            if (file_exists($csvFile)) {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $csvFile . '"');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                readfile($csvFile);
                unlink($csvFile); // Hapus file setelah didownload
                exit;
            } else {
                throw new Exception("File CSV tidak ditemukan");
            }
        } catch (Exception $e) {
            throw new Exception("Error membuat file CSV: " . $e->getMessage());
        }
    }
}
?> 