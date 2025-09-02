<?php
/**
 * Comment Model
 * Model komentarzy blogowych
 */

namespace App\Models;

class Comment extends Model
{
    protected $table = 'comments';
    protected $fillable = [
        'post_id', 'user_id', 'parent_id', 'author_name', 'author_email',
        'author_website', 'content', 'status', 'ip_address', 'user_agent'
    ];
    protected $casts = ['parent_id' => 'integer'];
    
    /**
     * Pobranie zatwierdzonych komentarzy dla posta
     */
    public function getApprovedComments($postId, $page = 1, $perPage = 20)
    {
        $where = 'post_id = ? AND status = ?';
        $params = [$postId, 'approved'];
        
        return $this->paginate($page, $perPage, $where, $params);
    }
    
    /**
     * Pobranie komentarzy z hierarchią (nested comments)
     */
    public function getCommentsHierarchy($postId)
    {
        $sql = "SELECT c.*, u.username, u.first_name, u.last_name, u.avatar
                FROM comments c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.post_id = ? AND c.status = 'approved'
                ORDER BY c.parent_id ASC, c.created_at ASC";
        
        $comments = $this->raw($sql, [$postId]);
        
        // Organizacja komentarzy w hierarchię
        $hierarchy = [];
        $children = [];
        
        foreach ($comments as $comment) {
            if ($comment['parent_id'] === null) {
                $hierarchy[] = $comment;
            } else {
                if (!isset($children[$comment['parent_id']])) {
                    $children[$comment['parent_id']] = [];
                }
                $children[$comment['parent_id']][] = $comment;
            }
        }
        
        // Dodanie komentarzy potomnych do rodziców
        foreach ($hierarchy as &$comment) {
            $comment['children'] = $children[$comment['id']] ?? [];
        }
        
        return $hierarchy;
    }
    
