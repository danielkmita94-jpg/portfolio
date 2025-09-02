namespace App\Controllers;  
<?php
/**
 * Feed Controller
 * Kontroler do generowania RSS i Atom feed
 */

class FeedController
{
    private $postModel;
    
    public function __construct()
    {
        $this->postModel = new Post();
    }
    
    /**
     * RSS feed
     */
    public function rss()
    {
        try {
            header('Content-Type: application/rss+xml; charset=utf-8');
            
            $posts = $this->postModel->getPublishedPostsForFeed(20);
            
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
            echo '<channel>' . "\n";
            echo '<title>' . htmlspecialchars(SITE_NAME) . '</title>' . "\n";
            echo '<link>' . htmlspecialchars(BASE_URL) . '</link>' . "\n";
            echo '<description>' . htmlspecialchars(SITE_DESCRIPTION) . '</description>' . "\n";
            echo '<language>pl</language>' . "\n";
            echo '<lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
            echo '<atom:link href="' . htmlspecialchars(BASE_URL . '/feed') . '" rel="self" type="application/rss+xml" />' . "\n";
            
            foreach ($posts as $post) {
                echo '<item>' . "\n";
                echo '<title>' . htmlspecialchars($post['title']) . '</title>' . "\n";
                echo '<link>' . htmlspecialchars(BASE_URL . '/posts/' . $post['slug']) . '</link>' . "\n";
                echo '<guid>' . htmlspecialchars(BASE_URL . '/posts/' . $post['slug']) . '</guid>' . "\n";
                echo '<pubDate>' . date('r', strtotime($post['published_at'])) . '</pubDate>' . "\n";
                echo '<description><![CDATA[' . $post['excerpt'] . ']]></description>' . "\n";
                
                if ($post['category_name']) {
                    echo '<category>' . htmlspecialchars($post['category_name']) . '</category>' . "\n";
                }
                
                echo '</item>' . "\n";
            }
            
            echo '</channel>' . "\n";
            echo '</rss>';
            
        } catch (Exception $e) {
            logError('FeedController::rss error: ' . $e->getMessage());
            http_response_code(500);
            echo '<?xml version="1.0" encoding="UTF-8"?><error>RSS feed generation failed</error>';
        }
    }
    
    /**
     * Atom feed
     */
    public function atom()
    {
        try {
            header('Content-Type: application/atom+xml; charset=utf-8');
            
            $posts = $this->postModel->getPublishedPostsForFeed(20);
            
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
            echo '<title>' . htmlspecialchars(SITE_NAME) . '</title>' . "\n";
            echo '<link href="' . htmlspecialchars(BASE_URL) . '" />' . "\n";
            echo '<link href="' . htmlspecialchars(BASE_URL . '/feed/atom') . '" rel="self" />' . "\n";
            echo '<id>' . htmlspecialchars(BASE_URL) . '</id>' . "\n";
            echo '<updated>' . date('c') . '</updated>' . "\n";
            echo '<subtitle>' . htmlspecialchars(SITE_DESCRIPTION) . '</subtitle>' . "\n";
            
            foreach ($posts as $post) {
                echo '<entry>' . "\n";
                echo '<title>' . htmlspecialchars($post['title']) . '</title>' . "\n";
                echo '<link href="' . htmlspecialchars(BASE_URL . '/posts/' . $post['slug']) . '" />' . "\n";
                echo '<id>' . htmlspecialchars(BASE_URL . '/posts/' . $post['slug']) . '</id>' . "\n";
                echo '<updated>' . date('c', strtotime($post['updated_at'])) . '</updated>' . "\n";
                echo '<published>' . date('c', strtotime($post['published_at'])) . '</published>' . "\n";
                echo '<summary type="html"><![CDATA[' . $post['excerpt'] . ']]></summary>' . "\n";
                echo '<content type="html"><![CDATA[' . $post['content'] . ']]></content>' . "\n";
                
                if ($post['author_name']) {
                    echo '<author>' . "\n";
                    echo '<name>' . htmlspecialchars($post['author_name']) . '</name>' . "\n";
                    echo '</author>' . "\n";
                }
                
                if ($post['category_name']) {
                    echo '<category term="' . htmlspecialchars($post['category_name']) . '" />' . "\n";
                }
                
                echo '</entry>' . "\n";
            }
            
            echo '</feed>';
            
        } catch (Exception $e) {
            logError('FeedController::atom error: ' . $e->getMessage());
            http_response_code(500);
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Atom feed generation failed</error>';
        }
    }
    
    /**
     * JSON feed
     */
    public function json()
    {
        try {
            header('Content-Type: application/json; charset=utf-8');
            
            $posts = $this->postModel->getPublishedPostsForFeed(20);
            
            $feed = [
                'version' => 'https://jsonfeed.org/version/1.1',
                'title' => SITE_NAME,
                'description' => SITE_DESCRIPTION,
                'home_page_url' => BASE_URL,
                'feed_url' => BASE_URL . '/feed/json',
                'language' => 'pl',
                'updated' => date('c'),
                'items' => []
            ];
            
            foreach ($posts as $post) {
                $item = [
                    'id' => BASE_URL . '/posts/' . $post['slug'],
                    'url' => BASE_URL . '/posts/' . $post['slug'],
                    'title' => $post['title'],
                    'content_html' => $post['content'],
                    'summary' => $post['excerpt'],
                    'date_published' => date('c', strtotime($post['published_at'])),
                    'date_modified' => date('c', strtotime($post['updated_at']))
                ];
                
                if ($post['author_name']) {
                    $item['authors'] = [
                        [
                            'name' => $post['author_name']
                        ]
                    ];
                }
                
                if ($post['category_name']) {
                    $item['tags'] = [$post['category_name']];
                }
                
                $feed['items'][] = $item;
            }
            
            echo json_encode($feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            logError('FeedController::json error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'JSON feed generation failed']);
        }
    }
}
