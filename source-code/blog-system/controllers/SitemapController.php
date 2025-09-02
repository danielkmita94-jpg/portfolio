namespace App\Controllers;  
<?php
/**
 * Sitemap Controller
 * Kontroler do generowania sitemap XML
 */

class SitemapController
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
     * Główna sitemap
     */
    public function index()
    {
        try {
            header('Content-Type: application/xml; charset=utf-8');
            
            $posts = $this->postModel->getPublishedPostsForSitemap();
            $categories = $this->categoryModel->getActiveCategories();
            
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            
            // Strona główna
            echo $this->generateUrl(BASE_URL, '1.0', 'daily');
            
            // Strona z postami
            echo $this->generateUrl(BASE_URL . '/posts', '0.8', 'daily');
            
            // Strona wyszukiwania
            echo $this->generateUrl(BASE_URL . '/search', '0.6', 'weekly');
            
            // Strona kontakt
            echo $this->generateUrl(BASE_URL . '/contact', '0.5', 'monthly');
            
            // Strona o nas
            echo $this->generateUrl(BASE_URL . '/about', '0.5', 'monthly');
            
            // Posty
            foreach ($posts as $post) {
                $priority = $post['is_featured'] ? '0.9' : '0.7';
                $changefreq = $post['is_featured'] ? 'weekly' : 'monthly';
                $lastmod = date('Y-m-d', strtotime($post['updated_at']));
                
                echo $this->generateUrl(
                    BASE_URL . '/posts/' . $post['slug'],
                    $priority,
                    $changefreq,
                    $lastmod
                );
            }
            
            // Kategorie
            foreach ($categories as $category) {
                echo $this->generateUrl(
                    BASE_URL . '/category/' . $category['slug'],
                    '0.6',
                    'weekly'
                );
            }
            
            echo '</urlset>';
            
        } catch (Exception $e) {
            logError('SitemapController::index error: ' . $e->getMessage());
            http_response_code(500);
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Sitemap generation failed</error>';
        }
    }
    
    /**
     * Sitemap kategorii
     */
    public function categories()
    {
        try {
            header('Content-Type: application/xml; charset=utf-8');
            
            $categories = $this->categoryModel->getActiveCategories();
            
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            
            foreach ($categories as $category) {
                echo $this->generateUrl(
                    BASE_URL . '/category/' . $category['slug'],
                    '0.6',
                    'weekly',
                    date('Y-m-d', strtotime($category['updated_at']))
                );
            }
            
            echo '</urlset>';
            
        } catch (Exception $e) {
            logError('SitemapController::categories error: ' . $e->getMessage());
            http_response_code(500);
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Sitemap generation failed</error>';
        }
    }
    
    /**
     * Sitemap tagów
     */
    public function tags()
    {
        try {
            header('Content-Type: application/xml; charset=utf-8');
            
            $tags = $this->tagModel->getPopularTags();
            
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            
            foreach ($tags as $tag) {
                echo $this->generateUrl(
                    BASE_URL . '/tag/' . $tag['slug'],
                    '0.5',
                    'monthly',
                    date('Y-m-d', strtotime($tag['created_at']))
                );
            }
            
            echo '</urlset>';
            
        } catch (Exception $e) {
            logError('SitemapController::tags error: ' . $e->getMessage());
            http_response_code(500);
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Sitemap generation failed</error>';
        }
    }
    
    /**
     * Generowanie URL w sitemap
     */
    private function generateUrl($url, $priority = '0.5', $changefreq = 'monthly', $lastmod = null)
    {
        $xml = "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($url) . "</loc>\n";
        
        if ($lastmod) {
            $xml .= "    <lastmod>" . htmlspecialchars($lastmod) . "</lastmod>\n";
        }
        
        $xml .= "    <changefreq>" . htmlspecialchars($changefreq) . "</changefreq>\n";
        $xml .= "    <priority>" . htmlspecialchars($priority) . "</priority>\n";
        $xml .= "  </url>\n";
        
        return $xml;
    }
}
