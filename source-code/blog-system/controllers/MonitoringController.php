namespace App\Controllers;  
<?php
/**
 * Monitoring Controller
 * Kontroler zarządzania logowaniem, monitoringiem i statystykami
 */

class MonitoringController
{
    private $logger;
    private $errorMonitor;
    private $appStats;
    
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->errorMonitor = ErrorMonitor::getInstance();
        $this->appStats = ApplicationStats::getInstance();
    }
    
    /**
     * Panel monitoringu
     */
    public function dashboard()
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        $data = [
            'logs' => $this->getLogStats(),
            'errors' => $this->getErrorStats(),
            'stats' => $this->getApplicationStats(),
            'recent_activity' => $this->getRecentActivity()
        ];
        
        $this->render('admin/monitoring/dashboard', [
            'data' => $data,
            'page_title' => 'Panel Monitoringu',
            'page_description' => 'Zarządzanie logowaniem, błędami i statystykami'
        ]);
    }
    
    /**
     * Panel logów
     */
    public function logs()
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        $page = $_GET['page'] ?? 1;
        $level = $_GET['level'] ?? '';
        $search = $_GET['search'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        
        $logs = $this->logger->searchLogs($search, $level, $dateFrom, $dateTo, 50);
        $logStats = $this->logger->getStats();
        
        $this->render('admin/monitoring/logs', [
            'logs' => $logs,
            'stats' => $logStats,
            'filters' => [
                'page' => $page,
                'level' => $level,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ],
            'page_title' => 'Logi Systemu',
            'page_description' => 'Przeglądanie i zarządzanie logami aplikacji'
        ]);
    }
    
    /**
     * Panel błędów
     */
    public function errors()
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        $page = $_GET['page'] ?? 1;
        $type = $_GET['type'] ?? '';
        $search = $_GET['search'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        
        $errors = $this->errorMonitor->getRecentErrors(50);
        $errorStats = $this->errorMonitor->getErrorStats();
        
        $this->render('admin/monitoring/errors', [
            'errors' => $errors,
            'stats' => $errorStats,
            'filters' => [
                'page' => $page,
                'type' => $type,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ],
            'page_title' => 'Monitoring Błędów',
            'page_description' => 'Przeglądanie i zarządzanie błędami aplikacji'
        ]);
    }
    
    /**
     * Panel statystyk
     */
    public function statistics()
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        $period = $_GET['period'] ?? '24h';
        $type = $_GET['type'] ?? 'general';
        
        $stats = $this->getStatisticsByType($type, $period);
        
        $this->render('admin/monitoring/statistics', [
            'stats' => $stats,
            'period' => $period,
            'type' => $type,
            'page_title' => 'Statystyki Aplikacji',
            'page_description' => 'Analiza wydajności i aktywności aplikacji'
        ]);
    }
    
    /**
     * Szczegóły błędu
     */
    public function errorDetails($errorId)
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT * FROM error_logs WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$errorId]);
            $error = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$error) {
                throw new Exception('Błąd nie został znaleziony');
            }
            
            $this->render('admin/monitoring/error_details', [
                'error' => $error,
                'page_title' => 'Szczegóły Błędu',
                'page_description' => 'Analiza szczegółów błędu'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get error details', ['error_id' => $errorId, 'error' => $e->getMessage()]);
            redirect('/admin/monitoring/errors');
        }
    }
    
    /**
     * Czyszczenie logów
     */
    public function clearLogs()
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $days = $_POST['days'] ?? 30;
            $type = $_POST['type'] ?? 'all';
            
            try {
                $deleted = 0;
                
                switch ($type) {
                    case 'logs':
                        $deleted = $this->logger->cleanOldLogs($days);
                        break;
                    case 'errors':
                        $deleted = $this->errorMonitor->cleanOldErrors($days);
                        break;
                    case 'stats':
                        $deleted = $this->appStats->cleanupOldData($days);
                        break;
                    case 'all':
                        $this->logger->cleanOldLogs($days);
                        $this->errorMonitor->cleanOldErrors($days);
                        $this->appStats->cleanupOldData($days);
                        $deleted = 'all';
                        break;
                }
                
                $this->logger->info('Cleared old data', [
                    'type' => $type,
                    'days' => $days,
                    'deleted' => $deleted
                ]);
                
                setFlashMessage('success', "Pomyślnie wyczyszczono stare dane ({$type})");
                
            } catch (Exception $e) {
                $this->logger->error('Failed to clear logs', ['error' => $e->getMessage()]);
                setFlashMessage('error', 'Wystąpił błąd podczas czyszczenia danych');
            }
        }
        
        redirect('/admin/monitoring/logs');
    }
    
    /**
     * Eksport logów
     */
    public function exportLogs()
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        $format = $_GET['format'] ?? 'json';
        $level = $_GET['level'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        
        $logs = $this->logger->searchLogs('', $level, $dateFrom, $dateTo, 1000);
        
        switch ($format) {
            case 'csv':
                $this->exportToCSV($logs, 'logs_' . date('Y-m-d_H-i-s') . '.csv');
                break;
            case 'json':
            default:
                $this->exportToJSON($logs, 'logs_' . date('Y-m-d_H-i-s') . '.json');
                break;
        }
    }
    
    /**
     * Generowanie raportu
     */
    public function generateReport()
    {
        if (!isAdmin()) {
            redirect('/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $reportType = $_POST['report_type'] ?? 'daily';
            $date = $_POST['date'] ?? date('Y-m-d');
            $format = $_POST['format'] ?? 'html';
            
            try {
                $report = $this->appStats->generateReport($reportType, $date);
                
                switch ($format) {
                    case 'pdf':
                        $this->exportToPDF($report, "report_{$reportType}_{$date}.pdf");
                        break;
                    case 'json':
                        $this->exportToJSON($report, "report_{$reportType}_{$date}.json");
                        break;
                    case 'html':
                    default:
                        $this->render('admin/monitoring/report', [
                            'report' => $report,
                            'page_title' => "Raport {$reportType}",
                            'page_description' => "Raport z dnia {$date}"
                        ]);
                        break;
                }
                
            } catch (Exception $e) {
                $this->logger->error('Failed to generate report', ['error' => $e->getMessage()]);
                setFlashMessage('error', 'Wystąpił błąd podczas generowania raportu');
                redirect('/admin/monitoring/statistics');
            }
        }
        
        $this->render('admin/monitoring/generate_report', [
            'page_title' => 'Generowanie Raportu',
            'page_description' => 'Tworzenie raportów z danych aplikacji'
        ]);
    }
    
    /**
     * API - Statystyki w czasie rzeczywistym
     */
    public function apiRealTimeStats()
    {
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Brak uprawnień']);
            return;
        }
        
        $stats = [
            'users_online' => $this->getUsersOnline(),
            'current_errors' => $this->getCurrentErrors(),
            'system_load' => $this->getSystemLoad(),
            'response_time' => $this->getAverageResponseTime()
        ];
        
        header('Content-Type: application/json');
        echo json_encode($stats);
    }
    
    /**
     * API - Szczegółowe statystyki
     */
    public function apiDetailedStats()
    {
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Brak uprawnień']);
            return;
        }
        
        $type = $_GET['type'] ?? 'general';
        $period = $_GET['period'] ?? '24h';
        
        $stats = $this->getStatisticsByType($type, $period);
        
        header('Content-Type: application/json');
        echo json_encode($stats);
    }
    
    /**
     * Pobranie statystyk logów
     */
    private function getLogStats()
    {
        return $this->logger->getStats();
    }
    
    /**
     * Pobranie statystyk błędów
     */
    private function getErrorStats()
    {
        return $this->errorMonitor->getErrorStats();
    }
    
    /**
     * Pobranie statystyk aplikacji
     */
    private function getApplicationStats()
    {
        return $this->appStats->getGeneralStats();
    }
    
    /**
     * Pobranie ostatniej aktywności
     */
    private function getRecentActivity()
    {
        $activity = [];
        
        // Ostatnie logi
        $activity['logs'] = $this->logger->getRecentLogs(10);
        
        // Ostatnie błędy
        $activity['errors'] = $this->errorMonitor->getRecentErrors(10);
        
        // Ostatnie wyświetlenia stron
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT page_url, COUNT(*) as views FROM page_stats 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) 
                    GROUP BY page_url ORDER BY views DESC LIMIT 5";
            $stmt = $db->query($sql);
            $activity['popular_pages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $activity['popular_pages'] = [];
        }
        
        return $activity;
    }
    
    /**
     * Pobranie statystyk według typu
     */
    private function getStatisticsByType($type, $period)
    {
        switch ($type) {
            case 'users':
                return $this->getUserStatistics($period);
            case 'performance':
                return $this->getPerformanceStatistics($period);
            case 'content':
                return $this->getContentStatistics($period);
            case 'errors':
                return $this->getErrorStatistics($period);
            case 'general':
            default:
                return $this->appStats->getGeneralStats();
        }
    }
    
    /**
     * Pobranie statystyk użytkowników
     */
    private function getUserStatistics($period)
    {
        $stats = [];
        
        try {
            $db = Database::getInstance()->getConnection();
            
            // Aktywni użytkownicy
            $sql = "SELECT COUNT(DISTINCT user_id) as active_users FROM page_stats 
                    WHERE user_id IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 1 {$period})";
            $stmt = $db->query($sql);
            $stats['active_users'] = $stmt->fetchColumn();
            
            // Nowi użytkownicy
            $sql = "SELECT COUNT(*) as new_users FROM users 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 {$period})";
            $stmt = $db->query($sql);
            $stats['new_users'] = $stmt->fetchColumn();
            
            // Top aktywni użytkownicy
            $sql = "SELECT u.username, COUNT(ps.id) as page_views 
                    FROM users u 
                    LEFT JOIN page_stats ps ON u.id = ps.user_id 
                    WHERE ps.created_at >= DATE_SUB(NOW(), INTERVAL 1 {$period})
                    GROUP BY u.id 
                    ORDER BY page_views DESC 
                    LIMIT 10";
            $stmt = $db->query($sql);
            $stats['top_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $stats['error'] = $e->getMessage();
        }
        
        return $stats;
    }
    
    /**
     * Pobranie statystyk wydajności
     */
    private function getPerformanceStatistics($period)
    {
        $stats = [];
        
        try {
            $db = Database::getInstance()->getConnection();
            
            // Średni czas odpowiedzi
            $sql = "SELECT AVG(response_time) as avg_response_time FROM page_stats 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 {$period})";
            $stmt = $db->query($sql);
            $stats['avg_response_time'] = round($stmt->fetchColumn(), 2);
            
            // Najwolniejsze strony
            $sql = "SELECT page_url, AVG(response_time) as avg_time, COUNT(*) as requests 
                    FROM page_stats 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 {$period})
                    GROUP BY page_url 
                    HAVING requests >= 5
                    ORDER BY avg_time DESC 
                    LIMIT 10";
            $stmt = $db->query($sql);
            $stats['slowest_pages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Kody odpowiedzi
            $sql = "SELECT status_code, COUNT(*) as count FROM page_stats 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 {$period})
                    GROUP BY status_code 
                    ORDER BY count DESC";
            $stmt = $db->query($sql);
            $stats['status_codes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $stats['error'] = $e->getMessage();
        }
        
        return $stats;
    }
    
    /**
     * Pobranie statystyk treści
     */
    private function getContentStatistics($period)
    {
        $stats = [];
        
        try {
            $db = Database::getInstance()->getConnection();
            
            // Najpopularniejsze posty
            $sql = "SELECT p.title, COUNT(ps.id) as views 
                    FROM posts p 
                    LEFT JOIN page_stats ps ON ps.page_url LIKE CONCAT('%', p.slug, '%')
                    WHERE ps.created_at >= DATE_SUB(NOW(), INTERVAL 1 {$period})
                    GROUP BY p.id 
                    ORDER BY views DESC 
                    LIMIT 10";
            $stmt = $db->query($sql);
            $stats['popular_posts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Statystyki komentarzy
            $sql = "SELECT COUNT(*) as total_comments FROM comments 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 {$period})";
            $stmt = $db->query($sql);
            $stats['total_comments'] = $stmt->fetchColumn();
            
        } catch (Exception $e) {
            $stats['error'] = $e->getMessage();
        }
        
        return $stats;
    }
    
    /**
     * Pobranie statystyk błędów
     */
    private function getErrorStatistics($period)
    {
        return $this->errorMonitor->getErrorStats();
    }
    
    /**
     * Pobranie użytkowników online
     */
    private function getUsersOnline()
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT COUNT(DISTINCT user_id) as online FROM page_stats 
                    WHERE user_id IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
            $stmt = $db->query($sql);
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Pobranie aktualnych błędów
     */
    private function getCurrentErrors()
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT COUNT(*) as errors FROM error_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            $stmt = $db->query($sql);
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Pobranie obciążenia systemu
     */
    private function getSystemLoad()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => round($load[0], 2),
                '5min' => round($load[1], 2),
                '15min' => round($load[2], 2)
            ];
        }
        
        return ['1min' => 0, '5min' => 0, '15min' => 0];
    }
    
    /**
     * Pobranie średniego czasu odpowiedzi
     */
    private function getAverageResponseTime()
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT AVG(response_time) as avg_time FROM page_stats 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            $stmt = $db->query($sql);
            return round($stmt->fetchColumn(), 2);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Eksport do CSV
     */
    private function exportToCSV($data, $filename)
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        if (!empty($data)) {
            // Nagłówki
            fputcsv($output, array_keys($data[0]));
            
            // Dane
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
    }
    
    /**
     * Eksport do JSON
     */
    private function exportToJSON($data, $filename)
    {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Eksport do PDF
     */
    private function exportToPDF($data, $filename)
    {
        // Implementacja eksportu do PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Tutaj można użyć biblioteki jak TCPDF lub FPDF
        echo "PDF export not implemented yet";
    }
    
    /**
     * Renderowanie widoku
     */
    private function render($view, $data = [])
    {
        extract($data);
        include APP_PATH . "/views/{$view}.php";
    }
}