    /**
     * Dodanie nowego komentarza
     */
    public function addComment($data)
    {
        // Sprawdzenie rate limiting
        if (!checkRateLimit('comment_' . ($data['user_id'] ?? $data['ip_address']), 5, 300)) {
            return ['success' => false, 'error' => 'Zbyt wiele komentarzy. Spróbuj ponownie za 5 minut.'];
        }
        
        // Walidacja
        $errors = $this->validateComment($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        // Ustawienie statusu
        $data['status'] = 'pending'; // Domyślnie oczekuje na moderację
        
        // Jeśli użytkownik jest zalogowany, automatycznie zatwierdź
        if (isset($data['user_id']) && isLoggedIn()) {
            $data['status'] = 'approved';
        }
        
        // Dodanie informacji o IP i user agent
        $data['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Zapisanie komentarza
        $commentId = $this->create($data);
        
        if ($commentId) {
            // Powiadomienie administratora o nowym komentarzu
            $this->notifyAdmin($commentId);
            
            return ['success' => true, 'comment_id' => $commentId];
        }
        
        return ['success' => false, 'error' => 'Błąd podczas dodawania komentarza'];
    }
    
    /**
     * Walidacja komentarza
     */
    private function validateComment($data)
    {
        $errors = [];
        
        if (empty($data['content']) || strlen(trim($data['content'])) < 3) {
            $errors['content'] = 'Komentarz musi mieć minimum 3 znaki';
        }
        
        if (strlen($data['content']) > 1000) {
            $errors['content'] = 'Komentarz nie może być dłuższy niż 1000 znaków';
        }
        
        // Sprawdzenie czy użytkownik nie jest zalogowany
        if (!isset($data['user_id']) || empty($data['user_id'])) {
            if (empty($data['author_name']) || strlen($data['author_name']) < 2) {
                $errors['author_name'] = 'Imię jest wymagane (minimum 2 znaki)';
            }
            
            if (empty($data['author_email']) || !validateEmail($data['author_email'])) {
                $errors['author_email'] = 'Nieprawidłowy adres email';
            }
        }
        
        // Sprawdzenie czy post istnieje i pozwala na komentarze
        if (isset($data['post_id'])) {
            $post = (new Post())->find($data['post_id']);
            if (!$post || $post['status'] !== 'published' || !$post['allow_comments']) {
                $errors['post'] = 'Nie można dodać komentarza do tego posta';
            }
        }
        
        // Sprawdzenie parent_id (dla komentarzy zagnieżdżonych)
        if (isset($data['parent_id']) && $data['parent_id']) {
            $parentComment = $this->find($data['parent_id']);
            if (!$parentComment || $parentComment['post_id'] != $data['post_id']) {
                $errors['parent'] = 'Nieprawidłowy komentarz nadrzędny';
            }
        }
        
        return $errors;
    }
    
    /**
     * Zatwierdzenie komentarza
     */
    public function approveComment($commentId)
    {
        $comment = $this->find($commentId);
        if (!$comment) {
            return false;
        }
        
        $result = $this->update($commentId, ['status' => 'approved']);
        
        if ($result) {
            // Powiadomienie autora komentarza
            $this->notifyAuthor($commentId, 'approved');
        }
        
        return $result;
    }
    
    /**
     * Odrzucenie komentarza
     */
    public function rejectComment($commentId)
    {
        $comment = $this->find($commentId);
        if (!$comment) {
            return false;
        }
        
        $result = $this->update($commentId, ['status' => 'spam']);
        
        if ($result) {
            // Powiadomienie autora komentarza
            $this->notifyAuthor($commentId, 'rejected');
        }
        
        return $result;
    }
    
    /**
     * Usunięcie komentarza
     */
    public function deleteComment($commentId)
    {
        // Usunięcie komentarzy potomnych
        $this->deleteChildComments($commentId);
        
        return $this->delete($commentId);
    }
    
    /**
     * Usunięcie komentarzy potomnych
     */
    private function deleteChildComments($parentId)
    {
        $children = $this->where('parent_id', $parentId);
        foreach ($children as $child) {
            $this->deleteChildComments($child['id']);
            $this->delete($child['id']);
        }
    }
    
    /**
     * Pobranie komentarzy do moderacji
     */
    public function getCommentsForModeration($page = 1, $perPage = 20)
    {
        $sql = "SELECT c.*, p.title as post_title, p.slug as post_slug,
                       u.username, u.first_name, u.last_name
                FROM comments c
                LEFT JOIN posts p ON c.post_id = p.id
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.status = 'pending'
                ORDER BY c.created_at ASC
                LIMIT ? OFFSET ?";
        
        $offset = ($page - 1) * $perPage;
        $comments = $this->raw($sql, [$perPage, $offset]);
        
        // Liczba wszystkich komentarzy oczekujących
        $total = $this->count('status = ?', ['pending']);
        
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
     * Pobranie komentarzy użytkownika
     */
    public function getUserComments($userId, $page = 1, $perPage = 10)
    {
        $sql = "SELECT c.*, p.title as post_title, p.slug as post_slug
                FROM comments c
                LEFT JOIN posts p ON c.post_id = p.id
                WHERE c.user_id = ?
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?";
        
        $offset = ($page - 1) * $perPage;
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
     * Pobranie statystyk komentarzy
     */
    public function getStats()
    {
        $stats = [];
        
        // Liczba wszystkich komentarzy
        $stats['total_comments'] = $this->count();
        
        // Liczba zatwierdzonych komentarzy
        $stats['approved_comments'] = $this->count('status = ?', ['approved']);
        
        // Liczba oczekujących komentarzy
        $stats['pending_comments'] = $this->count('status = ?', ['pending']);
        
        // Liczba spam komentarzy
        $stats['spam_comments'] = $this->count('status = ?', ['spam']);
        
        // Liczba komentarzy z dzisiaj
        $today = date('Y-m-d');
        $stats['today_comments'] = $this->count('DATE(created_at) = ?', [$today]);
        
        // Liczba komentarzy z tego tygodnia
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $stats['week_comments'] = $this->count('DATE(created_at) >= ?', [$weekStart]);
        
        // Liczba komentarzy z tego miesiąca
        $monthStart = date('Y-m-01');
        $stats['month_comments'] = $this->count('DATE(created_at) >= ?', [$monthStart]);
        
        return $stats;
    }
    
    /**
     * Powiadomienie administratora o nowym komentarzu
     */
    private function notifyAdmin($commentId)
    {
        $comment = $this->find($commentId);
        if (!$comment) {
            return;
        }
        
        $post = (new Post())->find($comment['post_id']);
        if (!$post) {
            return;
        }
        
        // Tutaj można dodać wysyłanie emaila do administratora
        // EmailService::sendCommentNotification($comment, $post);
    }
    
    /**
     * Powiadomienie autora komentarza
     */
    private function notifyAuthor($commentId, $action)
    {
        $comment = $this->find($commentId);
        if (!$comment) {
            return;
        }
        
        $post = (new Post())->find($comment['post_id']);
        if (!$post) {
            return;
        }
        
        // Tutaj można dodać wysyłanie emaila do autora komentarza
        // EmailService::sendCommentStatusNotification($comment, $post, $action);
    }
    
    /**
     * Sprawdzenie czy użytkownik może komentować
     */
    public function canComment($userId = null, $ipAddress = null)
    {
        $identifier = $userId ?: $ipAddress;
        if (!$identifier) {
            return false;
        }
        
        return checkRateLimit('comment_' . $identifier, 5, 300);
    }
    
    /**
     * Pobranie komentarzy z filtrami
     */
    public function getCommentsWithFilters($filters = [], $page = 1, $perPage = 20)
    {
        $where = '1=1';
        $params = [];
        
        if (isset($filters['status'])) {
            $where .= ' AND status = ?';
            $params[] = $filters['status'];
        }
        
        if (isset($filters['post_id'])) {
            $where .= ' AND post_id = ?';
            $params[] = $filters['post_id'];
        }
        
        if (isset($filters['user_id'])) {
            $where .= ' AND user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['date_from'])) {
            $where .= ' AND DATE(created_at) >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $where .= ' AND DATE(created_at) <= ?';
            $params[] = $filters['date_to'];
        }
        
        return $this->paginate($page, $perPage, $where, $params);
    }
}
