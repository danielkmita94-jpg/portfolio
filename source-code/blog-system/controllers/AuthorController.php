namespace App\Controllers;  
<?php
/**
 * Author Controller
 * Kontroler dla autorów - zarządzanie postami
 */

class AuthorController
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
     * Lista postów autora
     */
    public function posts()
    {
        try {
            $userId = $_SESSION['user_id'];
            $page = $_GET['page'] ?? 1;
            $status = $_GET['status'] ?? 'all';
            
            $posts = $this->postModel->getPostsByAuthor($userId, $status, $page, POSTS_PER_PAGE);
            $totalPosts = $this->postModel->getPostsCountByAuthor($userId, $status);
            
            $pagination = paginate($totalPosts, POSTS_PER_PAGE, $page, "/author/posts?status={$status}&page={page}");
            
            $data = [
                'posts' => $posts,
                'pagination' => $pagination,
                'status' => $status
            ];
            
            require_once APP_PATH . '/views/author/posts.php';
            
        } catch (Exception $e) {
            logError('AuthorController::posts error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Formularz tworzenia nowego postu
     */
    public function create()
    {
        try {
            $categories = $this->categoryModel->getActiveCategories();
            $tags = $this->tagModel->getPopularTags();
            
            $data = [
                'categories' => $categories,
                'tags' => $tags,
                'post' => null
            ];
            
            require_once APP_PATH . '/views/author/create.php';
            
        } catch (Exception $e) {
            logError('AuthorController::create error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Zapisanie nowego postu
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
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $tags = $_POST['tags'] ?? [];
            $status = $_POST['status'] ?? 'draft';
            $allowComments = isset($_POST['allow_comments']);
            $isFeatured = isset($_POST['is_featured']);
            
            if (empty($title) || strlen($title) < 5) {
                setFlashMessage('error', 'Tytuł musi mieć co najmniej 5 znaków.');
                redirect('/author/posts/create');
                return;
            }
            
            if (empty($content) || strlen($content) < 50) {
                setFlashMessage('error', 'Treść musi mieć co najmniej 50 znaków.');
                redirect('/author/posts/create');
                return;
            }
            
            // Generowanie sluga
            $slug = generateSlug($title);
            $originalSlug = $slug;
            $counter = 1;
            
            // Sprawdzenie unikalności sluga
            while ($this->postModel->whereFirst('slug', $slug)) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            
            // Przygotowanie danych postu
            $postData = [
                'user_id' => $userId,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'title' => sanitizeInput($title),
                'slug' => $slug,
                'excerpt' => sanitizeInput($excerpt),
                'content' => $content, // Nie sanityzujemy content, bo może zawierać HTML
                'status' => $status,
                'allow_comments' => $allowComments,
                'is_featured' => $isFeatured,
                'published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null
            ];
            
            // Obsługa uploadu obrazka
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $featuredImage = $this->uploadFeaturedImage($_FILES['featured_image']);
                if ($featuredImage) {
                    $postData['featured_image'] = $featuredImage;
                }
            }
            
            // Zapisanie postu
            $postId = $this->postModel->create($postData);
            
            if ($postId) {
                // Dodanie tagów
                if (!empty($tags)) {
                    $this->postModel->syncTags($postId, $tags);
                }
                
                setFlashMessage('success', 'Post został utworzony.');
                redirect("/author/posts/{$postId}/edit");
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas tworzenia postu.');
                redirect('/author/posts/create');
            }
            
        } catch (Exception $e) {
            logError('AuthorController::store error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas tworzenia postu.');
            redirect('/author/posts/create');
        }
    }
    
    /**
     * Formularz edycji postu
     */
    public function edit($postId)
    {
        try {
            $userId = $_SESSION['user_id'];
            
            $post = $this->postModel->find($postId);
            if (!$post || $post['user_id'] != $userId) {
                render404();
                return;
            }
            
            $categories = $this->categoryModel->getActiveCategories();
            $tags = $this->tagModel->getPopularTags();
            $postTags = $this->postModel->getPostTags($postId);
            
            $data = [
                'post' => $post,
                'categories' => $categories,
                'tags' => $tags,
                'postTags' => $postTags
            ];
            
            require_once APP_PATH . '/views/author/edit.php';
            
        } catch (Exception $e) {
            logError('AuthorController::edit error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Aktualizacja postu
     */
    public function update($postId)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $userId = $_SESSION['user_id'];
            
            $post = $this->postModel->find($postId);
            if (!$post || $post['user_id'] != $userId) {
                render404();
                return;
            }
            
            // Walidacja danych
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $excerpt = trim($_POST['excerpt'] ?? '');
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $tags = $_POST['tags'] ?? [];
            $status = $_POST['status'] ?? 'draft';
            $allowComments = isset($_POST['allow_comments']);
            $isFeatured = isset($_POST['is_featured']);
            
            if (empty($title) || strlen($title) < 5) {
                setFlashMessage('error', 'Tytuł musi mieć co najmniej 5 znaków.');
                redirect("/author/posts/{$postId}/edit");
                return;
            }
            
            if (empty($content) || strlen($content) < 50) {
                setFlashMessage('error', 'Treść musi mieć co najmniej 50 znaków.');
                redirect("/author/posts/{$postId}/edit");
                return;
            }
            
            // Generowanie nowego sluga jeśli tytuł się zmienił
            $slug = $post['slug'];
            if ($title !== $post['title']) {
                $slug = generateSlug($title);
                $originalSlug = $slug;
                $counter = 1;
                
                while ($this->postModel->whereFirst('slug', $slug) && $slug !== $post['slug']) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }
            
            // Przygotowanie danych do aktualizacji
            $updateData = [
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'title' => sanitizeInput($title),
                'slug' => $slug,
                'excerpt' => sanitizeInput($excerpt),
                'content' => $content,
                'status' => $status,
                'allow_comments' => $allowComments,
                'is_featured' => $isFeatured
            ];
            
            // Ustawienie daty publikacji jeśli status zmienił się na published
            if ($status === 'published' && $post['status'] !== 'published') {
                $updateData['published_at'] = date('Y-m-d H:i:s');
            }
            
            // Obsługa uploadu obrazka
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $featuredImage = $this->uploadFeaturedImage($_FILES['featured_image']);
                if ($featuredImage) {
                    $updateData['featured_image'] = $featuredImage;
                    
                    // Usunięcie starego obrazka
                    if ($post['featured_image'] && file_exists(UPLOADS_PATH . '/posts/' . $post['featured_image'])) {
                        unlink(UPLOADS_PATH . '/posts/' . $post['featured_image']);
                    }
                }
            }
            
            // Aktualizacja postu
            if ($this->postModel->update($postId, $updateData)) {
                // Aktualizacja tagów
                $this->postModel->syncTags($postId, $tags);
                
                setFlashMessage('success', 'Post został zaktualizowany.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas aktualizacji postu.');
            }
            
            redirect("/author/posts/{$postId}/edit");
            
        } catch (Exception $e) {
            logError('AuthorController::update error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas aktualizacji postu.');
            redirect("/author/posts/{$postId}/edit");
        }
    }
    
    /**
     * Usunięcie postu
     */
    public function destroy($postId)
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $userId = $_SESSION['user_id'];
            
            $post = $this->postModel->find($postId);
            if (!$post || $post['user_id'] != $userId) {
                render404();
                return;
            }
            
            // Usunięcie postu (soft delete)
            if ($this->postModel->update($postId, [
                'status' => 'archived',
                'deleted_at' => date('Y-m-d H:i:s')
            ])) {
                setFlashMessage('success', 'Post został usunięty.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas usuwania postu.');
            }
            
            redirect('/author/posts');
            
        } catch (Exception $e) {
            logError('AuthorController::destroy error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas usuwania postu.');
            redirect('/author/posts');
        }
    }
    
    /**
     * Upload obrazka wyróżniającego
     */
    private function uploadFeaturedImage($file)
    {
        try {
            // Sprawdzenie typu pliku
            if (!isImage($file)) {
                setFlashMessage('error', 'Dozwolone są tylko pliki obrazów (JPG, PNG, GIF, WebP).');
                return false;
            }
            
            // Sprawdzenie rozmiaru pliku
            if ($file['size'] > MAX_FILE_SIZE) {
                setFlashMessage('error', 'Plik jest za duży (maksymalnie 5MB).');
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
                createThumbnail('posts/' . $fileName, 'posts/thumb_' . $fileName, 300, 200);
                
                return $fileName;
            }
            
            return false;
            
        } catch (Exception $e) {
            logError('AuthorController::uploadFeaturedImage error: ' . $e->getMessage());
            return false;
        }
    }
}
