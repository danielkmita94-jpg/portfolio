<?php
/**
 * User Model
 * Model użytkownika z funkcjonalnościami autoryzacji
 */

namespace App\Models;

class User extends Model
{
    protected $table = 'users';
    protected $fillable = [
        'username', 'email', 'password', 'first_name', 'last_name', 
        'bio', 'avatar', 'role', 'is_active', 'email_verified',
        'verification_token', 'reset_token', 'reset_token_expires'
    ];
    protected $hidden = ['password', 'verification_token', 'reset_token'];
    protected $casts = [
        'is_active' => 'boolean',
        'email_verified' => 'boolean',
        'reset_token_expires' => 'datetime'
    ];
    
    /**
     * Rejestracja nowego użytkownika
     */
    public function register($data)
    {
        // Walidacja danych
        $errors = $this->validateRegistration($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        // Sprawdzenie czy użytkownik już istnieje
        if ($this->whereFirst('email', $data['email'])) {
            return ['success' => false, 'errors' => ['email' => 'Ten email jest już zajęty']];
        }
        
        if ($this->whereFirst('username', $data['username'])) {
            return ['success' => false, 'errors' => ['username' => 'Ta nazwa użytkownika jest już zajęta']];
        }
        
        // Hashowanie hasła
        $data['password'] = hashPassword($data['password']);
        
        // Generowanie tokenu weryfikacyjnego
        $data['verification_token'] = generateToken();
        
        // Domyślna rola
        $data['role'] = 'user';
        $data['is_active'] = true;
        $data['email_verified'] = false;
        
        // Zapisanie użytkownika
        $userId = $this->create($data);
        
        if ($userId) {
            // Wysłanie emaila weryfikacyjnego
            $this->sendVerificationEmail($data['email'], $data['verification_token']);
            
            return ['success' => true, 'user_id' => $userId];
        }
        
        return ['success' => false, 'errors' => ['general' => 'Błąd podczas rejestracji']];
    }
    
    /**
     * Logowanie użytkownika
     */
    public function login($email, $password)
    {
        $user = $this->whereFirst('email', $email);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Nieprawidłowy email lub hasło'];
        }
        
        if (!$user['is_active']) {
            return ['success' => false, 'error' => 'Konto jest nieaktywne'];
        }
        
        if (!verifyPassword($password, $user['password'])) {
            return ['success' => false, 'error' => 'Nieprawidłowy email lub hasło'];
        }
        
        // Aktualizacja ostatniego logowania
        $this->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
        
        // Ustawienie sesji
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * Wylogowanie użytkownika
     */
    public function logout()
    {
        session_destroy();
        return true;
    }
    
    /**
     * Zmiana hasła
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        $user = $this->find($userId);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Użytkownik nie istnieje'];
        }
        
        if (!verifyPassword($currentPassword, $user['password'])) {
            return ['success' => false, 'error' => 'Nieprawidłowe obecne hasło'];
        }
        
        if (!validatePassword($newPassword)) {
            return ['success' => false, 'error' => 'Nowe hasło nie spełnia wymagań'];
        }
        
        $hashedPassword = hashPassword($newPassword);
        $this->update($userId, ['password' => $hashedPassword]);
        
        return ['success' => true];
    }
    
    /**
     * Resetowanie hasła
     */
    public function resetPassword($email)
    {
        $user = $this->whereFirst('email', $email);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Email nie istnieje w systemie'];
        }
        
        $resetToken = generateToken();
        $resetTokenExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $this->update($user['id'], [
            'reset_token' => $resetToken,
            'reset_token_expires' => $resetTokenExpires
        ]);
        
        // Wysłanie emaila resetującego
        $this->sendResetEmail($email, $resetToken);
        
        return ['success' => true];
    }
    
    /**
     * Ustawienie nowego hasła po resetowaniu
     */
    public function setNewPassword($token, $newPassword)
    {
        $user = $this->whereFirst('reset_token', $token);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Nieprawidłowy token resetowania'];
        }
        
