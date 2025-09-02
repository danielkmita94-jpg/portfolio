<?php
/**
 * Post Controller
 * Kontroler postów blogowych (publiczny)
 */

namespace App\Controllers;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Comment;
use App\Models\User;
use App\Models\Setting;

class PostController
{
    private $postModel;
    private $categoryModel;
    private $tagModel;
    private $commentModel;
    private $userModel;
    
    public function __construct()
    {
        $this->postModel = new Post();
        $this->categoryModel = new Category();
        $this->tagModel = new Tag();
        $this->commentModel = new Comment();
        $this->userModel = new User();
    }
    
    /**
     * Lista wszystkich postów
     */
    public function index()
    {
        $page = (int) ($_GET['page'] ?? 1);
        $categoryId = $_GET['category'] ?? null;
        $search = $_GET['search'] ?? null;
        
        // Pobranie postów
        if ($search) {
            $result = $this->postModel->search($search, $page, POSTS_PER_PAGE);
        } else {
            $result = $this->postModel->getPublishedPosts($page, POSTS_PER_PAGE, $categoryId);
        }
        
        // Pobranie kategorii dla filtra
        $categories = $this->categoryModel->getActiveCategories();
        
        // Pobranie popularnych tagów
        $popularTags = $this->tagModel->getPopularTags(10);
        
        $this->render('posts/index', [
            'posts' => $result['data'] ?? [],
            'pagination' => $result['pagination'] ?? '',
            'categories' => $categories,
            'popular_tags' => $popularTags,
            'current_category' => $categoryId,
            'search_query' => $search,
            'page_title' => $search ? "Wyniki wyszukiwania: {$search}" : 'Wszystkie posty',
            'page_description' => 'Przeglądaj wszystkie artykuły na naszym blogu'
        ]);
    }
    
    /**
     * Wyświetlanie pojedynczego posta
     */
    public function show($slug)
    {
        $post = $this->postModel->getBySlug($slug);
        
        if (!$post) {
            http_response_code(404);
            $this->render('errors/404', [
                'page_title' => 'Post nie znaleziony',
                'page_description' => 'Szukany post nie istnieje'
            ]);
            return;
        }
        
        // Zwiększenie licznika wyświetleń
        $this->postModel->incrementViewCount($post['id']);
        
        // Pobranie komentarzy
        $comments = $this->commentModel->getCommentsHierarchy($post['id']);
        
        // Pobranie tagów posta
        $tags = $this->postModel->getTags($post['id']);
        
        // Pobranie powiązanych postów
        $relatedPosts = $this->postModel->getRelatedPosts($post['id'], 3);
        
        // Pobranie kategorii dla menu
        $categories = $this->categoryModel->getActiveCategories();
        
        // Pobranie popularnych tagów
        $popularTags = $this->tagModel->getPopularTags(10);
        
        $this->render('posts/show', [
            'post' => $post,
            'comments' => $comments,
            'tags' => $tags,
            'related_posts' => $relatedPosts,
            'categories' => $categories,
            'popular_tags' => $popularTags,
            'page_title' => $post['meta_title'] ?: $post['title'],
            'page_description' => $post['meta_description'] ?: $post['excerpt'],
            'can_comment' => $this->commentModel->canComment(
                $_SESSION['user_id'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null
            )
        ]);
    }
    
    /**
     * Obsługa dodawania komentarza
     */
    public function addComment($slug)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/posts/' . $slug);
        }
        
        $post = $this->postModel->getBySlug($slug);
        if (!$post) {
            setFlashMessage('error', 'Post nie istnieje.');
            redirect('/posts');
        }
        
        // Sprawdzenie czy post pozwala na komentarze
        if (!$post['allow_comments']) {
            setFlashMessage('error', 'Komentarze są wyłączone dla tego posta.');
            redirect('/posts/' . $slug);
        }
        
        // Przygotowanie danych komentarza
        $commentData = [
            'post_id' => $post['id'],
            'content' => sanitizeInput($_POST['content'] ?? ''),
            'parent_id' => !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null
        ];
        
