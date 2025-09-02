namespace App\Controllers;  
<?php
/**
 * Performance Controller
 * Kontroler zarządzania wydajnością aplikacji
 */

class PerformanceController
{
    private $cache;
    private $dbOptimizer;
    private $imageOptimizer;
    
    public function __construct()
    {
        $this->cache = Cache::getInstance();
        $this->dbOptimizer = new DatabaseOptimizer();
        $this->imageOptimizer = new ImageOptimizer();
    }
    
    /**
     * Panel wydajności
     */
    public function dashboard()
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        $stats = [
            'cache' => $this->cache->getStats(),
            'database' => $this->dbOptimizer->getPerformanceStats(),
            'images' => $this->imageOptimizer->getOptimizationStats(),
            'system' => $this->getSystemStats()
        ];
        
        $this->render('admin/performance/dashboard', [
            'stats' => $stats,
            'page_title' => 'Panel Wydajności',
            'page_description' => 'Zarządzanie wydajnością aplikacji'
        ]);
    }
    
    /**
     * Zarządzanie cache
     */
    public function cache()
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'clear_all':
                    $this->cache->clear();
                    setFlashMessage('success', 'Cache został wyczyszczony');
                    break;
                    
                case 'clear_pattern':
                    $pattern = $_POST['pattern'] ?? '';
                    if ($pattern) {
                        $deleted = $this->cache->deletePattern($pattern);
                        setFlashMessage('success', "Usunięto {$deleted} kluczy z cache");
                    }
                    break;
                    
                case 'optimize':
                    $this->optimizeCache();
                    setFlashMessage('success', 'Cache został zoptymalizowany');
                    break;
            }
            
            redirect('/admin/performance/cache');
        }
        
        $cacheStats = $this->cache->getStats();
        $cacheKeys = $this->getCacheKeys();
        
        $this->render('admin/performance/cache', [
            'stats' => $cacheStats,
            'keys' => $cacheKeys,
            'page_title' => 'Zarządzanie Cache',
            'page_description' => 'Zarządzanie systemem cache'
        ]);
    }
    
    /**
     * Optymalizacja bazy danych
     */
    public function database()
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'create_indexes':
                    $result = $this->dbOptimizer->createIndexes();
                    setFlashMessage('success', "Utworzono {$result['created']} indeksów");
                    break;
                    
                case 'optimize_tables':
                    $result = $this->dbOptimizer->optimizeTables();
                    setFlashMessage('success', "Zoptymalizowano {$result['optimized']} tabel");
                    break;
                    
                case 'cleanup_data':
                    $days = (int) ($_POST['days'] ?? 30);
                    $result = $this->dbOptimizer->cleanupOldData($days);
                    setFlashMessage('success', "Usunięto {$result['cleaned']} rekordów");
                    break;
                    
                case 'backup':
                    try {
                        $backupPath = $this->dbOptimizer->backupDatabase();
                        setFlashMessage('success', "Backup utworzony: {$backupPath}");
                    } catch (Exception $e) {
                        setFlashMessage('error', "Błąd tworzenia backupu: " . $e->getMessage());
                    }
                    break;
            }
            
            redirect('/admin/performance/database');
        }
        
        $stats = $this->dbOptimizer->getPerformanceStats();
        $slowQueries = $this->dbOptimizer->analyzeSlowQueries(10);
        $recommendations = $this->dbOptimizer->getOptimizationRecommendations();
        
        $this->render('admin/performance/database', [
            'stats' => $stats,
            'slow_queries' => $slowQueries,
            'recommendations' => $recommendations,
            'page_title' => 'Optymalizacja Bazy Danych',
            'page_description' => 'Zarządzanie bazą danych'
        ]);
    }
    
    /**
     * Optymalizacja obrazów
     */
    public function images()
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'optimize_directory':
                    $directory = $_POST['directory'] ?? UPLOADS_PATH;
                    $recursive = isset($_POST['recursive']);
                    
                    try {
                        $optimized = $this->imageOptimizer->optimizeDirectory($directory, $recursive);
                        setFlashMessage('success', "Zoptymalizowano {$optimized} obrazów");
                    } catch (Exception $e) {
                        setFlashMessage('error', "Błąd optymalizacji: " . $e->getMessage());
                    }
                    break;
                    
                case 'clear_image_cache':
                    $deleted = $this->imageOptimizer->clearImageCache();
                    setFlashMessage('success', "Usunięto {$deleted} kluczy cache obrazów");
                    break;
                    
                case 'generate_thumbnails':
                    $directory = $_POST['directory'] ?? UPLOADS_PATH;
                    $this->generateThumbnails($directory);
                    setFlashMessage('success', 'Thumbnails zostały wygenerowane');
                    break;
            }
            
            redirect('/admin/performance/images');
        }
        
        $stats = $this->imageOptimizer->getOptimizationStats();
        $imageInfo = $this->getImageInfo();
        
        $this->render('admin/performance/images', [
            'stats' => $stats,
            'image_info' => $imageInfo,
            'page_title' => 'Optymalizacja Obrazów',
            'page_description' => 'Zarządzanie obrazami'
        ]);
    }
    
    /**
     * API dla statystyk wydajności
     */
    public function apiStats()
    {
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        $stats = [
            'cache' => $this->cache->getStats(),
            'database' => $this->dbOptimizer->getPerformanceStats(),
            'images' => $this->imageOptimizer->getOptimizationStats(),
            'system' => $this->getSystemStats(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        header('Content-Type: application/json');
        echo json_encode($stats);
    }
    
    /**
     * Test wydajności
     */
    public function benchmark()
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        $results = [
            'database' => $this->benchmarkDatabase(),
            'cache' => $this->benchmarkCache(),
            'images' => $this->benchmarkImages()
        ];
        
        $this->render('admin/performance/benchmark', [
            'results' => $results,
            'page_title' => 'Test Wydajności',
            'page_description' => 'Benchmark aplikacji'
        ]);
    }
    
    /**
     * Optymalizacja cache
     */
    private function optimizeCache()
    {
        // Usunięcie wygasłych kluczy
        $stats = $this->cache->getStats();
        
        // Kompresja cache (jeśli obsługiwane)
        if (method_exists($this->cache, 'compress')) {
            $this->cache->compress();
        }
        
        return true;
    }
    
    /**
     * Pobranie kluczy cache
     */
    private function getCacheKeys()
    {
        // Implementacja zależy od drivera cache
        $keys = [];
        
        if (method_exists($this->cache, 'getKeys')) {
            $keys = $this->cache->getKeys();
        }
        
        return array_slice($keys, 0, 100); // Limit do 100 kluczy
    }
    
    /**
     * Pobranie informacji o obrazach
     */
    private function getImageInfo()
    {
        $uploadPath = UPLOADS_PATH;
        $images = [];
        
        if (is_dir($uploadPath)) {
            $files = glob($uploadPath . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
            
            foreach (array_slice($files, 0, 20) as $file) {
                $info = $this->imageOptimizer->getImageInfo($file);
                if ($info) {
                    $images[] = [
                        'path' => basename($file),
                        'size' => $info['size'],
                        'width' => $info['width'],
                        'height' => $info['height'],
                        'type' => $info['mime']
                    ];
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Generowanie thumbnailów
     */
    private function generateThumbnails($directory)
    {
        $files = glob($directory . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        $generated = 0;
        
        foreach ($files as $file) {
            try {
                $this->imageOptimizer->createThumbnail($file);
                $generated++;
            } catch (Exception $e) {
                error_log("Failed to generate thumbnail for {$file}: " . $e->getMessage());
            }
        }
        
        return $generated;
    }
    
    /**
     * Pobranie statystyk systemu
     */
    private function getSystemStats()
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'disk_free_space' => disk_free_space(ROOT_PATH),
            'disk_total_space' => disk_total_space(ROOT_PATH)
        ];
    }
    
    /**
     * Benchmark bazy danych
     */
    private function benchmarkDatabase()
    {
        $results = [];
        
        // Test prostego zapytania
        $start = microtime(true);
        $postModel = new Post();
        $postModel->count('status = ?', ['published']);
        $results['simple_query'] = microtime(true) - $start;
        
        // Test złożonego zapytania
        $start = microtime(true);
        $postModel->getFeaturedPosts(10);
        $results['complex_query'] = microtime(true) - $start;
        
        // Test zapytania z cache
        $start = microtime(true);
        $postModel->getFeaturedPosts(10);
        $results['cached_query'] = microtime(true) - $start;
        
        return $results;
    }
    
    /**
     * Benchmark cache
     */
    private function benchmarkCache()
    {
        $results = [];
        
        // Test zapisu
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $this->cache->set("test_key_{$i}", "test_value_{$i}");
        }
        $results['write_100_keys'] = microtime(true) - $start;
        
        // Test odczytu
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $this->cache->get("test_key_{$i}");
        }
        $results['read_100_keys'] = microtime(true) - $start;
        
        // Czyszczenie testowych kluczy
        for ($i = 0; $i < 100; $i++) {
            $this->cache->delete("test_key_{$i}");
        }
        
        return $results;
    }
    
    /**
     * Benchmark obrazów
     */
    private function benchmarkImages()
    {
        $results = [];
        
        // Test optymalizacji obrazu
        $testImage = UPLOADS_PATH . '/test.jpg';
        if (file_exists($testImage)) {
            $start = microtime(true);
            $this->imageOptimizer->optimize($testImage);
            $results['optimize_image'] = microtime(true) - $start;
            
            $start = microtime(true);
            $this->imageOptimizer->createThumbnail($testImage);
            $results['create_thumbnail'] = microtime(true) - $start;
        }
        
        return $results;
    }
    
    /**
     * Renderowanie widoku
     */
    private function render($view, $data = [])
    {
        extract($data);
        $viewPath = APP_PATH . "/views/{$view}.php";
        
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            throw new Exception("View not found: {$view}");
        }
    }
}