        if (strtotime($user['reset_token_expires']) < time()) {
            return ['success' => false, 'error' => 'Token resetowania wygasł'];
        }
        
        if (!validatePassword($newPassword)) {
            return ['success' => false, 'error' => 'Nowe hasło nie spełnia wymagań'];
        }
        
        $hashedPassword = hashPassword($newPassword);
        $this->update($user['id'], [
            'password' => $hashedPassword,
            'reset_token' => null,
            'reset_token_expires' => null
        ]);
        
        return ['success' => true];
    }
    
    /**
     * Weryfikacja emaila
     */
    public function verifyEmail($token)
    {
        $user = $this->whereFirst('verification_token', $token);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Nieprawidłowy token weryfikacji'];
        }
        
        $this->update($user['id'], [
            'email_verified' => true,
            'verification_token' => null
        ]);
        
        return ['success' => true];
    }
    
    /**
     * Pobranie postów użytkownika
     */
    public function getPosts($userId, $page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug,
                       COUNT(cm.id) as comment_count, COUNT(pl.user_id) as like_count
                FROM posts p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN comments cm ON p.id = cm.post_id AND cm.status = 'approved'
                LEFT JOIN post_likes pl ON p.id = pl.post_id
                WHERE p.user_id = ?
                GROUP BY p.id
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?";
        
        $posts = $this->raw($sql, [$userId, $perPage, $offset]);
        
        $total = $this->count('user_id = ?', [$userId]);
        
        return [
            'posts' => $posts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
        ];
    }
    
    /**
     * Pobranie komentarzy użytkownika
     */
    public function getComments($userId, $page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT c.*, p.title as post_title, p.slug as post_slug
                FROM comments c
                JOIN posts p ON c.post_id = p.id
                WHERE c.user_id = ?
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?";
        
        $comments = $this->raw($sql, [$userId, $perPage, $offset]);
        
        $total = $this->count('user_id = ?', [$userId]);
        
        return [
            'comments' => $comments,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
        ];
    }
    
    /**
     * Pobranie polubionych postów użytkownika
     */
    public function getLikedPosts($userId, $page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug,
                       u.username as author_username,
                       COUNT(cm.id) as comment_count, COUNT(pl2.user_id) as like_count
                FROM post_likes pl
                JOIN posts p ON pl.post_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN comments cm ON p.id = cm.post_id AND cm.status = 'approved'
                LEFT JOIN post_likes pl2 ON p.id = pl2.post_id
                WHERE pl.user_id = ? AND p.status = 'published'
                GROUP BY p.id
                ORDER BY pl.created_at DESC
                LIMIT ? OFFSET ?";
        
        $posts = $this->raw($sql, [$userId, $perPage, $offset]);
        
        $total = $this->count('user_id = ?', [$userId], 'post_likes');
        
        return [
            'posts' => $posts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]
        ];
    }
    
    /**
     * Aktualizacja profilu użytkownika
     */
    public function updateProfile($userId, $data)
    {
        $allowedFields = ['first_name', 'last_name', 'bio', 'avatar'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        if (empty($updateData)) {
            return ['success' => false, 'error' => 'Brak danych do aktualizacji'];
        }
        
        $this->update($userId, $updateData);
        
        return ['success' => true];
    }
    
    /**
     * Walidacja danych rejestracji
     */
    private function validateRegistration($data)
    {
        $errors = [];
        
        if (empty($data['username']) || strlen($data['username']) < 3) {
            $errors['username'] = 'Nazwa użytkownika musi mieć minimum 3 znaki';
        }
        
        if (empty($data['email']) || !validateEmail($data['email'])) {
            $errors['email'] = 'Nieprawidłowy adres email';
        }
        
        if (empty($data['password']) || !validatePassword($data['password'])) {
            $errors['password'] = 'Hasło musi mieć minimum 8 znaków, zawierać wielką literę, małą literę i cyfrę';
        }
        
        if (empty($data['password_confirm']) || $data['password'] !== $data['password_confirm']) {
            $errors['password_confirm'] = 'Hasła nie są identyczne';
        }
        
        return $errors;
    }
    
    /**
     * Wysłanie emaila weryfikacyjnego
     */
    private function sendVerificationEmail($email, $token)
    {
        $subject = 'Weryfikacja adresu email - ' . SITE_NAME;
        $verificationUrl = BASE_URL . '/verify-email/' . $token;
        
        $message = "
        <h2>Witaj w " . SITE_NAME . "!</h2>
        <p>Dziękujemy za rejestrację. Aby aktywować swoje konto, kliknij w poniższy link:</p>
        <p><a href='{$verificationUrl}'>{$verificationUrl}</a></p>
        <p>Link jest ważny przez 24 godziny.</p>
        <p>Jeśli nie zakładałeś konta, zignoruj ten email.</p>
        ";
        
        // Tutaj można dodać wysyłanie emaila przez SMTP
        // mail($email, $subject, $message, "From: " . FROM_EMAIL);
    }
    
    /**
     * Wysłanie emaila resetującego hasło
     */
    private function sendResetEmail($email, $token)
    {
        $subject = 'Resetowanie hasła - ' . SITE_NAME;
        $resetUrl = BASE_URL . '/reset-password/' . $token;
        
        $message = "
        <h2>Resetowanie hasła</h2>
        <p>Otrzymaliśmy prośbę o resetowanie hasła dla Twojego konta.</p>
        <p>Kliknij w poniższy link, aby ustawić nowe hasło:</p>
        <p><a href='{$resetUrl}'>{$resetUrl}</a></p>
        <p>Link jest ważny przez 1 godzinę.</p>
        <p>Jeśli nie prosiłeś o resetowanie hasła, zignoruj ten email.</p>
        ";
        
        // Tutaj można dodać wysyłanie emaila przez SMTP
        // mail($email, $subject, $message, "From: " . FROM_EMAIL);
    }
    
    /**
     * Pobranie statystyk użytkownika
     */
    public function getStats($userId)
    {
        $stats = [];
        
        // Liczba postów
        $stats['posts_count'] = $this->count('user_id = ?', [$userId], 'posts');
        
        // Liczba komentarzy
        $stats['comments_count'] = $this->count('user_id = ?', [$userId], 'comments');
        
        // Liczba polubionych postów
        $stats['liked_posts_count'] = $this->count('user_id = ?', [$userId], 'post_likes');
        
        // Suma wyświetleń postów
        $sql = "SELECT SUM(view_count) as total_views FROM posts WHERE user_id = ?";
        $result = $this->raw($sql, [$userId]);
        $stats['total_views'] = $result[0]['total_views'] ?? 0;
        
        // Suma polubień postów
        $sql = "SELECT COUNT(pl.user_id) as total_likes 
                FROM posts p 
                LEFT JOIN post_likes pl ON p.id = pl.post_id 
                WHERE p.user_id = ?";
        $result = $this->raw($sql, [$userId]);
        $stats['total_likes'] = $result[0]['total_likes'] ?? 0;
        
        return $stats;
    }
    
    /**
     * Sprawdzenie czy użytkownik polubił post
     */
    public function hasLikedPost($userId, $postId)
    {
        $sql = "SELECT COUNT(*) FROM post_likes WHERE user_id = ? AND post_id = ?";
        return $this->raw($sql, [$userId, $postId])[0]['COUNT(*)'] > 0;
    }
    
    /**
     * Dodanie/Usunięcie polubienia posta
     */
    public function toggleLike($userId, $postId)
    {
        if ($this->hasLikedPost($userId, $postId)) {
            // Usunięcie polubienia
            $sql = "DELETE FROM post_likes WHERE user_id = ? AND post_id = ?";
            $this->raw($sql, [$userId, $postId]);
            return ['action' => 'unliked', 'success' => true];
        } else {
            // Dodanie polubienia
            $sql = "INSERT INTO post_likes (user_id, post_id) VALUES (?, ?)";
            $this->raw($sql, [$userId, $postId]);
            return ['action' => 'liked', 'success' => true];
        }
    }
}
