<?php
/**
 * Dashboard Controller
 * Kontroler panelu użytkownika
 */

namespace App\Controllers;

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;

class DashboardController
{
    private $userModel;
    private $postModel;
    private $commentModel;
    
    public function __construct()
    {
        $this->userModel = new User();
        $this->postModel = new Post();
        $this->commentModel = new Comment();
    }
    
    /**
     * Strona główna dashboardu
     */
    public function index()
    {
        try {
            $userId = $_SESSION['user_id'];
            $user = $this->userModel->find($userId);
            
            if (!$user) {
                set_flash_message('error', 'Użytkownik nie istnieje.');
                redirect('/logout');
                return;
            }
            
            // Statystyki użytkownika
            $stats = $this->getUserStats($userId);
            
            // Ostatnie aktywności
            $recentPosts = $this->postModel->where('user_id', $userId, '=', 'created_at DESC', 5);
            $recentComments = $this->commentModel->where('user_id', $userId, '=', 'created_at DESC', 5);
            
            // Powiadomienia
            $notifications = []; // Tymczasowo puste
            
            // Renderowanie widoku
            $this->render('dashboard/index', [
                'user' => $user,
                'stats' => $stats,
                'recentPosts' => $recentPosts,
                'recentComments' => $recentComments,
                'notifications' => $notifications,
                'page_title' => 'Panel użytkownika',
                'page_description' => 'Zarządzaj swoim kontem'
            ]);
            
        } catch (Exception $e) {
            log_error('DashboardController::index error: ' . $e->getMessage());
            // renderError(); // Tymczasowo zakomentowane
        }
    }
    
    /**
     * Statystyki użytkownika
     */
    public function stats()
    {
        try {
            $userId = $_SESSION['user_id'];
            
            // Statystyki postów
            $postStats = ['total' => $this->postModel->count('user_id = ?', [$userId])];
            
            // Statystyki komentarzy
            $commentStats = ['total' => $this->commentModel->count('user_id = ?', [$userId])];
            
            // Statystyki polubień
            $likeStats = ['total' => 0]; // Tymczasowo
            
            // Statystyki wizualizacji
            $viewStats = ['total' => 0]; // Tymczasowo
            
            $data = [
                'postStats' => $postStats,
                'commentStats' => $commentStats,
                'likeStats' => $likeStats,
                'viewStats' => $viewStats
            ];
            
            if (is_ajax()) {
                json_response($data);
            } else {
                require_once APP_PATH . '/views/dashboard/stats.php';
            }
            
        } catch (Exception $e) {
            log_error('DashboardController::stats error: ' . $e->getMessage());
            // renderError(); // Tymczasowo zakomentowane
        }
    }
    
    /**
     * Ustawienia użytkownika
     */
    public function settings()
    {
        try {
            $userId = $_SESSION['user_id'];
            $user = $this->userModel->find($userId);
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->updateUserSettings($userId);
            }
            
            $data = ['user' => $user];
            require_once APP_PATH . '/views/dashboard/settings.php';
            
        } catch (Exception $e) {
            log_error('DashboardController::settings error: ' . $e->getMessage());
            // renderError(); // Tymczasowo zakomentowane
        }
    }
    
    /**
     * Powiadomienia użytkownika
     */
    public function notifications()
    {
        try {
            $userId = $_SESSION['user_id'];
            $page = $_GET['page'] ?? 1;
            
            $notifications = []; // Tymczasowo puste
            $totalNotifications = 0;
            
            $pagination = paginate($totalNotifications, 20, $page, "/dashboard/notifications?page={page}");
            
            $data = [
                'notifications' => $notifications,
                'pagination' => $pagination
            ];
            
            require_once APP_PATH . '/views/dashboard/notifications.php';
            
        } catch (Exception $e) {
            log_error('DashboardController::notifications error: ' . $e->getMessage());
            // renderError(); // Tymczasowo zakomentowane
        }
    }
    
    /**
     * Oznaczenie powiadomienia jako przeczytane
     */
    public function markNotificationRead($notificationId)
    {
        try {
            if (!is_ajax()) {
                json_response(['error' => 'Nieprawidłowe żądanie'], 400);
                return;
            }
            
            $userId = $_SESSION['user_id'];
            
            // Sprawdzenie czy powiadomienie należy do użytkownika
            // $notification = $this->userModel->getNotification($notificationId, $userId);
            // if (!$notification) {
            //     json_response(['error' => 'Powiadomienie nie istnieje'], 404);
            //     return;
            // }
            
            // Oznaczenie jako przeczytane
            // $this->userModel->markNotificationRead($notificationId);
            
            json_response(['success' => true]);
            
        } catch (Exception $e) {
            log_error('DashboardController::markNotificationRead error: ' . $e->getMessage());
            json_response(['error' => 'Wystąpił błąd'], 500);
        }
    }
    
    /**
     * Pobranie statystyk użytkownika
     */
    private function getUserStats($userId)
    {
        return [
            'posts' => $this->postModel->count('user_id = ?', [$userId]),
            'comments' => $this->commentModel->count('user_id = ?', [$userId]),
            'likes' => 0, // Tymczasowo
            'views' => 0  // Tymczasowo
        ];
    }
    
    /**
     * Pobranie powiadomień użytkownika
     */
    private function getUserNotifications($userId, $page = 1, $limit = 10)
    {
        return []; // Tymczasowo puste
    }
    
    /**
     * Liczba powiadomień użytkownika
     */
    private function getUserNotificationsCount($userId)
    {
        return 0; // Tymczasowo
    }
    
    /**
     * Aktualizacja ustawień użytkownika
     */
    private function updateUserSettings($userId)
    {
        // Walidacja CSRF
        validateCSRFToken($_POST['_token'] ?? '');
        
        $settings = [
            'email_notifications' => isset($_POST['email_notifications']),
            'comment_notifications' => isset($_POST['comment_notifications']),
            'like_notifications' => isset($_POST['like_notifications']),
            'newsletter_subscription' => isset($_POST['newsletter_subscription'])
        ];
        
        // $this->userModel->updateSettings($userId, $settings);
        set_flash_message('success', 'Ustawienia zostały zaktualizowane.');
    }
    
    /**
     * Renderowanie widoku
     */
    private function render($view, $data = [])
    {
        // Ekstrakcja danych do zmiennych
        extract($data);
        
        // Pobranie flash messages
        $flashMessages = getFlashMessages();
        
        // Pobranie kategorii dla menu
        $categories = (new \App\Models\Category())->getActiveCategories();
        
        // Pobranie ustawień systemu
        $settings = (new \App\Models\Setting())->getPublicSettings();
        
        // Dołączenie layoutu
        include APP_PATH . "/views/layouts/header.php";
        include APP_PATH . "/views/{$view}.php";
        include APP_PATH . "/views/layouts/footer.php";
    }
}
