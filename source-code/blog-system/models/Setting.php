<?php
/**
 * Setting Model
 * Model ustawień systemu
 */

namespace App\Models;

class Setting extends Model
{
    protected $table = 'settings';
    protected $fillable = ['setting_key', 'setting_value', 'setting_type', 'description', 'is_public'];
    protected $casts = ['is_public' => 'boolean'];
    
    /**
     * Pobranie ustawienia po kluczu
     */
    public function get($key, $default = null)
    {
        $setting = $this->whereFirst('setting_key', $key);
        
        if (!$setting) {
            return $default;
        }
        
        return $this->castValue($setting['setting_value'], $setting['setting_type']);
    }
    
    /**
     * Ustawienie wartości
     */
    public function set($key, $value, $type = 'string', $description = null, $isPublic = false)
    {
        $setting = $this->whereFirst('setting_key', $key);
        
        if ($setting) {
            // Aktualizacja istniejącego ustawienia
            return $this->update($setting['id'], [
                'setting_value' => $this->prepareValue($value, $type),
                'setting_type' => $type,
                'description' => $description,
                'is_public' => $isPublic
            ]);
        } else {
            // Utworzenie nowego ustawienia
            return $this->create([
                'setting_key' => $key,
                'setting_value' => $this->prepareValue($value, $type),
                'setting_type' => $type,
                'description' => $description,
                'is_public' => $isPublic
            ]);
        }
    }
    
    /**
     * Pobranie wszystkich ustawień publicznych
     */
    public function getPublicSettings()
    {
        $settings = $this->where('is_public', true);
        $result = [];
        
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = $this->castValue(
                $setting['setting_value'], 
                $setting['setting_type']
            );
        }
        
