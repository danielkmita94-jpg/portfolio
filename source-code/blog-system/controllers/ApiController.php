namespace App\Controllers;  
<?php
/**
 * API Controller
 * Kontroler API - endpointy REST
 */

class ApiController
{
    private $postModel;
    private $categoryModel;
    private $tagModel;
    private $userModel;
    
    public function __construct()
    {
        $this->postModel = new Post();
        $this->categoryModel = new Category();
        $this->tagModel = new Tag();
        $this->userModel = new User();
        
        // Sprawdzenie czy to żądanie API
        if (!$this->isApiRequest()) {
            http_response_code(400);
            jsonResponse(['error' => 'Nieprawidłowe żądanie API']);
        }
    }
    
    /**
     * Lista postów (API)
     */
    public function posts()
    {
        try {
            $page = $_GET['page'] ?? 1;
            $limit = min((int)($_GET['limit'] ?? 10), 50); // Maksymalnie 50 postów
            $category = $_GET['category'] ?? '';
            $tag = $_GET['tag'] ?? '';
            $search = $_GET['search'] ?? '';
            
            $posts = $this->postModel->getPostsForApi($page, $limit, $category, $tag, $search);
            $totalPosts = $this->postModel->getPostsCountForApi($category, $tag, $search);
            
            $response = [
                'success' => true,
                'data' => $posts,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalPosts,
                    'total_pages' => ceil($totalPosts / $limit)
                ]
            ];
            
            jsonResponse($response);
            
        } catch (Exception $e) {
            logError('ApiController::posts error: ' . $e->getMessage());
            jsonResponse(['error' => 'Wystąpił błąd serwera'], 500);
        }
    }
    
    /**
     * Pojedynczy post (API)
     */
    public function post($postId)
    {
        try {
            $post = $this->postModel->getPostForApi($postId);
            
            if (!$post) {
                jsonResponse(['error' => 'Post nie istnieje'], 404);
                return;
            }
            
            // Zwiększenie licznika wizualizacji
            $this->postModel->incrementViewCount($postId);
            
            jsonResponse([
                'success' => true,
                'data' => $post
            ]);
            
        } catch (Exception $e) {
            logError('ApiController::post error: ' . $e->getMessage());
            jsonResponse(['error' => 'Wystąpił błąd serwera'], 500);
        }
    }
    
    /**
     * Lista kategorii (API)
     */
    public function categories()
    {
        try {
            $categories = $this->categoryModel->getActiveCategories();
            
            jsonResponse([
                'success' => true,
                'data' => $categories
            ]);
            
        } catch (Exception $e) {
            logError('ApiController::categories error: ' . $e->getMessage());
            jsonResponse(['error' => 'Wystąpił błąd serwera'], 500);
        }
    }
    
    /**
     * Lista tagów (API)
     */
    public function tags()
    {
        try {
            $tags = $this->tagModel->getPopularTags();
            
            jsonResponse([
                'success' => true,
                'data' => $tags
            ]);
            
        } catch (Exception $e) {
            logError('ApiController::tags error: ' . $e->getMessage());
            jsonResponse(['error' => 'Wystąpił błąd serwera'], 500);
        }
    }
    
    /**
     * Wyszukiwanie (API)
     */
    public function search()
    {
        try {
            $query = trim($_GET['q'] ?? '');
            $type = $_GET['type'] ?? 'posts';
            $limit = min((int)($_GET['limit'] ?? 10), 50);
            
            if (empty($query) || strlen($query) < 2) {
                jsonResponse(['error' => 'Zapytanie musi mieć co najmniej 2 znaki'], 400);
                return;
            }
            
            $results = [];
            
            switch ($type) {
                case 'posts':
                    $results = $this->postModel->search($query, 1, $limit);
                    break;
                case 'categories':
                    $results = $this->categoryModel->search($query, 1, $limit);
                    break;
                case 'tags':
                    $results = $this->tagModel->search($query, 1, $limit);
                    break;
                case 'all':
                    $results = [
                        'posts' => $this->postModel->search($query, 1, 5),
                        'categories' => $this->categoryModel->search($query, 1, 3),
                        'tags' => $this->tagModel->search($query, 1, 3)
                    ];
                    break;
                default:
                    jsonResponse(['error' => 'Nieprawidłowy typ wyszukiwania'], 400);
                    return;
            }
            
            jsonResponse([
                'success' => true,
                'data' => $results,
                'query' => $query,
                'type' => $type
            ]);
            
        } catch (Exception $e) {
            logError('ApiController::search error: ' . $e->getMessage());
            jsonResponse(['error' => 'Wystąpił błąd serwera'], 500);
        }
    }
    
    /**
     * Statystyki (API)
     */
    public function stats()
    {
        try {
            $stats = [
                'total_posts' => $this->postModel->getTotalPosts(),
                'total_categories' => $this->categoryModel->getTotalCategories(),
                'total_tags' => $this->tagModel->getTotalTags(),
                'total_users' => $this->userModel->getTotalUsers(),
                'recent_posts' => $this->postModel->getRecentPosts(5),
                'popular_categories' => $this->categoryModel->getPopularCategories(5),
                'popular_tags' => $this->tagModel->getPopularTags(10)
            ];
            
            jsonResponse([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (Exception $e) {
            logError('ApiController::stats error: ' . $e->getMessage());
            jsonResponse(['error' => 'Wystąpił błąd serwera'], 500);
        }
    }
    
    /**
     * Autocomplete (API)
     */
    public function autocomplete()
    {
        try {
            $query = trim($_GET['q'] ?? '');
            $type = $_GET['type'] ?? 'posts';
            $limit = min((int)($_GET['limit'] ?? 5), 10);
            
            if (strlen($query) < 2) {
                jsonResponse(['data' => []]);
                return;
            }
            
            $results = [];
            
            switch ($type) {
                case 'posts':
                    $results = $this->postModel->searchAutocomplete($query, $limit);
                    break;
                case 'categories':
                    $results = $this->categoryModel->searchAutocomplete($query, $limit);
                    break;
                case 'tags':
                    $results = $this->tagModel->searchAutocomplete($query, $limit);
                    break;
                default:
                    jsonResponse(['error' => 'Nieprawidłowy typ autocomplete'], 400);
                    return;
            }
            
            jsonResponse([
                'success' => true,
                'data' => $results
            ]);
            
        } catch (Exception $e) {
            logError('ApiController::autocomplete error: ' . $e->getMessage());
            jsonResponse(['error' => 'Wystąpił błąd serwera'], 500);
        }
    }
    
    /**
     * Polubienie postu (API)
     */
    public function likePost($postId)
    {
        try {
            if (!isLoggedIn()) {
                jsonResponse(['error' => 'Musisz być zalogowany'], 401);
                return;
            }
            
            $post = $this->postModel->find($postId);
            if (!$post) {
                jsonResponse(['error' => 'Post nie istnieje'], 404);
                return;
            }
            
            $result = $this->postModel->toggleLike($postId, $_SESSION['user_id']);
            $likesCount = $this->postModel->getLikesCount($postId);
            
            jsonResponse([
                'success' => true,
                'data' => [
                    'likes_count' => $likesCount,
                    'is_liked' => $result['is_liked']
                ]
            ]);
            
        } catch (Exception $e) {
            logError('ApiController::likePost error: ' . $e->getMessage());
            jsonResponse(['error' => 'Wystąpił błąd serwera'], 500);
        }
    }
    
    /**
     * Dodanie komentarza (API)
     */
    public function addComment()
    {
        try {
            if (!isLoggedIn()) {
                jsonResponse(['error' => 'Musisz być zalogowany'], 401);
                return;
            }
            
            // Walidacja CSRF
            validateCSRFToken();
            
            $postId = (int)($_POST['post_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $parentId = (int)($_POST['parent_id'] ?? 0);
            
            if ($postId <= 0) {
                jsonResponse(['error' => 'Nieprawidłowe ID postu'], 400);
                return;
            }
            
            if (empty($content) || strlen($content) < 3) {
                jsonResponse(['error' => 'Komentarz musi mieć co najmniej 3 znaki'], 400);
                return;
            }
            
            if (strlen($content) > 1000) {
                jsonResponse(['error' => 'Komentarz jest za długi'], 400);
                return;
            }
            
            $post = $this->postModel->find($postId);
            if (!$post || $post['status'] !== 'published') {
                jsonResponse(['error' => 'Post nie istnieje'], 404);
                return;
            }
            
            if (!$post['allow_comments']) {
                jsonResponse(['error' => 'Komentarze są wyłączone dla tego postu'], 400);
                return;
            }
            
            // Sprawdzenie parent_id
            if ($parentId > 0) {
                $parentComment = (new Comment())->find($parentId);
                if (!$parentComment || $parentComment['post_id'] != $postId) {
                    jsonResponse(['error' => 'Nieprawidłowy komentarz nadrzędny'], 400);
                    return;
                }
            }
            
            $commentData = [
                'post_id' => $postId,
                'user_id' => $_SESSION['user_id'],
                'parent_id' => $parentId > 0 ? $parentId : null,
                'author_name' => $_SESSION['user_name'] ?? '',
                'author_email' => $_SESSION['user_email'] ?? '',
                'content' => sanitizeInput($content),
                'status' => 'approved',
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ];
            
            $commentModel = new Comment();
            $commentId = $commentModel->create($commentData);
            
            if ($commentId) {
                // Aktualizacja licznika komentarzy
                $this->postModel->updateCommentCount($postId);
                
                $comment = $commentModel->find($commentId);
                
                jsonResponse([
                    'success' => true,
                    'data' => $comment,
                    'message' => 'Komentarz został dodany'
                ]);
            } else {
                jsonResponse(['error' => 'Wystąpił błąd podczas dodawania komentarza'], 500);
            }
            
        } catch (Exception $e) {
            logError('ApiController::addComment error: ' . $e->getMessage());
            jsonResponse(['error' => 'Wystąpił błąd serwera'], 500);
        }
    }
    
    /**
     * Sprawdzenie czy to żądanie API
     */
    private function isApiRequest()
    {
        return strpos($_SERVER['REQUEST_URI'], '/api/') === 0;
    }
}
