<?php
/**
 * Tag Model
 * Model tagów blogowych
 */

namespace App\Models;

class Tag extends Model
{
    protected $table = 'tags';
    protected $fillable = ['name', 'slug', 'description'];
    
    /**
     * Pobranie wszystkich tagów z liczbą postów
     */
    public function getAllTagsWithPostCount()
    {
        $sql = "SELECT t.*, COUNT(pt.post_id) as post_count
                FROM tags t
                LEFT JOIN post_tags pt ON t.id = pt.tag_id
                LEFT JOIN posts p ON pt.post_id = p.id AND p.status = 'published'
                GROUP BY t.id
                ORDER BY post_count DESC, t.name ASC";
        
        return $this->raw($sql);
    }
    
    /**
     * Pobranie popularnych tagów
     */
    public function getPopularTags($limit = 10)
    {
        $sql = "SELECT t.*, COUNT(pt.post_id) as post_count
                FROM tags t
                LEFT JOIN post_tags pt ON t.id = pt.tag_id
                LEFT JOIN posts p ON pt.post_id = p.id AND p.status = 'published'
                GROUP BY t.id
                HAVING post_count > 0
                ORDER BY post_count DESC, t.name ASC
                LIMIT ?";
        
        return $this->raw($sql, [$limit]);
    }
    
    /**
     * Pobranie tagu po slug
     */
    public function getBySlug($slug)
    {
        return $this->whereFirst('slug', $slug);
    }
    
    /**
     * Utworzenie nowego tagu
     */
    public function createTag($data)
    {
        // Generowanie slug
        $data['slug'] = $this->generateUniqueSlug($data['name']);
        
        return $this->create($data);
    }
    
