<?php
/**
 * Blog System - Main Entry Point
 * Główny punkt wejścia do aplikacji
 */

// Włączenie raportowania błędów
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ładowanie konfiguracji
require_once '../app/config/config.php';

// Autoloader
require_once '../vendor/autoload.php';

// Ładowanie funkcji pomocniczych
require_once '../app/helpers/functions.php';

// Rozpoczęcie sesji (jeśli nie jest już aktywna)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ładowanie zaawansowanego routera
$router = require_once '../routes/advanced.php';

// Inicjalizacja aplikacji
try {
    // Pobranie URL
    $requestUri = $_SERVER['REQUEST_URI'];
    $basePath = dirname($_SERVER['SCRIPT_NAME']);
    
    // Usunięcie ścieżki bazowej z URL
    if ($basePath !== '/') {
        $requestUri = str_replace($basePath, '', $requestUri);
    }
    
    // Usunięcie query string
    $requestUri = strtok($requestUri, '?');
    
    // Usunięcie trailing slash
    $requestUri = rtrim($requestUri, '/');
    
    // Domyślna strona główna
    if (empty($requestUri)) {
        $requestUri = '/';
    }
    
    // Pobranie metody HTTP
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Obsługa metod PUT, PATCH, DELETE przez POST
    if ($method === 'POST' && isset($_POST['_method'])) {
        $method = strtoupper($_POST['_method']);
    }
    
    // Utworzenie dispatchera
    $dispatcher = new \App\Core\Dispatcher($router);
    
    // Obsługa request
    $dispatcher->dispatch($method, $requestUri);
    
} catch (Exception $e) {
    // Logowanie błędu
    logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Wyświetlenie błędu
    if (DEBUG) {
        echo '<h1>Error</h1>';
        echo '<p><strong>Message:</strong> ' . e($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . e($e->getFile()) . '</p>';
        echo '<p><strong>Line:</strong> ' . e($e->getLine()) . '</p>';
        echo '<h2>Stack Trace:</h2>';
        echo '<pre>' . e($e->getTraceAsString()) . '</pre>';
    } else {
        renderError();
    }
}



/**
 * Renderowanie strony 404
 */
function render404() {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="pl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Strona nie znaleziona</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-6xl font-bold text-gray-800 mb-4">404</h1>
            <h2 class="text-2xl font-semibold text-gray-600 mb-8">Strona nie znaleziona</h2>
            <p class="text-gray-500 mb-8">Przepraszamy, ale strona której szukasz nie istnieje.</p>
            <a href="/" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                Wróć do strony głównej
            </a>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Renderowanie strony błędu
 */
function renderError() {
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="pl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>500 - Błąd serwera</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-6xl font-bold text-gray-800 mb-4">500</h1>
            <h2 class="text-2xl font-semibold text-gray-600 mb-8">Błąd serwera</h2>
            <p class="text-gray-500 mb-8">Przepraszamy, wystąpił błąd serwera. Spróbuj ponownie później.</p>
            <a href="/" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                Wróć do strony głównej
            </a>
        </div>
    </body>
    </html>
    <?php
}
