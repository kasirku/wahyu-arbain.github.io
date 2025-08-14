<?php
class GeminiHelper {
    private $apiKey;
    private $timeout;
    private $maxRetries = 3;
    private $retryDelay = 2; // detik

    // Daftar keyword umum yang relevan untuk stock photo
    private $commonKeywords = [
        // People & Portraits
        'woman', 'man', 'people', 'child', 'children', 'family', 'portrait', 'face', 
        'smile', 'happy', 'emotion', 'expression', 'beauty', 'fashion', 'lifestyle',
        'model', 'young', 'adult', 'senior', 'couple', 'love', 'romantic', 'hugging',
        
        // Activities & Actions
        'walking', 'running', 'sitting', 'standing', 'relaxing', 'working', 'studying',
        'learning', 'reading', 'writing', 'meeting', 'teamwork', 'collaboration',
        
        // Business & Technology
        'office', 'business', 'startup', 'entrepreneur', 'professional', 'presentation',
        'communication', 'success', 'leadership', 'innovation', 'technology', 'digital',
        'internet', 'data', 'computer', 'laptop', 'mobile', 'smartphone', 'tablet',
        'coding', 'AI', 'artificial intelligence', 'virtual', 'cybersecurity', 'cloud',
        'software', 'app', 'interface', 'UX', 'UI', 'network', 'system',
        
        // Urban & Architecture
        'modern', 'urban', 'city', 'skyline', 'building', 'skyscraper', 'architecture',
        'design', 'minimal', 'concrete', 'glass', 'window', 'street', 'traffic',
        'pedestrian', 'car', 'bike', 'transport',
        
        // Nature & Landscape
        'nature', 'landscape', 'mountain', 'hill', 'forest', 'jungle', 'river', 'lake',
        'sea', 'beach', 'ocean', 'sunset', 'sunrise', 'sky', 'cloud', 'blue', 'green',
        'summer', 'spring', 'tropical', 'island', 'park', 'outdoor', 'hiking', 'camping',
        
        // Animals & Wildlife
        'wildlife', 'animal', 'bird', 'fish', 'dog', 'cat', 'pet', 'macro',
        
        // Plants & Agriculture
        'leaf', 'flower', 'plant', 'tree', 'agriculture', 'farm', 'field', 'rural',
        'village',
        
        // Food & Drink
        'food', 'drink', 'cuisine', 'cooking', 'kitchen', 'ingredient', 'vegetable',
        'fruit', 'dessert', 'cake', 'bread', 'coffee', 'tea', 'lunch', 'dinner',
        'breakfast', 'restaurant', 'cafe', 'plate', 'healthy', 'organic', 'natural',
        
        // Health & Fitness
        'fitness', 'health', 'wellness', 'yoga', 'meditation', 'sport', 'exercise',
        'training', 'gym', 'competition', 'energy', 'power',
        
        // Finance & Business
        'finance', 'money', 'banking', 'currency', 'coin', 'cash', 'credit card',
        'wallet', 'economy', 'chart', 'graph', 'investment', 'growth', 'marketing',
        'analytics', 'strategy', 'report', 'infographic',
        
        // Design & Art
        'concept', 'abstract', 'background', 'pattern', 'texture', 'marble', 'paper',
        'fabric', 'wood', 'water', 'smoke', 'fire', 'explosion', 'light', 'shadow',
        'contrast', 'gradient', 'colorful', 'rainbow', 'neon', 'glow', 'vintage',
        'retro', 'futuristic', '3D', 'render', 'vector', 'icon', 'symbol', 'button',
        'badge', 'label', 'logo', 'infographic', 'minimalism', 'flat design',
        'isometric', 'business card', 'flyer', 'poster', 'template', 'mockup',
        'branding',
        
        // Education
        'education', 'school', 'university', 'student', 'teacher', 'blackboard',
        'book', 'graduation', 'exam', 'test', 'writing', 'pencil', 'pen', 'paper',
        'notebook', 'document', 'office supplies',
        
        // Celebrations & Events
        'celebration', 'birthday', 'Christmas', 'New Year', 'holiday', 'party',
        'decoration', 'gift', 'love', 'heart', 'Valentine', 'wedding', 'ceremony',
        'flower', 'ring',
        
        // Culture & Community
        'religion', 'spiritual', 'culture', 'tradition', 'Indonesia', 'Asia', 'local',
        'ethnic', 'diversity', 'unity', 'community', 'support', 'volunteer', 'charity',
        'peace',
        
        // Environment & Sustainability
        'environment', 'sustainability', 'eco', 'green energy', 'recycling', 'climate',
        'weather', 'rain', 'snow', 'thunder', 'storm', 'cloudscape'
    ];

    public function __construct() {
        $this->apiKey = GEMINI_API_KEY;
        $this->timeout = API_TIMEOUT;
    }

