namespace App\Controllers;  
<?php
/**
 * Comment Controller
 * Kontroler do zarządzania komentarzami
 */

class CommentController
{
    private $commentModel;
    private $postModel;
    
    public function __construct()
    {
        $this->commentModel = new Comment();
        $this->postModel = new Post();
    }
    
    /**
     * Dodawanie komentarza
     */
    public function store($postSlug)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            // Rate limiting dla komentarzy
            if (!checkRateLimit('comments_' . $_SERVER['REMOTE_ADDR'], 5, 300)) {
                setFlashMessage('error', 'Zbyt wiele komentarzy. Spróbuj ponownie za 5 minut.');
                redirect("/posts/{$postSlug}");
                return;
            }
            
            // Pobranie postu
            $post = $this->postModel->whereFirst('slug', $postSlug);
            if (!$post || $post['status'] !== 'published') {
                render404();
                return;
            }
            
            // Sprawdzenie czy komentarze są dozwolone
            if (!$post['allow_comments']) {
                setFlashMessage('error', 'Komentarze są wyłączone dla tego postu.');
                redirect("/posts/{$postSlug}");
                return;
            }
            
            // Walidacja danych
            $content = trim($_POST['content'] ?? '');
            $parentId = (int)($_POST['parent_id'] ?? 0);
            $authorName = trim($_POST['author_name'] ?? '');
            $authorEmail = trim($_POST['author_email'] ?? '');
            
            if (empty($content) || strlen($content) < 3) {
                setFlashMessage('error', 'Komentarz musi mieć co najmniej 3 znaki.');
                redirect("/posts/{$postSlug}");
                return;
            }
            
            if (strlen($content) > 1000) {
                setFlashMessage('error', 'Komentarz jest za długi (maksymalnie 1000 znaków).');
                redirect("/posts/{$postSlug}");
                return;
            }
            
            // Sprawdzenie czy użytkownik jest zalogowany
            $userId = null;
            if (isLoggedIn()) {
                $userId = $_SESSION['user_id'];
                $authorName = $_SESSION['user_name'] ?? '';
                $authorEmail = $_SESSION['user_email'] ?? '';
            } else {
                // Walidacja danych anonimowego użytkownika
                if (empty($authorName) || strlen($authorName) < 2) {
                    setFlashMessage('error', 'Imię musi mieć co najmniej 2 znaki.');
                    redirect("/posts/{$postSlug}");
                    return;
                }
                
                if (!validateEmail($authorEmail)) {
                    setFlashMessage('error', 'Podaj prawidłowy adres email.');
                    redirect("/posts/{$postSlug}");
                    return;
                }
            }
            
            // Sprawdzenie parent_id
            if ($parentId > 0) {
                $parentComment = $this->commentModel->find($parentId);
                if (!$parentComment || $parentComment['post_id'] != $post['id']) {
                    setFlashMessage('error', 'Nieprawidłowy komentarz nadrzędny.');
                    redirect("/posts/{$postSlug}");
                    return;
                }
            }
            
            // Przygotowanie danych komentarza
            $commentData = [
                'post_id' => $post['id'],
                'user_id' => $userId,
                'parent_id' => $parentId > 0 ? $parentId : null,
                'author_name' => $authorName,
                'author_email' => $authorEmail,
                'content' => sanitizeInput($content),
                'status' => isLoggedIn() ? 'approved' : 'pending', // Anonimowe komentarze wymagają moderacji
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ];
            
            // Zapisanie komentarza
            $commentId = $this->commentModel->create($commentData);
            
            if ($commentId) {
                // Aktualizacja licznika komentarzy w poście
                $this->postModel->updateCommentCount($post['id']);
                
                if (isLoggedIn()) {
                    setFlashMessage('success', 'Komentarz został dodany.');
                } else {
                    setFlashMessage('success', 'Komentarz został dodany i oczekuje na moderację.');
                }
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas dodawania komentarza.');
            }
            
            redirect("/posts/{$postSlug}");
            
        } catch (Exception $e) {
            logError('CommentController::store error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas dodawania komentarza.');
            redirect("/posts/{$postSlug}");
        }
    }
    
    /**
     * Usuwanie komentarza (tylko dla zalogowanych użytkowników)
     */
    public function destroy($commentId)
    {
        try {
            if (!isLoggedIn()) {
                setFlashMessage('error', 'Musisz być zalogowany, aby usunąć komentarz.');
                redirect('/login');
                return;
            }
            
            $comment = $this->commentModel->find($commentId);
            if (!$comment) {
                render404();
                return;
            }
            
            // Sprawdzenie uprawnień
            $user = (new User())->find($_SESSION['user_id']);
            $canDelete = false;
            
            if ($comment['user_id'] == $_SESSION['user_id']) {
                $canDelete = true; // Właściciel komentarza
            } elseif (isAdmin()) {
                $canDelete = true; // Administrator
            } elseif (isAuthor()) {
                // Autor może usuwać komentarze ze swoich postów
                $post = $this->postModel->find($comment['post_id']);
                if ($post && $post['user_id'] == $_SESSION['user_id']) {
                    $canDelete = true;
                }
            }
            
            if (!$canDelete) {
                setFlashMessage('error', 'Nie masz uprawnień do usunięcia tego komentarza.');
                redirect('/');
                return;
            }
            
            // Usunięcie komentarza
            if ($this->commentModel->delete($commentId)) {
                // Aktualizacja licznika komentarzy w poście
                $this->postModel->updateCommentCount($comment['post_id']);
                
                setFlashMessage('success', 'Komentarz został usunięty.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas usuwania komentarza.');
            }
            
            // Przekierowanie z powrotem
            $post = $this->postModel->find($comment['post_id']);
            redirect("/posts/{$post['slug']}");
            
        } catch (Exception $e) {
            logError('CommentController::destroy error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas usuwania komentarza.');
            redirect('/');
        }
    }
    
    /**
     * Polubienie komentarza (AJAX)
     */
    public function like($commentId)
    {
        try {
            if (!isLoggedIn()) {
                jsonResponse(['error' => 'Musisz być zalogowany'], 401);
                return;
            }
            
            if (!isAjaxRequest()) {
                jsonResponse(['error' => 'Nieprawidłowe żądanie'], 400);
                return;
            }
            
            $comment = $this->commentModel->find($commentId);
            if (!$comment) {
                jsonResponse(['error' => 'Komentarz nie istnieje'], 404);
                return;
            }
            
            $result = $this->commentModel->toggleLike($commentId, $_SESSION['user_id']);
            $likesCount = $this->commentModel->getLikesCount($commentId);
            
            jsonResponse([
                'success' => true,
                'likes_count' => $likesCount,
                'is_liked' => $result['is_liked']
            ]);
            
        } catch (Exception $e) {
            logError('CommentController::like error: ' . $e->getMessage());
            jsonResponse(['error' => 'Wystąpił błąd'], 500);
        }
    }
}
