<?php
// Konfigurasi Gemini API
define('GEMINI_API_KEY', 'AIzaSyAxcAA8uWZuND59dOcEEjHtPucx-4h4-mY'); // Ganti dengan API key Anda
define('API_TIMEOUT', 30);

// Konfigurasi untuk generate caption dan keyword
define('CAPTION_PROMPT', 'Describe this image in detail, focusing on the main subject and important elements. Format: Title: [title] Keywords: [comma-separated keywords]');
define('MAX_KEYWORDS', 49);

// Direktori untuk menyimpan foto
define('UPLOAD_DIR', 'uploads/');
?> 