    private function ensureKeywordsCount($keywords, $title) {
        // Jika keywords sudah 49, langsung return
        if (count($keywords) >= 49) {
            return array_slice($keywords, 0, 49);
        }

        // Tambahkan keyword dari title
        $titleWords = explode(' ', strtolower($title));
        foreach ($titleWords as $word) {
            $word = trim(preg_replace('/[^a-z0-9]/', '', $word));
            if (strlen($word) > 3 && !in_array($word, $keywords)) {
                $keywords[] = $word;
            }
        }

        // Jika masih kurang, tambahkan dari common keywords
        while (count($keywords) < 49) {
            $randomKeyword = $this->commonKeywords[array_rand($this->commonKeywords)];
            if (!in_array($randomKeyword, $keywords)) {
                $keywords[] = $randomKeyword;
            }
        }

        return array_slice($keywords, 0, 49);
    }

    public function generateMetadata($imagePath) {
        $retryCount = 0;
        $lastError = null;

        while ($retryCount < $this->maxRetries) {
            try {
                // Validasi file
                if (!file_exists($imagePath)) {
                    throw new Exception("File tidak ditemukan: $imagePath");
                }

                // Baca file gambar dan encode ke base64
                $imageData = file_get_contents($imagePath);
                if ($imageData === false) {
                    throw new Exception("Gagal membaca file gambar");
                }
                $imageData = base64_encode($imageData);
                
                // Siapkan data untuk API
                $data = [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => 'Analyze this image and provide a title and keywords in the following format exactly:\n\nTitle: [Write a descriptive title here]\nKeywords: [Write comma-separated keywords here]\n\nMake sure to:\n1. Start with "Title: " followed by a descriptive title\n2. Then "Keywords: " followed by comma-separated keywords\n3. Use clear, descriptive keywords relevant to the image\n4. Keep the title concise but informative\n5. Include at least 10 keywords'
                                ],
                                [
                                    'inline_data' => [
                                        'mime_type' => 'image/jpeg',
                                        'data' => $imageData
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'topK' => 32,
                        'topP' => 1,
                        'maxOutputTokens' => 2048,
                    ]
                ];

                // Log request untuk debugging
                error_log("Sending request to Gemini API for file: " . basename($imagePath));
                error_log("Request data: " . json_encode($data));

                // Kirim request ke Gemini API
                $ch = curl_init('https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=' . $this->apiKey);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json'
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_ENCODING, '');
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

                $response = curl_exec($ch);
                
                if ($response === false) {
                    $error = curl_error($ch);
                    $errno = curl_errno($ch);
                    curl_close($ch);
                    throw new Exception("CURL Error ($errno): $error");
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                // Log response untuk debugging
                error_log("Gemini API Response Code: " . $httpCode);
                error_log("Gemini API Response: " . $response);

                if ($httpCode === 0) {
                    throw new Exception("Tidak dapat terhubung ke server. Periksa koneksi internet Anda.");
                }

                if ($httpCode !== 200) {
                    $errorData = json_decode($response, true);
                    $errorMessage = isset($errorData['error']['message']) 
                        ? $errorData['error']['message'] 
                        : "Error API: HTTP Code " . $httpCode;
                    throw new Exception($errorMessage);
                }

                $result = json_decode($response, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Error parsing JSON: " . json_last_error_msg());
                }
                
                if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                    error_log("Invalid API response structure: " . json_encode($result));
                    throw new Exception("Format output tidak sesuai: " . $response);
                }

                $output = $result['candidates'][0]['content']['parts'][0]['text'];
                error_log("API Output: " . $output);
                
                // Parse hasil untuk mendapatkan title dan keywords
                if (preg_match('/Title:\s*(.*?)\s*Keywords:\s*(.*)/s', $output, $matches)) {
                    $title = trim($matches[1]);
                    $keywords = array_map('trim', explode(',', $matches[2]));
                    
                    error_log("Parsed Title: " . $title);
                    error_log("Parsed Keywords: " . implode(', ', $keywords));
                    
                    // Validasi hasil
                    if (empty($title) || empty($keywords)) {
                        error_log("Title atau keywords kosong");
                        error_log("Title: " . ($title ?: 'kosong'));
                        error_log("Keywords: " . (empty($keywords) ? 'kosong' : implode(', ', $keywords)));
                        throw new Exception("Title atau keywords kosong");
                    }

                    // Pastikan jumlah keywords sesuai
                    $keywords = $this->ensureKeywordsCount($keywords, $title);
                    error_log("Final Keywords Count: " . count($keywords));

                    return [
                        'title' => $title,
                        'keywords' => $keywords
                    ];
                }
                
                error_log("Failed to parse output: " . $output);
                throw new Exception("Format output tidak sesuai: " . $output);

            } catch (Exception $e) {
                $lastError = $e->getMessage();
                error_log("Attempt " . ($retryCount + 1) . " failed: " . $lastError);
                
                if ($retryCount < $this->maxRetries - 1) {
                    sleep($this->retryDelay);
                }
                $retryCount++;
            }
        }

        throw new Exception("Gagal generate metadata setelah " . $this->maxRetries . " percobaan. Error terakhir: " . $lastError);
    }
}
?> 