        // Dodanie danych użytkownika
        if (isLoggedIn()) {
            $commentData['user_id'] = $_SESSION['user_id'];
        } else {
            $commentData['author_name'] = sanitizeInput($_POST['author_name'] ?? '');
            $commentData['author_email'] = sanitizeInput($_POST['author_email'] ?? '');
            $commentData['author_website'] = sanitizeInput($_POST['author_website'] ?? '');
        }
        
        // Dodanie komentarza
        $result = $this->commentModel->addComment($commentData);
        
        if ($result['success']) {
            setFlashMessage('success', 'Komentarz został dodany i oczekuje na moderację.');
        } else {
            setFlashMessage('error', $result['error'] ?? 'Wystąpił błąd podczas dodawania komentarza.');
        }
        
        redirect('/posts/' . $slug . '#comments');
    }
    
    /**
     * Polubienie posta
     */
    public function like($postId)
    {
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'error' => 'Musisz być zalogowany'], 401);
        }
        
        $post = $this->postModel->find($postId);
        if (!$post || $post['status'] !== 'published') {
            jsonResponse(['success' => false, 'error' => 'Post nie istnieje'], 404);
        }
        
        $result = $this->userModel->toggleLike($_SESSION['user_id'], $postId);
        
        if ($result['success']) {
            // Pobranie aktualnej liczby polubień
            $post = $this->postModel->getBySlug($post['slug']);
            jsonResponse([
                'success' => true,
                'action' => $result['action'],
                'like_count' => $post['like_count']
            ]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Wystąpił błąd'], 500);
        }
    }
    
    /**
     * Wyszukiwanie postów
     */
    public function search()
    {
        $query = sanitizeInput($_GET['q'] ?? '');
        $page = (int) ($_GET['page'] ?? 1);
        
        if (empty($query)) {
            redirect('/posts');
        }
        
        $result = $this->postModel->search($query, $page, POSTS_PER_PAGE);
        
        // Pobranie kategorii dla menu
        $categories = $this->categoryModel->getActiveCategories();
        
        // Pobranie popularnych tagów
        $popularTags = $this->tagModel->getPopularTags(10);
        
        $this->render('posts/search', [
            'posts' => $result['posts'],
            'pagination' => $result['pagination'],
            'categories' => $categories,
            'popular_tags' => $popularTags,
            'search_query' => $query,
            'page_title' => "Wyniki wyszukiwania: {$query}",
            'page_description' => "Wyniki wyszukiwania dla: {$query}"
        ]);
    }
    
    /**
     * API - Pobranie postów
     */
    public function apiPosts()
    {
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = min((int) ($_GET['per_page'] ?? 10), 50); // Maksymalnie 50
        $categoryId = $_GET['category_id'] ?? null;
        $search = $_GET['search'] ?? null;
        
        if ($search) {
            $result = $this->postModel->search($search, $page, $perPage);
        } else {
            $result = $this->postModel->getPublishedPosts($page, $perPage, $categoryId);
        }
        
        // Przygotowanie danych do API
        $posts = array_map(function($post) {
            return [
                'id' => $post['id'],
                'title' => $post['title'],
                'slug' => $post['slug'],
                'excerpt' => $post['excerpt'],
                'content' => $post['content'],
                'featured_image' => $post['featured_image'],
                'author' => [
                    'username' => $post['author_username'],
                    'first_name' => $post['author_first_name'],
                    'last_name' => $post['author_last_name']
                ],
                'category' => $post['category_name'] ? [
                    'name' => $post['category_name'],
                    'slug' => $post['category_slug'],
                    'color' => $post['category_color']
                ] : null,
                'stats' => [
                    'view_count' => $post['view_count'],
                    'comment_count' => $post['comment_count'],
                    'like_count' => $post['like_count']
                ],
                'published_at' => $post['published_at'],
                'created_at' => $post['created_at']
            ];
        }, $result['data']);
        
        jsonResponse([
            'success' => true,
            'data' => $posts,
            'pagination' => $result['pagination']
        ]);
    }
    
    /**
     * API - Pobranie pojedynczego posta
     */
    public function apiPost($id)
    {
        $post = $this->postModel->find($id);
        
        if (!$post || $post['status'] !== 'published') {
            jsonResponse(['success' => false, 'error' => 'Post nie istnieje'], 404);
        }
        
        // Pobranie dodatkowych danych
        $post = $this->postModel->getBySlug($post['slug']);
        $tags = $this->postModel->getTags($post['id']);
        
        // Przygotowanie danych do API
        $postData = [
            'id' => $post['id'],
            'title' => $post['title'],
            'slug' => $post['slug'],
            'excerpt' => $post['excerpt'],
            'content' => $post['content'],
            'featured_image' => $post['featured_image'],
            'thumbnail' => $post['thumbnail'],
            'meta_title' => $post['meta_title'],
            'meta_description' => $post['meta_description'],
            'author' => [
                'username' => $post['author_username'],
                'first_name' => $post['author_first_name'],
                'last_name' => $post['author_last_name'],
                'avatar' => $post['avatar']
            ],
            'category' => $post['category_name'] ? [
                'name' => $post['category_name'],
                'slug' => $post['category_slug'],
                'color' => $post['category_color']
            ] : null,
            'tags' => array_map(function($tag) {
                return [
                    'name' => $tag['name'],
                    'slug' => $tag['slug'],
                    'description' => $tag['description']
                ];
            }, $tags),
            'stats' => [
                'view_count' => $post['view_count'],
                'comment_count' => $post['comment_count'],
                'like_count' => $post['like_count']
            ],
            'published_at' => $post['published_at'],
            'created_at' => $post['created_at'],
            'updated_at' => $post['updated_at']
        ];
        
        jsonResponse([
            'success' => true,
            'data' => $postData
        ]);
    }
    
    /**
     * Moje posty - lista postów użytkownika
     */
    public function myPosts()
    {
        try {
            $userId = $_SESSION['user_id'];
            $page = (int) ($_GET['page'] ?? 1);
            
            // Pobranie postów użytkownika
            $result = $this->postModel->getUserPosts($userId, $page, POSTS_PER_PAGE);
            
            // Pobranie kategorii dla menu
            $categories = $this->categoryModel->getActiveCategories();
            
            $this->render('posts/my', [
                'posts' => $result['data'],
                'pagination' => $result['pagination'],
                'categories' => $categories,
                'page_title' => 'Moje posty',
                'page_description' => 'Zarządzaj swoimi artykułami'
            ]);
            
        } catch (Exception $e) {
            log_error('PostController::myPosts error: ' . $e->getMessage());
            set_flash_message('error', 'Wystąpił błąd podczas ładowania postów.');
            redirect('/dashboard');
        }
    }
    
    /**
     * Formularz tworzenia nowego posta
     */
    public function create()
    {
        try {
            // Pobranie kategorii
            $categories = $this->categoryModel->getActiveCategories();
            
            // Pobranie tagów
            $tags = $this->tagModel->getAllTags();
            
            $this->render('posts/create', [
                'categories' => $categories,
                'tags' => $tags,
                'page_title' => 'Nowy artykuł',
                'page_description' => 'Utwórz nowy artykuł'
            ]);
            
        } catch (Exception $e) {
            log_error('PostController::create error: ' . $e->getMessage());
            set_flash_message('error', 'Wystąpił błąd podczas ładowania formularza.');
            redirect('/posts/my');
        }
    }
    
    /**
     * Zapisywanie nowego posta
     */
    public function store()
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $userId = $_SESSION['user_id'];
            
            // Walidacja danych
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $excerpt = trim($_POST['excerpt'] ?? '');
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $tags = $_POST['tags'] ?? [];
            $status = $_POST['status'] ?? 'draft';
            
            if (empty($title) || strlen($title) < 3) {
                set_flash_message('error', 'Tytuł musi mieć co najmniej 3 znaki.');
                redirect('/posts/create');
                return;
            }
            
            if (empty($content) || strlen($content) < 10) {
                set_flash_message('error', 'Treść musi mieć co najmniej 10 znaków.');
                redirect('/posts/create');
                return;
            }
            
            // Generowanie sluga
            $slug = createSlug($title);
            
            // Sprawdzenie czy slug już istnieje
            $existingPost = $this->postModel->getBySlug($slug);
            if ($existingPost) {
                $slug = $slug . '-' . time();
            }
            
            // Przygotowanie danych
            $postData = [
                'title' => sanitizeInput($title),
                'slug' => $slug,
                'excerpt' => sanitizeInput($excerpt),
                'content' => $content,
                'user_id' => $userId,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'status' => $status,
                'published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null
            ];
            
            // Upload obrazka
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $image = $this->uploadImage($_FILES['featured_image']);
                if ($image) {
                    $postData['featured_image'] = $image;
                }
            }
            
            // Zapisywanie posta
            $postId = $this->postModel->create($postData);
            
            if ($postId) {
                // Zapisywanie tagów
                if (!empty($tags)) {
                    $this->postModel->syncTags($postId, $tags);
                }
                
                set_flash_message('success', 'Artykuł został utworzony pomyślnie.');
                redirect('/posts/my');
            } else {
                set_flash_message('error', 'Wystąpił błąd podczas zapisywania artykułu.');
                redirect('/posts/create');
            }
            
        } catch (Exception $e) {
            log_error('PostController::store error: ' . $e->getMessage());
            set_flash_message('error', 'Wystąpił błąd podczas zapisywania artykułu.');
            redirect('/posts/create');
        }
    }
    
    /**
     * Formularz edycji posta
     */
    public function edit($id)
    {
        try {
            $userId = $_SESSION['user_id'];
            
            // Pobranie posta
            $post = $this->postModel->find($id);
            
            if (!$post || $post['user_id'] != $userId) {
                set_flash_message('error', 'Post nie istnieje lub nie masz uprawnień do jego edycji.');
                redirect('/posts/my');
                return;
            }
            
            // Pobranie kategorii
            $categories = $this->categoryModel->getActiveCategories();
            
            // Pobranie tagów
            $tags = $this->tagModel->getAllTags();
            
            // Pobranie tagów posta
            $postTags = $this->postModel->getTags($id);
            $postTagIds = array_column($postTags, 'id');
            
            $this->render('posts/edit', [
                'post' => $post,
                'categories' => $categories,
                'tags' => $tags,
                'post_tags' => $postTagIds,
                'page_title' => 'Edycja artykułu',
                'page_description' => 'Edytuj swój artykuł'
            ]);
            
        } catch (Exception $e) {
            log_error('PostController::edit error: ' . $e->getMessage());
            set_flash_message('error', 'Wystąpił błąd podczas ładowania formularza.');
            redirect('/posts/my');
        }
    }
    
    /**
     * Aktualizacja posta
     */
    public function update($id)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $userId = $_SESSION['user_id'];
            
            // Sprawdzenie czy post należy do użytkownika
            $post = $this->postModel->find($id);
            if (!$post || $post['user_id'] != $userId) {
                set_flash_message('error', 'Post nie istnieje lub nie masz uprawnień do jego edycji.');
                redirect('/posts/my');
                return;
            }
            
            // Walidacja danych
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $excerpt = trim($_POST['excerpt'] ?? '');
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $tags = $_POST['tags'] ?? [];
            $status = $_POST['status'] ?? 'draft';
            
            if (empty($title) || strlen($title) < 3) {
                set_flash_message('error', 'Tytuł musi mieć co najmniej 3 znaki.');
                redirect("/posts/{$id}/edit");
                return;
            }
            
            if (empty($content) || strlen($content) < 10) {
                set_flash_message('error', 'Treść musi mieć co najmniej 10 znaków.');
                redirect("/posts/{$id}/edit");
                return;
            }
            
            // Generowanie sluga
            $slug = createSlug($title);
            
            // Sprawdzenie czy slug już istnieje (z wyjątkiem aktualnego posta)
            $existingPost = $this->postModel->getBySlug($slug);
            if ($existingPost && $existingPost['id'] != $id) {
                $slug = $slug . '-' . time();
            }
            
            // Przygotowanie danych
            $postData = [
                'title' => sanitizeInput($title),
                'slug' => $slug,
                'excerpt' => sanitizeInput($excerpt),
                'content' => $content,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'status' => $status,
                'published_at' => $status === 'published' && !$post['published_at'] ? date('Y-m-d H:i:s') : $post['published_at']
            ];
            
            // Upload obrazka
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $image = $this->uploadImage($_FILES['featured_image']);
                if ($image) {
                    $postData['featured_image'] = $image;
                    
                    // Usunięcie starego obrazka
                    if ($post['featured_image'] && file_exists(UPLOADS_PATH . '/posts/' . $post['featured_image'])) {
                        unlink(UPLOADS_PATH . '/posts/' . $post['featured_image']);
                    }
                }
            }
            
            // Aktualizacja posta
            if ($this->postModel->update($id, $postData)) {
                // Aktualizacja tagów
                $this->postModel->syncTags($id, $tags);
                
                set_flash_message('success', 'Artykuł został zaktualizowany pomyślnie.');
                redirect('/posts/my');
            } else {
                set_flash_message('error', 'Wystąpił błąd podczas aktualizacji artykułu.');
                redirect("/posts/{$id}/edit");
            }
            
        } catch (Exception $e) {
            log_error('PostController::update error: ' . $e->getMessage());
            set_flash_message('error', 'Wystąpił błąd podczas aktualizacji artykułu.');
            redirect("/posts/{$id}/edit");
        }
    }
    
    /**
     * Usuwanie posta
     */
    public function destroy($id)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $userId = $_SESSION['user_id'];
            
            // Sprawdzenie czy post należy do użytkownika
            $post = $this->postModel->find($id);
            if (!$post || $post['user_id'] != $userId) {
                set_flash_message('error', 'Post nie istnieje lub nie masz uprawnień do jego usunięcia.');
                redirect('/posts/my');
                return;
            }
            
            // Usunięcie obrazka
            if ($post['featured_image'] && file_exists(UPLOADS_PATH . '/posts/' . $post['featured_image'])) {
                unlink(UPLOADS_PATH . '/posts/' . $post['featured_image']);
            }
            
            // Usunięcie posta
            if ($this->postModel->delete($id)) {
                set_flash_message('success', 'Artykuł został usunięty pomyślnie.');
            } else {
                set_flash_message('error', 'Wystąpił błąd podczas usuwania artykułu.');
            }
            
            redirect('/posts/my');
            
        } catch (Exception $e) {
            log_error('PostController::destroy error: ' . $e->getMessage());
            set_flash_message('error', 'Wystąpił błąd podczas usuwania artykułu.');
            redirect('/posts/my');
        }
    }
    
    /**
     * Upload obrazka
     */
    private function uploadImage($file)
    {
        try {
            // Sprawdzenie typu pliku
            if (!is_image($file)) {
                set_flash_message('error', 'Dozwolone są tylko pliki obrazów (JPG, PNG, GIF, WebP).');
                return false;
            }
            
            // Sprawdzenie rozmiaru pliku
            if ($file['size'] > MAX_FILE_SIZE) {
                set_flash_message('error', 'Plik jest za duży (maksymalnie 5MB).');
                return false;
            }
            
            $uploadDir = UPLOADS_PATH . '/posts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = 'post_' . time() . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Generowanie miniaturki
                $thumbnailPath = $uploadDir . 'thumb_' . $fileName;
                createThumbnail('posts/' . $fileName, 'posts/thumb_' . $fileName, 300, 200);
                
                return $fileName;
            }
            
            return false;
            
        } catch (Exception $e) {
            log_error('PostController::uploadImage error: ' . $e->getMessage());
            return false;
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
        
        // Pobranie kategorii dla menu
        $categories = $this->categoryModel->getActiveCategories();
        
        // Pobranie ustawień systemu
        $settings = (new Setting())->getPublicSettings();
        
        // Dołączenie layoutu
        include APP_PATH . "/views/layouts/header.php";
        include APP_PATH . "/views/{$view}.php";
        include APP_PATH . "/views/layouts/footer.php";
    }
}
