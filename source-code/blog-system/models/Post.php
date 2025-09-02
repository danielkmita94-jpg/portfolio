<?php
/**
 * Post Model
 * Model postów blogowych
 */

namespace App\Models;

class Post extends Model
{
    protected $table = 'posts';
    protected $fillable = [
        'user_id', 'category_id', 'title', 'slug', 'excerpt', 'content',
        'featured_image', 'thumbnail', 'meta_title', 'meta_description',
        'status', 'is_featured', 'allow_comments', 'published_at'
    ];
    protected $casts = [
        'is_featured' => 'boolean',
        'allow_comments' => 'boolean',
        'view_count' => 'integer',
        'published_at' => 'datetime'
    ];
    
    /**
     * Pobranie opublikowanych postów z paginacją
     */
    public function getPublishedPosts($page = 1, $perPage = 10, $categoryId = null, $search = null)
    {
        $where = 'status = ?';
        $params = ['published'];
        
        if ($categoryId) {
            $where .= ' AND category_id = ?';
            $params[] = $categoryId;
        }
        
        if ($search) {
            $where .= ' AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)';
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        return $this->paginate($page, $perPage, $where, $params);
    }
    
    /**
     * Pobranie wyróżnionych postów
     */
    public function getFeaturedPosts($limit = 3)
    {
        $cacheKey = "featured_posts_{$limit}";
        
        return $this->remember($cacheKey, 1800, function() use ($limit) {
            $sql = "SELECT p.*, u.username as author_username, u.first_name, u.last_name,
                           c.name as category_name, c.slug as category_slug, c.color as category_color,
                           COUNT(DISTINCT cm.id) as comment_count, COUNT(DISTINCT pl.user_id) as like_count
                    FROM posts p
                    LEFT JOIN users u ON p.user_id = u.id
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN comments cm ON p.id = cm.post_id AND cm.status = 'approved'
                    LEFT JOIN post_likes pl ON p.id = pl.post_id
                    WHERE p.status = 'published' AND p.is_featured = 1
                    GROUP BY p.id
                    ORDER BY p.published_at DESC
                    LIMIT ?";
            
            return $this->raw($sql, [$limit]);
        });
    }
    
    /**
     * Pobranie najnowszych postów
     */
    public function getLatestPosts($limit = 6)
    {
        $cacheKey = "latest_posts_{$limit}";
        
        return $this->remember($cacheKey, 900, function() use ($limit) {
            $sql = "SELECT p.*, u.username as author_username, u.first_name, u.last_name,
                           c.name as category_name, c.slug as category_slug, c.color as category_color,
                           COUNT(DISTINCT cm.id) as comment_count, COUNT(DISTINCT pl.user_id) as like_count
                    FROM posts p
                    LEFT JOIN users u ON p.user_id = u.id
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN comments cm ON p.id = cm.post_id AND cm.status = 'approved'
                    LEFT JOIN post_likes pl ON p.id = pl.post_id
                    WHERE p.status = 'published'
                    GROUP BY p.id
                    ORDER BY p.published_at DESC
                    LIMIT ?";
            
            return $this->raw($sql, [$limit]);
        });
    }
    
    /**
     * Pobranie popularnych postów
     */
    public function getPopularPosts($limit = 6)
    {
        $sql = "SELECT p.*, u.username as author_username, u.first_name, u.last_name,
                       c.name as category_name, c.slug as category_slug, c.color as category_color,
                       COUNT(DISTINCT cm.id) as comment_count, COUNT(DISTINCT pl.user_id) as like_count
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN comments cm ON p.id = cm.post_id AND cm.status = 'approved'
                LEFT JOIN post_likes pl ON p.id = pl.post_id
                WHERE p.status = 'published'
                GROUP BY p.id
                ORDER BY p.view_count DESC, p.published_at DESC
                LIMIT ?";
        
        return $this->raw($sql, [$limit]);
    }
    
    /**
     * Pobranie posta po slug
     */
    public function getBySlug($slug)
    {
        $sql = "SELECT p.*, u.username as author_username, u.first_name, u.last_name, u.avatar,
                       c.name as category_name, c.slug as category_slug, c.color as category_color,
                       COUNT(DISTINCT cm.id) as comment_count, COUNT(DISTINCT pl.user_id) as like_count
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN comments cm ON p.id = cm.post_id AND cm.status = 'approved'
                LEFT JOIN post_likes pl ON p.id = pl.post_id
                WHERE p.slug = ? AND p.status = 'published'
                GROUP BY p.id";
        
        $result = $this->raw($sql, [$slug]);
        return $result ? $result[0] : null;
    }
    
    /**
     * Pobranie postów z kategorii
     */
    public function getByCategory($categorySlug, $page = 1, $perPage = 10)
    {
        $sql = "SELECT p.*, u.username as author_username, u.first_name, u.last_name,
                       c.name as category_name, c.slug as category_slug, c.color as category_color,
                       COUNT(DISTINCT cm.id) as comment_count, COUNT(DISTINCT pl.user_id) as like_count
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN comments cm ON p.id = cm.post_id AND cm.status = 'approved'
                LEFT JOIN post_likes pl ON p.id = pl.post_id
                WHERE p.status = 'published' AND c.slug = ?
                GROUP BY p.id
                ORDER BY p.published_at DESC
                LIMIT ? OFFSET ?";
        
        $offset = ($page - 1) * $perPage;
        $posts = $this->raw($sql, [$categorySlug, $perPage, $offset]);
        
        // Liczba wszystkich postów w kategorii
        $countSql = "SELECT COUNT(*) FROM posts p
                     JOIN categories c ON p.category_id = c.id
                     WHERE p.status = 'published' AND c.slug = ?";
        $total = $this->raw($countSql, [$categorySlug])[0]['COUNT(*)'];
        
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
     * Pobranie postów z tagiem
     */
    public function getByTag($tagSlug, $page = 1, $perPage = 10)
    {
        $sql = "SELECT p.*, u.username as author_username, u.first_name, u.last_name,
                       c.name as category_name, c.slug as category_slug, c.color as category_color,
                       COUNT(DISTINCT cm.id) as comment_count, COUNT(DISTINCT pl.user_id) as like_count
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN comments cm ON p.id = cm.post_id AND cm.status = 'approved'
                LEFT JOIN post_likes pl ON p.id = pl.post_id
                JOIN post_tags pt ON p.id = pt.post_id
                JOIN tags t ON pt.tag_id = t.id
                WHERE p.status = 'published' AND t.slug = ?
                GROUP BY p.id
                ORDER BY p.published_at DESC
                LIMIT ? OFFSET ?";
        
        $offset = ($page - 1) * $perPage;
        $posts = $this->raw($sql, [$tagSlug, $perPage, $offset]);
        
        // Liczba wszystkich postów z tagiem
        $countSql = "SELECT COUNT(DISTINCT p.id) FROM posts p
                     JOIN post_tags pt ON p.id = pt.post_id
                     JOIN tags t ON pt.tag_id = t.id
                     WHERE p.status = 'published' AND t.slug = ?";
        $total = $this->raw($countSql, [$tagSlug])[0]['COUNT(DISTINCT p.id)'];
        
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
     * Wyszukiwanie postów
     */
    public function search($query, $page = 1, $perPage = 10)
    {
        $sql = "SELECT p.*, u.username as author_username, u.first_name, u.last_name,
                       c.name as category_name, c.slug as category_slug, c.color as category_color,
                       COUNT(DISTINCT cm.id) as comment_count, COUNT(DISTINCT pl.user_id) as like_count
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN comments cm ON p.id = cm.post_id AND cm.status = 'approved'
                LEFT JOIN post_likes pl ON p.id = pl.post_id
                WHERE p.status = 'published' 
                AND (p.title LIKE ? OR p.content LIKE ? OR p.excerpt LIKE ?)
                GROUP BY p.id
                ORDER BY p.published_at DESC
                LIMIT ? OFFSET ?";
        
        $searchTerm = "%{$query}%";
        $offset = ($page - 1) * $perPage;
        $posts = $this->raw($sql, [$searchTerm, $searchTerm, $searchTerm, $perPage, $offset]);
        
        // Liczba wszystkich wyników
        $countSql = "SELECT COUNT(*) FROM posts 
                     WHERE status = 'published' 
                     AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
        $total = $this->raw($countSql, [$searchTerm, $searchTerm, $searchTerm])[0]['COUNT(*)'];
        
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
     * Pobranie powiązanych postów
     */
    public function getRelatedPosts($postId, $limit = 3)
    {
        $sql = "SELECT p.*, u.username as author_username,
                       c.name as category_name, c.slug as category_slug
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.status = 'published' 
                AND p.id != ?
                AND (p.category_id = (SELECT category_id FROM posts WHERE id = ?)
                     OR p.id IN (
                         SELECT DISTINCT pt2.post_id 
                         FROM post_tags pt1
                         JOIN post_tags pt2 ON pt1.tag_id = pt2.tag_id
                         WHERE pt1.post_id = ? AND pt2.post_id != ?
                     ))
                ORDER BY p.published_at DESC
                LIMIT ?";
        
        return $this->raw($sql, [$postId, $postId, $postId, $postId, $limit]);
    }
    
    /**
     * Zwiększenie licznika wyświetleń
     */
    public function incrementViewCount($postId)
    {
        $sql = "UPDATE posts SET view_count = view_count + 1 WHERE id = ?";
        return $this->raw($sql, [$postId]);
    }
    
    /**
     * Pobranie tagów posta
     */
    public function getTags($postId)
    {
        $sql = "SELECT t.* FROM tags t
                JOIN post_tags pt ON t.id = pt.tag_id
                WHERE pt.post_id = ?
                ORDER BY t.name";
        
        return $this->raw($sql, [$postId]);
    }
    
    /**
     * Dodanie tagów do posta
     */
    public function addTags($postId, $tagIds)
    {
        // Usunięcie istniejących tagów
        $this->raw("DELETE FROM post_tags WHERE post_id = ?", [$postId]);
        
        // Dodanie nowych tagów
        foreach ($tagIds as $tagId) {
            $this->raw("INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)", [$postId, $tagId]);
        }
        
        return true;
    }
    
    /**
     * Utworzenie nowego posta
     */
    public function createPost($data, $tagIds = [])
    {
        // Generowanie slug
        $data['slug'] = $this->generateUniqueSlug($data['title']);
        
        // Ustawienie daty publikacji
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }
        
        // Zapisanie posta
        $postId = $this->create($data);
        
        if ($postId && !empty($tagIds)) {
            $this->addTags($postId, $tagIds);
        }
        
        return $postId;
    }
    
    /**
     * Aktualizacja posta
     */
    public function updatePost($postId, $data, $tagIds = [])
    {
        // Generowanie nowego slug jeśli tytuł się zmienił
        if (isset($data['title'])) {
            $currentPost = $this->find($postId);
            if ($currentPost['title'] !== $data['title']) {
                $data['slug'] = $this->generateUniqueSlug($data['title'], $postId);
            }
        }
        
        // Ustawienie daty publikacji
        if (isset($data['status']) && $data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }
        
        // Aktualizacja posta
        $result = $this->update($postId, $data);
        
        if ($result && !empty($tagIds)) {
            $this->addTags($postId, $tagIds);
        }
        
        return $result;
    }
    
    /**
     * Generowanie unikalnego slug
     */
    private function generateUniqueSlug($title, $excludeId = null)
    {
        $baseSlug = generateSlug($title);
        $slug = $baseSlug;
        $counter = 1;
        
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Sprawdzenie czy slug istnieje
     */
    private function slugExists($slug, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) FROM posts WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->raw($sql, $params);
        return $result[0]['COUNT(*)'] > 0;
    }
    
    /**
     * Pobranie statystyk postów
     */
    public function getStats()
    {
        $stats = [];
        
        // Liczba wszystkich postów
        $stats['total_posts'] = $this->count();
        
        // Liczba opublikowanych postów
        $stats['published_posts'] = $this->count('status = ?', ['published']);
        
        // Liczba szkiców
        $stats['draft_posts'] = $this->count('status = ?', ['draft']);
        
        // Liczba zarchiwizowanych postów
        $stats['archived_posts'] = $this->count('status = ?', ['archived']);
        
        // Liczba wyróżnionych postów
        $stats['featured_posts'] = $this->count('is_featured = ?', [true]);
        
        // Suma wyświetleń
        $result = $this->raw("SELECT SUM(view_count) as total_views FROM posts");
        $stats['total_views'] = $result[0]['total_views'] ?? 0;
        
        // Średnia wyświetleń na post
        $stats['avg_views'] = $stats['published_posts'] > 0 ? round($stats['total_views'] / $stats['published_posts'], 2) : 0;
        
        // Najpopularniejszy post
        $result = $this->raw("SELECT title, view_count FROM posts WHERE status = 'published' ORDER BY view_count DESC LIMIT 1");
        $stats['most_popular_post'] = $result ? $result[0] : null;
        
        return $stats;
    }
    
    /**
     * Pobranie postów do moderacji
     */
    public function getPostsForModeration($page = 1, $perPage = 10)
    {
        $where = 'status = ?';
        $params = ['draft'];
        
        return $this->paginate($page, $perPage, $where, $params);
    }
    
    /**
     * Zatwierdzenie posta
     */
    public function approvePost($postId)
    {
        return $this->update($postId, [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Pobranie postów użytkownika
     */
    public function getUserPosts($userId, $page = 1, $perPage = 10)
    {
        $where = 'user_id = ?';
        $params = [$userId];
        
        return $this->paginate($page, $perPage, $where, $params);
    }
    
    /**
     * Synchronizacja tagów posta
     */
    public function syncTags($postId, $tagIds)
    {
        // Usunięcie wszystkich tagów posta
        $sql = "DELETE FROM post_tags WHERE post_id = ?";
        $this->raw($sql, [$postId]);
        
        // Dodanie nowych tagów
        if (!empty($tagIds)) {
            $values = [];
            $params = [];
            
            foreach ($tagIds as $tagId) {
                $values[] = "(?, ?)";
                $params[] = $postId;
                $params[] = $tagId;
            }
            
            $sql = "INSERT INTO post_tags (post_id, tag_id) VALUES " . implode(', ', $values);
            $this->raw($sql, $params);
        }
        
        return true;
    }
    
}
