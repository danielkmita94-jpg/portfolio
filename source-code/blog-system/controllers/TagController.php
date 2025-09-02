<?php
/**
 * Tag Controller
 * Kontroler do zarządzania tagami
 */

namespace App\Controllers;

class TagController
{
    private $tagModel;
    
    public function __construct()
    {
        $this->tagModel = new Tag();
    }
    
    /**
     * Wyświetlenie tagu po slugu
     */
    public function show($slug)
    {
        try {
            // Pobranie tagu
            $tag = $this->tagModel->whereFirst('slug', $slug);
            
            if (!$tag) {
                render404();
                return;
            }
            
            // Pobranie postów z tym tagiem
            $page = $_GET['page'] ?? 1;
            $posts = $this->tagModel->getPostsByTag($tag['id'], $page, POSTS_PER_PAGE);
            $totalPosts = $this->tagModel->getPostsCountByTag($tag['id']);
            
            // Paginacja
            $pagination = paginate($totalPosts, POSTS_PER_PAGE, $page, "/tag/{$slug}?page={page}");
            
            // Renderowanie widoku
            $data = [
                'tag' => $tag,
                'posts' => $posts,
                'pagination' => $pagination
            ];
            
            require_once APP_PATH . '/views/tags/show.php';
            
        } catch (Exception $e) {
            logError('TagController::show error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Lista wszystkich tagów (dla API)
     */
    public function index()
    {
        try {
            $tags = $this->tagModel->getPopularTags();
            
            if (isAjaxRequest()) {
                jsonResponse($tags);
            } else {
                $data = ['tags' => $tags];
                require_once APP_PATH . '/views/tags/index.php';
            }
            
        } catch (Exception $e) {
            logError('TagController::index error: ' . $e->getMessage());
            renderError();
        }
    }
}
