<?php
/**
 * Admin Controller
 * Kontroler administratora - zarządzanie systemem
 */

namespace App\Controllers;

class AdminController
{
    private $userModel;
    private $postModel;
    private $categoryModel;
    private $commentModel;
    private $settingModel;
    
    public function __construct()
    {
        $this->userModel = new User();
        $this->postModel = new Post();
        $this->categoryModel = new Category();
        $this->commentModel = new Comment();
        $this->settingModel = new Setting();
    }
    
    /**
     * Dashboard administratora
     */
    public function index()
    {
        try {
            // Statystyki ogólne
            $stats = [
                'total_users' => $this->userModel->getTotalUsers(),
                'total_posts' => $this->postModel->getTotalPosts(),
                'total_comments' => $this->commentModel->getTotalComments(),
                'pending_comments' => $this->commentModel->getPendingCommentsCount(),
                'total_categories' => $this->categoryModel->getTotalCategories(),
                'recent_posts' => $this->postModel->getRecentPosts(5),
                'recent_users' => $this->userModel->getRecentUsers(5),
                'recent_comments' => $this->commentModel->getRecentComments(5)
            ];
            
            $data = ['stats' => $stats];
            require_once APP_PATH . '/views/admin/dashboard.php';
            
        } catch (Exception $e) {
            logError('AdminController::index error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Zarządzanie użytkownikami
     */
    public function users()
    {
        try {
            $page = $_GET['page'] ?? 1;
            $search = $_GET['search'] ?? '';
            $role = $_GET['role'] ?? '';
            
            $users = $this->userModel->getUsersForAdmin($page, 20, $search, $role);
            $totalUsers = $this->userModel->getUsersCountForAdmin($search, $role);
            
            $pagination = paginate($totalUsers, 20, $page, "/admin/users?search=" . urlencode($search) . "&role={$role}&page={page}");
            
            $data = [
                'users' => $users,
                'pagination' => $pagination,
                'search' => $search,
                'role' => $role
            ];
            
            require_once APP_PATH . '/views/admin/users.php';
            
        } catch (Exception $e) {
            logError('AdminController::users error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Aktualizacja użytkownika
     */
    public function updateUser($userId)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $user = $this->userModel->find($userId);
            if (!$user) {
                render404();
                return;
            }
            
            $role = $_POST['role'] ?? $user['role'];
            $isActive = isset($_POST['is_active']);
            
            $updateData = [
                'role' => $role,
                'is_active' => $isActive
            ];
            
            if ($this->userModel->update($userId, $updateData)) {
                setFlashMessage('success', 'Użytkownik został zaktualizowany.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas aktualizacji użytkownika.');
            }
            
            redirect('/admin/users');
            
        } catch (Exception $e) {
            logError('AdminController::updateUser error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas aktualizacji użytkownika.');
            redirect('/admin/users');
        }
    }
    
    /**
     * Usunięcie użytkownika
     */
    public function deleteUser($userId)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $user = $this->userModel->find($userId);
            if (!$user) {
                render404();
                return;
            }
            
            // Nie można usunąć samego siebie
            if ($userId == $_SESSION['user_id']) {
                setFlashMessage('error', 'Nie możesz usunąć swojego konta.');
                redirect('/admin/users');
                return;
            }
            
            // Dezaktywacja użytkownika (soft delete)
            if ($this->userModel->update($userId, [
                'is_active' => false,
                'deleted_at' => date('Y-m-d H:i:s')
            ])) {
                setFlashMessage('success', 'Użytkownik został usunięty.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas usuwania użytkownika.');
            }
            
            redirect('/admin/users');
            
        } catch (Exception $e) {
            logError('AdminController::deleteUser error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas usuwania użytkownika.');
            redirect('/admin/users');
        }
    }
    
    /**
     * Zarządzanie postami
     */
    public function posts()
    {
        try {
            $page = $_GET['page'] ?? 1;
            $status = $_GET['status'] ?? 'all';
            $search = $_GET['search'] ?? '';
            
            $posts = $this->postModel->getPostsForAdmin($page, 20, $status, $search);
            $totalPosts = $this->postModel->getPostsCountForAdmin($status, $search);
            
            $pagination = paginate($totalPosts, 20, $page, "/admin/posts?status={$status}&search=" . urlencode($search) . "&page={page}");
            
            $data = [
                'posts' => $posts,
                'pagination' => $pagination,
                'status' => $status,
                'search' => $search
            ];
            
            require_once APP_PATH . '/views/admin/posts.php';
            
        } catch (Exception $e) {
            logError('AdminController::posts error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Zatwierdzenie postu
     */
    public function approvePost($postId)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $post = $this->postModel->find($postId);
            if (!$post) {
                render404();
                return;
            }
            
            if ($this->postModel->update($postId, [
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s')
            ])) {
                setFlashMessage('success', 'Post został zatwierdzony.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas zatwierdzania postu.');
            }
            
            redirect('/admin/posts');
            
        } catch (Exception $e) {
            logError('AdminController::approvePost error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas zatwierdzania postu.');
            redirect('/admin/posts');
        }
    }
    
    /**
     * Usunięcie postu
     */
    public function deletePost($postId)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $post = $this->postModel->find($postId);
            if (!$post) {
                render404();
                return;
            }
            
            if ($this->postModel->update($postId, [
                'status' => 'archived',
                'deleted_at' => date('Y-m-d H:i:s')
            ])) {
                setFlashMessage('success', 'Post został usunięty.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas usuwania postu.');
            }
            
            redirect('/admin/posts');
            
        } catch (Exception $e) {
            logError('AdminController::deletePost error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas usuwania postu.');
            redirect('/admin/posts');
        }
    }
    
    /**
     * Zarządzanie kategoriami
     */
    public function categories()
    {
        try {
            $categories = $this->categoryModel->all();
            
            $data = ['categories' => $categories];
            require_once APP_PATH . '/views/admin/categories.php';
            
        } catch (Exception $e) {
            logError('AdminController::categories error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Dodanie kategorii
     */
    public function storeCategory()
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $color = trim($_POST['color'] ?? '#3B82F6');
            
            if (empty($name) || strlen($name) < 2) {
                setFlashMessage('error', 'Nazwa kategorii musi mieć co najmniej 2 znaki.');
                redirect('/admin/categories');
                return;
            }
            
            $slug = generateSlug($name);
            $originalSlug = $slug;
            $counter = 1;
            
            while ($this->categoryModel->whereFirst('slug', $slug)) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            
            $categoryData = [
                'name' => sanitizeInput($name),
                'slug' => $slug,
                'description' => sanitizeInput($description),
                'color' => $color
            ];
            
            if ($this->categoryModel->create($categoryData)) {
                setFlashMessage('success', 'Kategoria została utworzona.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas tworzenia kategorii.');
            }
            
            redirect('/admin/categories');
            
        } catch (Exception $e) {
            logError('AdminController::storeCategory error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas tworzenia kategorii.');
            redirect('/admin/categories');
        }
    }
    
    /**
     * Aktualizacja kategorii
     */
    public function updateCategory($categoryId)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $category = $this->categoryModel->find($categoryId);
            if (!$category) {
                render404();
                return;
            }
            
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $color = trim($_POST['color'] ?? '#3B82F6');
            $isActive = isset($_POST['is_active']);
            
            if (empty($name) || strlen($name) < 2) {
                setFlashMessage('error', 'Nazwa kategorii musi mieć co najmniej 2 znaki.');
                redirect('/admin/categories');
                return;
            }
            
            $updateData = [
                'name' => sanitizeInput($name),
                'description' => sanitizeInput($description),
                'color' => $color,
                'is_active' => $isActive
            ];
            
            if ($this->categoryModel->update($categoryId, $updateData)) {
                setFlashMessage('success', 'Kategoria została zaktualizowana.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas aktualizacji kategorii.');
            }
            
            redirect('/admin/categories');
            
        } catch (Exception $e) {
            logError('AdminController::updateCategory error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas aktualizacji kategorii.');
            redirect('/admin/categories');
        }
    }
    