    /**
     * Aktualizacja tagu
     */
    public function updateTag($id, $data)
    {
        // Generowanie nowego slug jeśli nazwa się zmieniła
        if (isset($data['name'])) {
            $currentTag = $this->find($id);
            if ($currentTag['name'] !== $data['name']) {
                $data['slug'] = $this->generateUniqueSlug($data['name'], $id);
            }
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Generowanie unikalnego slug
     */
    private function generateUniqueSlug($name, $excludeId = null)
    {
        $baseSlug = generateSlug($name);
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
        $sql = "SELECT COUNT(*) FROM tags WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->raw($sql, $params);
        return $result[0]['COUNT(*)'] > 0;
    }
    
    /**
     * Pobranie tagów dla posta
     */
    public function getTagsForPost($postId)
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
    public function addTagsToPost($postId, $tagIds)
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
     * Wyszukiwanie tagów
     */
    public function searchTags($query, $limit = 10)
    {
        $sql = "SELECT t.*, COUNT(pt.post_id) as post_count
                FROM tags t
                LEFT JOIN post_tags pt ON t.id = pt.tag_id
                LEFT JOIN posts p ON pt.post_id = p.id AND p.status = 'published'
                WHERE t.name LIKE ?
                GROUP BY t.id
                ORDER BY post_count DESC, t.name ASC
                LIMIT ?";
        
        $searchTerm = "%{$query}%";
        return $this->raw($sql, [$searchTerm, $limit]);
    }
    
    /**
     * Pobranie sugerowanych tagów
     */
    public function getSuggestedTags($query, $limit = 5)
    {
        $sql = "SELECT t.* FROM tags t
                WHERE t.name LIKE ?
                ORDER BY t.name ASC
                LIMIT ?";
        
        $searchTerm = "%{$query}%";
        return $this->raw($sql, [$searchTerm, $limit]);
    }
    
    /**
     * Utworzenie tagu jeśli nie istnieje
     */
    public function findOrCreate($name)
    {
        $tag = $this->whereFirst('name', $name);
        
        if (!$tag) {
            $tagId = $this->createTag(['name' => $name]);
            $tag = $this->find($tagId);
        }
        
        return $tag;
    }
    
    /**
     * Pobranie statystyk tagów
     */
    public function getStats()
    {
        $stats = [];
        
        // Liczba wszystkich tagów
        $stats['total_tags'] = $this->count();
        
        // Liczba tagów używanych w postach
        $sql = "SELECT COUNT(DISTINCT t.id) FROM tags t
                JOIN post_tags pt ON t.id = pt.tag_id
                JOIN posts p ON pt.post_id = p.id AND p.status = 'published'";
        $result = $this->raw($sql);
        $stats['used_tags'] = $result[0]['COUNT(DISTINCT t.id)'];
        
        // Liczba nieużywanych tagów
        $stats['unused_tags'] = $stats['total_tags'] - $stats['used_tags'];
        
        // Najpopularniejsze tagi
        $stats['popular_tags'] = $this->getPopularTags(5);
        
        return $stats;
    }
    
    /**
     * Sprawdzenie czy tag może być usunięty
     */
    public function canBeDeleted($id)
    {
        $sql = "SELECT COUNT(*) FROM post_tags WHERE tag_id = ?";
        $result = $this->raw($sql, [$id]);
        return $result[0]['COUNT(*)'] == 0;
    }
    
    /**
     * Usunięcie tagu (tylko jeśli nie jest używany)
     */
    public function deleteTag($id)
    {
        if ($this->canBeDeleted($id)) {
            return $this->delete($id);
        }
        return false;
    }
    
    /**
     * Pobranie tagów z liczbą postów w kategorii
     */
    public function getTagsByCategory($categoryId, $limit = 10)
    {
        $sql = "SELECT t.*, COUNT(pt.post_id) as post_count
                FROM tags t
                JOIN post_tags pt ON t.id = pt.tag_id
                JOIN posts p ON pt.post_id = p.id
                WHERE p.category_id = ? AND p.status = 'published'
                GROUP BY t.id
                ORDER BY post_count DESC, t.name ASC
                LIMIT ?";
        
        return $this->raw($sql, [$categoryId, $limit]);
    }
    
    /**
     * Pobranie powiązanych tagów
     */
    public function getRelatedTags($tagId, $limit = 5)
    {
        $sql = "SELECT t.*, COUNT(pt2.post_id) as common_posts
                FROM tags t
                JOIN post_tags pt1 ON t.id = pt1.tag_id
                JOIN post_tags pt2 ON pt1.post_id = pt2.post_id
                WHERE pt2.tag_id = ? AND t.id != ?
                GROUP BY t.id
                ORDER BY common_posts DESC, t.name ASC
                LIMIT ?";
        
        return $this->raw($sql, [$tagId, $tagId, $limit]);
    }
    
    /**
     * Pobranie tagów z ostatnich postów
     */
    public function getRecentTags($days = 30, $limit = 10)
    {
        $sql = "SELECT t.*, COUNT(pt.post_id) as post_count
                FROM tags t
                JOIN post_tags pt ON t.id = pt.tag_id
                JOIN posts p ON pt.post_id = p.id
                WHERE p.status = 'published' 
                AND p.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY t.id
                ORDER BY post_count DESC, t.name ASC
                LIMIT ?";
        
        return $this->raw($sql, [$days, $limit]);
    }
    
    /**
     * Pobranie tagów z trendami (wzrost/spadek użycia)
     */
    public function getTrendingTags($days = 7, $limit = 10)
    {
        $sql = "SELECT t.*, 
                       COUNT(CASE WHEN p.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN pt.post_id END) as recent_posts,
                       COUNT(CASE WHEN p.published_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND p.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN pt.post_id END) as previous_posts
                FROM tags t
                JOIN post_tags pt ON t.id = pt.tag_id
                JOIN posts p ON pt.post_id = p.id
                WHERE p.status = 'published' 
                AND p.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY t.id
                HAVING recent_posts > 0
                ORDER BY (recent_posts - previous_posts) DESC, recent_posts DESC
                LIMIT ?";
        
        return $this->raw($sql, [$days, $days, $days * 2, $days * 2, $limit]);
    }
    
    /**
     * Pobranie tagów z autouzupełnianiem
     */
    public function getAutocompleteTags($query, $limit = 10)
    {
        $sql = "SELECT t.name, t.slug, COUNT(pt.post_id) as post_count
                FROM tags t
                LEFT JOIN post_tags pt ON t.id = pt.tag_id
                LEFT JOIN posts p ON pt.post_id = p.id AND p.status = 'published'
                WHERE t.name LIKE ?
                GROUP BY t.id
                ORDER BY post_count DESC, t.name ASC
                LIMIT ?";
        
        $searchTerm = "%{$query}%";
        return $this->raw($sql, [$searchTerm, $limit]);
    }
}
