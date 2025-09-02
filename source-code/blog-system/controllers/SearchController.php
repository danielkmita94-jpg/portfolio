<?php
/**
 * Search Controller
 * Kontroler do wyszukiwania treści
 */

namespace App\Controllers;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;

class SearchController
{
    private $postModel;
    private $categoryModel;
    private $tagModel;
    
    public function __construct()
    {
        $this->postModel = new Post();
        $this->categoryModel = new Category();
        $this->tagModel = new Tag();
    }
    
    /**
     * Wyszukiwanie treści
     */
    public function index()
    {
        try {
            $query = trim($_GET['q'] ?? '');
            $type = $_GET['type'] ?? 'posts';
            $page = $_GET['page'] ?? 1;
            
            if (empty($query)) {
                $data = [
                    'query' => '',
                    'results' => [],
                    'pagination' => null,
                    'type' => $type
                ];
                require_once APP_PATH . '/views/search/index.php';
                return;
            }
            
            // Rate limiting dla wyszukiwania
            if (!checkRateLimit('search_' . $_SERVER['REMOTE_ADDR'], 30, 3600)) {
                setFlashMessage('error', 'Zbyt wiele wyszukiwań. Spróbuj ponownie za godzinę.');
                redirect('/');
                return;
            }
            
            $results = [];
            $totalResults = 0;
            
            switch ($type) {
                case 'posts':
                    $results = $this->postModel->search($query, $page, POSTS_PER_PAGE);
                    $totalResults = $this->postModel->searchCount($query);
                    break;
                    
                case 'categories':
                    $results = $this->categoryModel->search($query, $page, 20);
                    $totalResults = $this->categoryModel->searchCount($query);
                    break;
                    
                case 'tags':
                    $results = $this->tagModel->search($query, $page, 20);
                    $totalResults = $this->tagModel->searchCount($query);
                    break;
                    
                default:
                    // Wyszukiwanie we wszystkich typach
                    $posts = $this->postModel->search($query, 1, 5);
                    $categories = $this->categoryModel->search($query, 1, 3);
                    $tags = $this->tagModel->search($query, 1, 3);
                    
                    $results = [
                        'posts' => $posts,
                        'categories' => $categories,
                        'tags' => $tags
                    ];
                    $totalResults = count($posts) + count($categories) + count($tags);
                    break;
            }
            
            // Paginacja
            $pagination = null;
            if ($type !== 'all') {
                $pagination = paginate($totalResults, POSTS_PER_PAGE, $page, "/search?q=" . urlencode($query) . "&type={$type}&page={page}");
            }
            
            $data = [
                'query' => $query,
                'results' => $results,
                'pagination' => $pagination,
                'type' => $type,
                'totalResults' => $totalResults
            ];
            
            require_once APP_PATH . '/views/search/index.php';
            
        } catch (Exception $e) {
            logError('SearchController::index error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Wyszukiwanie AJAX (autocomplete)
     */
    public function autocomplete()
    {
        try {
            if (!isAjaxRequest()) {
                http_response_code(400);
                return;
            }
            
            $query = trim($_GET['q'] ?? '');
            $type = $_GET['type'] ?? 'posts';
            
            if (strlen($query) < 2) {
                jsonResponse([]);
                return;
            }
            
            $results = [];
            
            switch ($type) {
                case 'posts':
                    $results = $this->postModel->searchAutocomplete($query, 5);
                    break;
                case 'categories':
                    $results = $this->categoryModel->searchAutocomplete($query, 5);
                    break;
                case 'tags':
                    $results = $this->tagModel->searchAutocomplete($query, 5);
                    break;
            }
            
            jsonResponse($results);
            
        } catch (Exception $e) {
            logError('SearchController::autocomplete error: ' . $e->getMessage());
            jsonResponse([], 500);
        }
    }
}
