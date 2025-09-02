<?php
/**
 * Category Model
 * Model kategorii blogowych
 */

namespace App\Models;

class Category extends Model
{
    protected $table = 'categories';
    protected $fillable = ['name', 'slug', 'description', 'color', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    
    /**
     * Pobranie aktywnych kategorii
     */
    public function getActiveCategories()
    {
        return $this->where('is_active', true);
    }
    
    /**
     * Pobranie kategorii z liczbą postów
     */
    public function getCategoriesWithPostCount()
    {
        $sql = "SELECT c.*, COUNT(p.id) as post_count
                FROM categories c
                LEFT JOIN posts p ON c.id = p.category_id AND p.status = 'published'
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY c.name";
        
        return $this->raw($sql);
    }
    
    /**
     * Pobranie kategorii po slug
     */
    public function getBySlug($slug)
    {
        return $this->whereFirst('slug', $slug);
    }
    
    /**
     * Utworzenie nowej kategorii
     */
    public function createCategory($data)
    {
        // Generowanie slug
        $data['slug'] = $this->generateUniqueSlug($data['name']);
        
        return $this->create($data);
    }
    
    /**
     * Aktualizacja kategorii
     */
    public function updateCategory($id, $data)
    {
        // Generowanie nowego slug jeśli nazwa się zmieniła
        if (isset($data['name'])) {
            $currentCategory = $this->find($id);
            if ($currentCategory['name'] !== $data['name']) {
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
        $sql = "SELECT COUNT(*) FROM categories WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->raw($sql, $params);
        return $result[0]['COUNT(*)'] > 0;
    }
    
    /**
     * Pobranie statystyk kategorii
     */
    public function getStats()
    {
        $sql = "SELECT c.name, c.slug, c.color,
                       COUNT(p.id) as post_count,
                       SUM(p.view_count) as total_views,
                       AVG(p.view_count) as avg_views
                FROM categories c
                LEFT JOIN posts p ON c.id = p.category_id AND p.status = 'published'
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY post_count DESC";
        
        return $this->raw($sql);
    }
    
    /**
     * Pobranie najpopularniejszych kategorii
     */
    public function getPopularCategories($limit = 5)
    {
        $sql = "SELECT c.name, c.slug, c.color,
                       COUNT(p.id) as post_count,
                       SUM(p.view_count) as total_views
                FROM categories c
                LEFT JOIN posts p ON c.id = p.category_id AND p.status = 'published'
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY total_views DESC, post_count DESC
                LIMIT ?";
        
        return $this->raw($sql, [$limit]);
    }
    
    /**
     * Sprawdzenie czy kategoria może być usunięta
     */
    public function canBeDeleted($id)
    {
        $sql = "SELECT COUNT(*) FROM posts WHERE category_id = ?";
        $result = $this->raw($sql, [$id]);
        return $result[0]['COUNT(*)'] == 0;
    }
    
    /**
     * Usunięcie kategorii (tylko jeśli nie ma postów)
     */
    public function deleteCategory($id)
    {
        if ($this->canBeDeleted($id)) {
            return $this->delete($id);
        }
        return false;
    }
    
    /**
     * Aktywacja/dezaktywacja kategorii
     */
    public function toggleActive($id)
    {
        $category = $this->find($id);
        if ($category) {
            $newStatus = !$category['is_active'];
            return $this->update($id, ['is_active' => $newStatus]);
        }
        return false;
    }
}