    /**
     * Usunięcie kategorii
     */
    public function deleteCategory($categoryId)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $category = $this->categoryModel->find($categoryId);
            if (!$category) {
                render404();
                return;
            }
            
            // Sprawdzenie czy kategoria ma posty
            $postsCount = $this->postModel->getPostsCountByCategory($categoryId);
            if ($postsCount > 0) {
                setFlashMessage('error', 'Nie można usunąć kategorii, która ma posty.');
                redirect('/admin/categories');
                return;
            }
            
            if ($this->categoryModel->delete($categoryId)) {
                setFlashMessage('success', 'Kategoria została usunięta.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas usuwania kategorii.');
            }
            
            redirect('/admin/categories');
            
        } catch (Exception $e) {
            logError('AdminController::deleteCategory error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas usuwania kategorii.');
            redirect('/admin/categories');
        }
    }
    
    /**
     * Zarządzanie komentarzami
     */
    public function comments()
    {
        try {
            $page = $_GET['page'] ?? 1;
            $status = $_GET['status'] ?? 'all';
            
            $comments = $this->commentModel->getCommentsForAdmin($page, 20, $status);
            $totalComments = $this->commentModel->getCommentsCountForAdmin($status);
            
            $pagination = paginate($totalComments, 20, $page, "/admin/comments?status={$status}&page={page}");
            
            $data = [
                'comments' => $comments,
                'pagination' => $pagination,
                'status' => $status
            ];
            
            require_once APP_PATH . '/views/admin/comments.php';
            
        } catch (Exception $e) {
            logError('AdminController::comments error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Zatwierdzenie komentarza
     */
    public function approveComment($commentId)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $comment = $this->commentModel->find($commentId);
            if (!$comment) {
                render404();
                return;
            }
            
            if ($this->commentModel->update($commentId, ['status' => 'approved'])) {
                setFlashMessage('success', 'Komentarz został zatwierdzony.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas zatwierdzania komentarza.');
            }
            
            redirect('/admin/comments');
            
        } catch (Exception $e) {
            logError('AdminController::approveComment error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas zatwierdzania komentarza.');
            redirect('/admin/comments');
        }
    }
    
    /**
     * Usunięcie komentarza
     */
    public function deleteComment($commentId)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $comment = $this->commentModel->find($commentId);
            if (!$comment) {
                render404();
                return;
            }
            
            if ($this->commentModel->delete($commentId)) {
                setFlashMessage('success', 'Komentarz został usunięty.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas usuwania komentarza.');
            }
            
            redirect('/admin/comments');
            
        } catch (Exception $e) {
            logError('AdminController::deleteComment error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas usuwania komentarza.');
            redirect('/admin/comments');
        }
    }
    
    /**
     * Ustawienia systemu
     */
    public function settings()
    {
        try {
            $settings = $this->settingModel->getAllSettings();
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->updateSettings();
            }
            
            $data = ['settings' => $settings];
            require_once APP_PATH . '/views/admin/settings.php';
            
        } catch (Exception $e) {
            logError('AdminController::settings error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Aktualizacja ustawień
     */
    private function updateSettings()
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $settings = [
                'site_name' => sanitizeInput($_POST['site_name'] ?? ''),
                'site_description' => sanitizeInput($_POST['site_description'] ?? ''),
                'posts_per_page' => (int)($_POST['posts_per_page'] ?? 10),
                'comments_per_page' => (int)($_POST['comments_per_page'] ?? 20),
                'allow_registration' => isset($_POST['allow_registration']),
                'require_email_verification' => isset($_POST['require_email_verification']),
                'moderate_comments' => isset($_POST['moderate_comments']),
                'allow_anonymous_comments' => isset($_POST['allow_anonymous_comments'])
            ];
            
            foreach ($settings as $key => $value) {
                $this->settingModel->updateSetting($key, $value);
            }
            
            setFlashMessage('success', 'Ustawienia zostały zaktualizowane.');
            
        } catch (Exception $e) {
            logError('AdminController::updateSettings error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas aktualizacji ustawień.');
        }
    }
    
    /**
     * Statystyki systemu
     */
    public function stats()
    {
        try {
            $stats = [
                'users' => $this->userModel->getStats(),
                'posts' => $this->postModel->getStats(),
                'comments' => $this->commentModel->getStats(),
                'categories' => $this->categoryModel->getStats()
            ];
            
            if (isAjaxRequest()) {
                jsonResponse($stats);
            } else {
                $data = ['stats' => $stats];
                require_once APP_PATH . '/views/admin/stats.php';
            }
            
        } catch (Exception $e) {
            logError('AdminController::stats error: ' . $e->getMessage());
            renderError();
        }
    }
}
