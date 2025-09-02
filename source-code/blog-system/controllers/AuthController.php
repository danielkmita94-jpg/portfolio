<?php
/**
 * Auth Controller
 * Kontroler autoryzacji użytkowników
 */

namespace App\Controllers;

use App\Models\User;
use App\Models\Category;
use App\Models\Setting;

class AuthController
{
    private $userModel;
    
    public function __construct()
    {
        $this->userModel = new User();
    }
    
    /**
     * Wyświetlenie formularza logowania
     */
    public function login()
    {
        if (isLoggedIn()) {
            redirect('/dashboard');
        }
        
        $this->render('auth/login', [
            'page_title' => 'Logowanie',
            'page_description' => 'Zaloguj się do swojego konta'
        ]);
    }
    
    /**
     * Obsługa logowania
     */
    public function authenticate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/login');
        }
        
        // Sprawdzenie rate limiting
        if (!checkRateLimit('login_' . ($_SERVER['REMOTE_ADDR'] ?? ''), 5, 300)) {
            setFlashMessage('error', 'Zbyt wiele prób logowania. Spróbuj ponownie za 5 minut.');
            redirect('/login');
        }
        
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // Walidacja
        $errors = [];
        
        if (empty($email)) {
            $errors['email'] = 'Email jest wymagany';
        }
        
        if (empty($password)) {
            $errors['password'] = 'Hasło jest wymagane';
        }
        
        if (!empty($errors)) {
            setFlashMessage('error', 'Wystąpiły błędy w formularzu.');
            $_SESSION['login_errors'] = $errors;
            $_SESSION['login_data'] = ['email' => $email];
            redirect('/login');
        }
        
        // Próba logowania
        $result = $this->userModel->login($email, $password);
        
        if ($result['success']) {
            // Ustawienie remember me
            if ($remember) {
                $this->setRememberMe($result['user']['id']);
            }
            
            set_flash_message('success', 'Zalogowano pomyślnie!');
            
            // Przekierowanie do poprzedniej strony lub dashboard
            $redirectUrl = $_SESSION['redirect_after_login'] ?? '/dashboard';
            unset($_SESSION['redirect_after_login']);
            
            redirect($redirectUrl);
        } else {
            set_flash_message('error', $result['error']);
            $_SESSION['login_data'] = ['email' => $email];
            redirect('/login');
        }
    }
    
    /**
     * Wyświetlenie formularza rejestracji
     */
    public function register()
    {
        if (isLoggedIn()) {
            redirect('/dashboard');
        }
        
        // Sprawdzenie czy rejestracja jest włączona
        $settings = new Setting();
        if (!$settings->get('users.allow_registration', true)) {
            set_flash_message('error', 'Rejestracja jest obecnie wyłączona.');
            redirect('/login');
        }
        
        $this->render('auth/register', [
            'page_title' => 'Rejestracja',
            'page_description' => 'Utwórz nowe konto'
        ]);
    }
    
    /**
     * Obsługa rejestracji
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/register');
        }
        
        // Sprawdzenie rate limiting
        if (!checkRateLimit('register_' . ($_SERVER['REMOTE_ADDR'] ?? ''), 3, 600)) {
            set_flash_message('error', 'Zbyt wiele prób rejestracji. Spróbuj ponownie za 10 minut.');
            redirect('/register');
        }
        
        $data = [
            'username' => sanitizeInput($_POST['username'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
            'last_name' => sanitizeInput($_POST['last_name'] ?? '')
        ];
        
        // Rejestracja użytkownika
        $result = $this->userModel->register($data);
        
        if ($result['success']) {
            set_flash_message('success', 'Konto zostało utworzone! Sprawdź swój email, aby je aktywować.');
            redirect('/login');
        } else {
            set_flash_message('error', 'Wystąpiły błędy podczas rejestracji.');
            $_SESSION['register_errors'] = $result['errors'];
            $_SESSION['register_data'] = $data;
            redirect('/register');
        }
    }
    
    /**
     * Wylogowanie
     */
    public function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/');
        }
        
        // Usunięcie remember me token
        $this->removeRememberMe();
        
        // Wylogowanie
        $this->userModel->logout();
        
        set_flash_message('success', 'Wylogowano pomyślnie!');
        redirect('/');
    }
    
    /**
     * Wyświetlenie formularza resetowania hasła
     */
    public function forgotPassword()
    {
        if (isLoggedIn()) {
            redirect('/dashboard');
        }
        
        $this->render('auth/forgot-password', [
            'page_title' => 'Resetowanie hasła',
            'page_description' => 'Wprowadź swój email, aby zresetować hasło'
        ]);
    }
    
    /**
     * Wysłanie linku resetującego hasło
     */
    public function sendResetLink()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/forgot-password');
        }
        
        // Sprawdzenie rate limiting
        if (!checkRateLimit('reset_' . ($_SERVER['REMOTE_ADDR'] ?? ''), 3, 600)) {
            setFlashMessage('error', 'Zbyt wiele prób resetowania hasła. Spróbuj ponownie za 10 minut.');
            redirect('/forgot-password');
        }
        
        $email = sanitizeInput($_POST['email'] ?? '');
        
        if (empty($email) || !validateEmail($email)) {
            setFlashMessage('error', 'Nieprawidłowy adres email.');
            redirect('/forgot-password');
        }
        
        $result = $this->userModel->resetPassword($email);
        
        if ($result['success']) {
            setFlashMessage('success', 'Link do resetowania hasła został wysłany na podany adres email.');
        } else {
            setFlashMessage('error', $result['error']);
        }
        
        redirect('/forgot-password');
    }
    
    /**
     * Wyświetlenie formularza nowego hasła
     */
    public function resetPassword($token)
    {
        if (isLoggedIn()) {
            redirect('/dashboard');
        }
        
        $user = $this->userModel->whereFirst('reset_token', $token);
        
        if (!$user) {
            setFlashMessage('error', 'Nieprawidłowy token resetowania hasła.');
            redirect('/login');
        }
        
        if (strtotime($user['reset_token_expires']) < time()) {
            setFlashMessage('error', 'Token resetowania hasła wygasł.');
            redirect('/login');
        }
        
        $this->render('auth/reset-password', [
            'page_title' => 'Nowe hasło',
            'page_description' => 'Ustaw nowe hasło',
            'token' => $token
        ]);
    }
    
    /**
     * Aktualizacja hasła
     */
    public function updatePassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/login');
        }
        
        $token = sanitizeInput($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        if (empty($token)) {
            setFlashMessage('error', 'Token jest wymagany.');
            redirect('/login');
        }
        
        if (empty($password)) {
            setFlashMessage('error', 'Nowe hasło jest wymagane.');
            redirect('/reset-password/' . $token);
        }
        
        if ($password !== $password_confirm) {
            setFlashMessage('error', 'Hasła nie są identyczne.');
            redirect('/reset-password/' . $token);
        }
        
        if (!validatePassword($password)) {
            setFlashMessage('error', 'Hasło nie spełnia wymagań bezpieczeństwa.');
            redirect('/reset-password/' . $token);
        }
        
        $result = $this->userModel->setNewPassword($token, $password);
        
        if ($result['success']) {
            setFlashMessage('success', 'Hasło zostało zmienione pomyślnie! Możesz się teraz zalogować.');
            redirect('/login');
        } else {
            setFlashMessage('error', $result['error']);
            redirect('/reset-password/' . $token);
        }
    }
    
    /**
     * Weryfikacja emaila
     */
    public function verifyEmail($token)
    {
        $result = $this->userModel->verifyEmail($token);
        
        if ($result['success']) {
            setFlashMessage('success', 'Email został zweryfikowany pomyślnie! Możesz się teraz zalogować.');
        } else {
            setFlashMessage('error', $result['error']);
        }
        
        redirect('/login');
    }
    
    /**
     * Ustawienie remember me
     */
    private function setRememberMe($userId)
    {
        $token = generateToken(64);
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Zapisanie tokenu w bazie danych
        $this->userModel->update($userId, [
            'remember_token' => $token,
            'remember_token_expires' => $expires
        ]);
        
        // Ustawienie ciasteczka
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
    }
    
    /**
     * Usunięcie remember me
     */
    private function removeRememberMe()
    {
        if (isset($_COOKIE['remember_token'])) {
            // Usunięcie tokenu z bazy danych
            $user = $this->userModel->whereFirst('remember_token', $_COOKIE['remember_token']);
            if ($user) {
                $this->userModel->update($user['id'], [
                    'remember_token' => null,
                    'remember_token_expires' => null
                ]);
            }
            
            // Usunięcie ciasteczka
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
    
    /**
     * Sprawdzenie remember me token
     */
    public function checkRememberMe()
    {
        if (isset($_COOKIE['remember_token'])) {
            $user = $this->userModel->whereFirst('remember_token', $_COOKIE['remember_token']);
            
            if ($user && strtotime($user['remember_token_expires']) > time()) {
                // Automatyczne logowanie
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                
                return true;
            } else {
                // Usunięcie nieprawidłowego tokenu
                $this->removeRememberMe();
            }
        }
        
        return false;
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
        
        // Pobranie błędów formularzy
        $loginErrors = $_SESSION['login_errors'] ?? [];
        $loginData = $_SESSION['login_data'] ?? [];
        $registerErrors = $_SESSION['register_errors'] ?? [];
        $registerData = $_SESSION['register_data'] ?? [];
        
        unset($_SESSION['login_errors'], $_SESSION['login_data'], 
              $_SESSION['register_errors'], $_SESSION['register_data']);
        
        // Pobranie kategorii dla menu
        $categories = (new Category())->getActiveCategories();
        
        // Pobranie ustawień systemu
        $settings = (new Setting())->getPublicSettings();
        
        // Dołączenie layoutu
        include APP_PATH . "/views/layouts/header.php";
        include APP_PATH . "/views/{$view}.php";
        include APP_PATH . "/views/layouts/footer.php";
    }
}
