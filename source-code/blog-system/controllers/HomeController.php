<?php
/**
 * Home Controller
 * Kontroler strony głównej i stron publicznych
 */

namespace App\Controllers;

class HomeController
{
    private $postModel;
    private $categoryModel;
    private $userModel;
    
    public function __construct()
    {
        $this->postModel = new \App\Models\Post();
        $this->categoryModel = new \App\Models\Category();
        $this->userModel = new \App\Models\User();
    }
    
    /**
     * Strona główna
     */
    public function index()
    {
        // Pobranie najnowszych postów
        $featuredPosts = $this->postModel->getFeaturedPosts(3);
        $latestPosts = $this->postModel->getLatestPosts(6);
        $popularPosts = $this->postModel->getPopularPosts(6);
        
        // Pobranie kategorii
        $categories = $this->categoryModel->getActiveCategories();
        
        // Statystyki
        $stats = [
            'total_posts' => $this->postModel->count('status = ?', ['published']),
            'total_users' => $this->userModel->count('is_active = ?', [true]),
            'total_comments' => (new \App\Models\Comment())->count('status = ?', ['approved'])
        ];
        
        // Renderowanie widoku
        $this->render('home/index', [
            'featured_posts' => $featuredPosts,
            'latest_posts' => $latestPosts,
            'popular_posts' => $popularPosts,
            'categories' => $categories,
            'stats' => $stats
        ]);
    }
    
    /**
     * Strona "O nas"
     */
    public function about()
    {
        $this->render('home/about', [
            'page_title' => 'O nas',
            'page_description' => 'Poznaj nasz zespół i misję'
        ]);
    }
    
    /**
     * Strona kontaktowa
     */
    public function contact()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleContactForm();
        }
        
        $this->render('home/contact', [
            'page_title' => 'Kontakt',
            'page_description' => 'Skontaktuj się z nami'
        ]);
    }
    
    /**
     * Obsługa formularza kontaktowego
     */
    private function handleContactForm()
    {
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $subject = sanitizeInput($_POST['subject'] ?? '');
        $message = sanitizeInput($_POST['message'] ?? '');
        
        $errors = [];
        
        // Walidacja
        if (empty($name)) {
            $errors['name'] = 'Imię jest wymagane';
        }
        
        if (empty($email) || !validateEmail($email)) {
            $errors['email'] = 'Nieprawidłowy adres email';
        }
        
        if (empty($subject)) {
            $errors['subject'] = 'Temat jest wymagany';
        }
        
        if (empty($message)) {
            $errors['message'] = 'Wiadomość jest wymagana';
        }
        
        if (empty($errors)) {
            // Tutaj można dodać wysyłanie emaila
            // $this->sendContactEmail($name, $email, $subject, $message);
            
            set_flash_message('success', 'Dziękujemy za wiadomość! Odpowiemy najszybciej jak to możliwe.');
            redirect('/contact');
        } else {
            set_flash_message('error', 'Wystąpiły błędy w formularzu.');
            $_SESSION['contact_errors'] = $errors;
            $_SESSION['contact_data'] = $_POST;
            redirect('/contact');
        }
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
        
        // Pobranie błędów formularza kontaktowego
        $contactErrors = $_SESSION['contact_errors'] ?? [];
        $contactData = $_SESSION['contact_data'] ?? [];
        unset($_SESSION['contact_errors'], $_SESSION['contact_data']);
        
        // Pobranie kategorii dla menu
        $categories = $this->categoryModel->getActiveCategories();
        
        // Pobranie ustawień systemu
        $settings = (new \App\Models\Setting())->getPublicSettings();
        
        // Dołączenie layoutu
        include APP_PATH . "/views/layouts/header.php";
        include APP_PATH . "/views/{$view}.php";
        include APP_PATH . "/views/layouts/footer.php";
    }
}