        return $result;
    }
    
    /**
     * Pobranie wszystkich ustawień
     */
    public function getAllSettings()
    {
        $settings = $this->all();
        $result = [];
        
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = [
                'value' => $this->castValue($setting['setting_value'], $setting['setting_type']),
                'type' => $setting['setting_type'],
                'description' => $setting['description'],
                'is_public' => $setting['is_public']
            ];
        }
        
        return $result;
    }
    
    /**
     * Usunięcie ustawienia
     */
    public function remove($key)
    {
        $setting = $this->whereFirst('setting_key', $key);
        
        if ($setting) {
            return $this->delete($setting['id']);
        }
        
        return false;
    }
    
    /**
     * Sprawdzenie czy ustawienie istnieje
     */
    public function has($key)
    {
        return $this->whereFirst('setting_key', $key) !== null;
    }
    
    /**
     * Przygotowanie wartości do zapisu
     */
    private function prepareValue($value, $type)
    {
        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            case 'json':
                return json_encode($value);
            case 'integer':
                return (string) (int) $value;
            case 'float':
                return (string) (float) $value;
            default:
                return (string) $value;
        }
    }
    
    /**
     * Konwersja wartości z bazy danych
     */
    private function castValue($value, $type)
    {
        switch ($type) {
            case 'boolean':
                return $value === '1' || $value === 'true' || $value === true;
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
    
    /**
     * Pobranie ustawień z grupy
     */
    public function getSettingsByGroup($group)
    {
        $sql = "SELECT * FROM settings WHERE setting_key LIKE ? ORDER BY setting_key";
        $settings = $this->raw($sql, [$group . '%']);
        
        $result = [];
        foreach ($settings as $setting) {
            $key = str_replace($group . '.', '', $setting['setting_key']);
            $result[$key] = $this->castValue($setting['setting_value'], $setting['setting_type']);
        }
        
        return $result;
    }
    
    /**
     * Ustawienie wielu wartości z grupy
     */
    public function setSettingsByGroup($group, $settings)
    {
        foreach ($settings as $key => $value) {
            $fullKey = $group . '.' . $key;
            $this->set($fullKey, $value);
        }
        
        return true;
    }
    
    /**
     * Inicjalizacja domyślnych ustawień
     */
    public function initializeDefaults()
    {
        $defaults = [
            // Ustawienia strony
            'site.name' => ['value' => 'Blog System', 'type' => 'string', 'description' => 'Nazwa strony', 'public' => true],
            'site.description' => ['value' => 'Profesjonalny system blogowy', 'type' => 'string', 'description' => 'Opis strony', 'public' => true],
            'site.keywords' => ['value' => 'blog, system, php, mysql', 'type' => 'string', 'description' => 'Słowa kluczowe', 'public' => true],
            'site.author' => ['value' => 'Blog System', 'type' => 'string', 'description' => 'Autor strony', 'public' => true],
            
            // Ustawienia postów
            'posts.per_page' => ['value' => 10, 'type' => 'integer', 'description' => 'Liczba postów na stronę', 'public' => true],
            'posts.excerpt_length' => ['value' => 150, 'type' => 'integer', 'description' => 'Długość skrótu posta', 'public' => false],
            'posts.allow_comments' => ['value' => true, 'type' => 'boolean', 'description' => 'Pozwolić na komentarze', 'public' => true],
            'posts.moderate_comments' => ['value' => true, 'type' => 'boolean', 'description' => 'Moderować komentarze', 'public' => false],
            'posts.auto_approve_comments' => ['value' => false, 'type' => 'boolean', 'description' => 'Automatycznie zatwierdzać komentarze', 'public' => false],
            
            // Ustawienia użytkowników
            'users.allow_registration' => ['value' => true, 'type' => 'boolean', 'description' => 'Pozwolić na rejestrację', 'public' => true],
            'users.email_verification' => ['value' => true, 'type' => 'boolean', 'description' => 'Wymagać weryfikacji email', 'public' => false],
            'users.default_role' => ['value' => 'user', 'type' => 'string', 'description' => 'Domyślna rola użytkownika', 'public' => false],
            
            // Ustawienia email
            'email.from_name' => ['value' => 'Blog System', 'type' => 'string', 'description' => 'Nazwa nadawcy email', 'public' => false],
            'email.from_address' => ['value' => 'noreply@blog-system.com', 'type' => 'string', 'description' => 'Adres nadawcy email', 'public' => false],
            'email.notifications' => ['value' => true, 'type' => 'boolean', 'description' => 'Wysyłać powiadomienia email', 'public' => false],
            
            // Ustawienia SEO
            'seo.enable_sitemap' => ['value' => true, 'type' => 'boolean', 'description' => 'Włączyć generowanie sitemap', 'public' => false],
            'seo.enable_rss' => ['value' => true, 'type' => 'boolean', 'description' => 'Włączyć RSS feed', 'public' => true],
            'seo.meta_description' => ['value' => 'Profesjonalny system blogowy', 'type' => 'string', 'description' => 'Domyślny meta description', 'public' => false],
            
            // Ustawienia cache
            'cache.enabled' => ['value' => true, 'type' => 'boolean', 'description' => 'Włączyć cache', 'public' => false],
            'cache.duration' => ['value' => 3600, 'type' => 'integer', 'description' => 'Czas trwania cache (sekundy)', 'public' => false],
            
            // Ustawienia bezpieczeństwa
            'security.csrf_protection' => ['value' => true, 'type' => 'boolean', 'description' => 'Ochrona CSRF', 'public' => false],
            'security.rate_limiting' => ['value' => true, 'type' => 'boolean', 'description' => 'Rate limiting', 'public' => false],
            'security.max_login_attempts' => ['value' => 5, 'type' => 'integer', 'description' => 'Maksymalna liczba prób logowania', 'public' => false],
            
            // Ustawienia wyświetlania
            'display.theme' => ['value' => 'default', 'type' => 'string', 'description' => 'Aktywny motyw', 'public' => true],
            'display.show_author' => ['value' => true, 'type' => 'boolean', 'description' => 'Pokazywać autora postów', 'public' => true],
            'display.show_date' => ['value' => true, 'type' => 'boolean', 'description' => 'Pokazywać datę postów', 'public' => true],
            'display.show_categories' => ['value' => true, 'type' => 'boolean', 'description' => 'Pokazywać kategorie', 'public' => true],
            'display.show_tags' => ['value' => true, 'type' => 'boolean', 'description' => 'Pokazywać tagi', 'public' => true],
            
            // Ustawienia społecznościowe
            'social.enable_likes' => ['value' => true, 'type' => 'boolean', 'description' => 'Włączyć polubienia', 'public' => true],
            'social.enable_sharing' => ['value' => true, 'type' => 'boolean', 'description' => 'Włączyć udostępnianie', 'public' => true],
            'social.facebook_app_id' => ['value' => '', 'type' => 'string', 'description' => 'Facebook App ID', 'public' => false],
            'social.twitter_username' => ['value' => '', 'type' => 'string', 'description' => 'Twitter username', 'public' => false],
            
            // Ustawienia analityki
            'analytics.google_analytics' => ['value' => '', 'type' => 'string', 'description' => 'Google Analytics ID', 'public' => false],
            'analytics.enable_tracking' => ['value' => false, 'type' => 'boolean', 'description' => 'Włączyć śledzenie', 'public' => false],
            
            // Ustawienia systemu
            'system.maintenance_mode' => ['value' => false, 'type' => 'boolean', 'description' => 'Tryb konserwacji', 'public' => false],
            'system.debug_mode' => ['value' => false, 'type' => 'boolean', 'description' => 'Tryb debug', 'public' => false],
            'system.timezone' => ['value' => 'Europe/Warsaw', 'type' => 'string', 'description' => 'Strefa czasowa', 'public' => false],
            'system.language' => ['value' => 'pl', 'type' => 'string', 'description' => 'Język systemu', 'public' => true]
        ];
        
        foreach ($defaults as $key => $setting) {
            if (!$this->has($key)) {
                $this->set(
                    $key, 
                    $setting['value'], 
                    $setting['type'], 
                    $setting['description'], 
                    $setting['public']
                );
            }
        }
        
        return true;
    }
    
    /**
     * Pobranie ustawień z cache
     */
    public function getCached($key, $default = null)
    {
        if (!CACHE_ENABLED) {
            return $this->get($key, $default);
        }
        
        $cacheKey = 'setting_' . $key;
        $cached = $this->getFromCache($cacheKey);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $value = $this->get($key, $default);
        $this->setCache($cacheKey, $value, CACHE_DURATION);
        
        return $value;
    }
    
    /**
     * Pobranie z cache
     */
    private function getFromCache($key)
    {
        $cacheFile = ROOT_PATH . '/cache/' . md5($key) . '.cache';
        
        if (file_exists($cacheFile)) {
            $data = unserialize(file_get_contents($cacheFile));
            if ($data['expires'] > time()) {
                return $data['value'];
            }
            unlink($cacheFile);
        }
        
        return false;
    }
    
    /**
     * Zapisanie do cache
     */
    private function setCache($key, $value, $duration)
    {
        $cacheDir = ROOT_PATH . '/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheFile = $cacheDir . '/' . md5($key) . '.cache';
        $data = [
            'value' => $value,
            'expires' => time() + $duration
        ];
        
        file_put_contents($cacheFile, serialize($data));
    }
    
    /**
     * Czyszczenie cache
     */
    public function clearCache()
    {
        $cacheDir = ROOT_PATH . '/cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*.cache');
            foreach ($files as $file) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * Eksport ustawień
     */
    public function export()
    {
        $settings = $this->getAllSettings();
        return json_encode($settings, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import ustawień
     */
    public function import($jsonData)
    {
        $settings = json_decode($jsonData, true);
        
        if (!$settings) {
            return false;
        }
        
        foreach ($settings as $key => $setting) {
            $this->set(
                $key,
                $setting['value'],
                $setting['type'],
                $setting['description'],
                $setting['is_public']
            );
        }
        
        return true;
    }
}
