<?php
/**
 * Category Controller
 * Kontroler do zarządzania kategoriami
 */

namespace App\Controllers;

use App\Models\Category;

class CategoryController
{
    private $categoryModel;
    
    public function __construct()
    {
        $this->categoryModel = new Category();
    }
    
    /**
     * Wyświetlenie kategorii po slugu
     */
    public function show($slug)
    {
        try {
            // Pobranie kategorii
            $category = $this->categoryModel->whereFirst('slug', $slug);
            
            if (!$category) {
                render404();
                return;
            }
            
            // Pobranie postów z tej kategorii
            $page = $_GET['page'] ?? 1;
            $posts = $this->categoryModel->getPostsByCategory($category['id'], $page, POSTS_PER_PAGE);
            $totalPosts = $this->categoryModel->getPostsCountByCategory($category['id']);
            
            // Paginacja
            $pagination = paginate($totalPosts, POSTS_PER_PAGE, $page, "/category/{$slug}?page={page}");
            
            // Renderowanie widoku
            $this->render('categories/show', [
                'category' => $category,
                'posts' => $posts,
                'pagination' => $pagination,
                'totalPosts' => $totalPosts
            ]);
            
        } catch (Exception $e) {
            log_error('CategoryController::show error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Lista wszystkich kategorii (dla API)
     */
    public function index()
    {
        try {
            $categories = $this->categoryModel->getActiveCategories();
            
            if (is_ajax()) {
                json_response($categories);
            } else {
                $this->render('categories/index', [
                    'categories' => $categories
                ]);
            }
            
        } catch (Exception $e) {
            log_error('CategoryController::index error: ' . $e->getMessage());
            renderError();
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
        
        // Dołączenie layoutu
        include APP_PATH . "/views/layouts/header.php";
        include APP_PATH . "/views/{$view}.php";
        include APP_PATH . "/views/layouts/footer.php";
    }
}
