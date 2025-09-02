<?php
/**
 * User Controller
 * Kontroler do zarządzania profilem użytkownika
 */

namespace App\Controllers;

class UserController
{
    private $userModel;
    
    public function __construct()
    {
        $this->userModel = new \App\Models\User();
    }
    
    /**
     * Wyświetlenie profilu użytkownika
     */
    public function profile()
    {
        try {
            $userId = $_SESSION['user_id'];
            $user = $this->userModel->find($userId);
            
            if (!$user) {
                set_flash_message('error', 'Użytkownik nie istnieje.');
                redirect('/logout');
                return;
            }
            
            $this->render('users/profile', [
                'user' => $user,
                'page_title' => 'Profil użytkownika',
                'page_description' => 'Zarządzaj swoim profilem'
            ]);
            
        } catch (Exception $e) {
            logError('UserController::profile error: ' . $e->getMessage());
            renderError();
        }
    }
    
    /**
     * Aktualizacja profilu użytkownika
     */
    public function update()
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $userId = $_SESSION['user_id'];
            $user = $this->userModel->find($userId);
            
            if (!$user) {
                set_flash_message('error', 'Użytkownik nie istnieje.');
                redirect('/logout');
                return;
            }
            
            // Walidacja danych
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            if (empty($firstName) || strlen($firstName) < 2) {
                set_flash_message('error', 'Imię musi mieć co najmniej 2 znaki.');
                redirect('/profile');
                return;
            }
            
            if (empty($lastName) || strlen($lastName) < 2) {
                set_flash_message('error', 'Nazwisko musi mieć co najmniej 2 znaki.');
                redirect('/profile');
                return;
            }
            
            if (!validateEmail($email)) {
                set_flash_message('error', 'Podaj prawidłowy adres email.');
                redirect('/profile');
                return;
            }
            
            // Sprawdzenie czy email nie jest już używany
            if ($email !== $user['email']) {
                $existingUser = $this->userModel->whereFirst('email', $email);
                if ($existingUser && $existingUser['id'] != $userId) {
                    set_flash_message('error', 'Ten adres email jest już używany.');
                    redirect('/profile');
                    return;
                }
            }
            
            // Przygotowanie danych do aktualizacji
            $updateData = [
                'first_name' => sanitizeInput($firstName),
                'last_name' => sanitizeInput($lastName),
                'email' => $email,
                'bio' => sanitizeInput($bio)
            ];
            
            // Obsługa uploadu avatara
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $avatar = $this->uploadAvatar($_FILES['avatar']);
                if ($avatar) {
                    $updateData['avatar'] = $avatar;
                    
                    // Usunięcie starego avatara
                    if ($user['avatar'] && file_exists(UPLOADS_PATH . '/avatars/' . $user['avatar'])) {
                        unlink(UPLOADS_PATH . '/avatars/' . $user['avatar']);
                    }
                }
            }
            
            // Aktualizacja profilu
            if ($this->userModel->update($userId, $updateData)) {
                // Aktualizacja sesji
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                $_SESSION['user_email'] = $email;
                
                set_flash_message('success', 'Profil został zaktualizowany.');
            } else {
                set_flash_message('error', 'Wystąpił błąd podczas aktualizacji profilu.');
            }
            
            redirect('/profile');
            
        } catch (Exception $e) {
            log_error('UserController::update error: ' . $e->getMessage());
            set_flash_message('error', 'Wystąpił błąd podczas aktualizacji profilu.');
            redirect('/profile');
        }
    }
    
    /**
     * Zmiana hasła
     */
    public function changePassword()
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $userId = $_SESSION['user_id'];
            $user = $this->userModel->find($userId);
            
            if (!$user) {
                set_flash_message('error', 'Użytkownik nie istnieje.');
                redirect('/logout');
                return;
            }
            
            // Walidacja danych
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($currentPassword)) {
                set_flash_message('error', 'Podaj aktualne hasło.');
                redirect('/profile');
                return;
            }
            
            if (!verifyPassword($currentPassword, $user['password'])) {
                set_flash_message('error', 'Aktualne hasło jest nieprawidłowe.');
                redirect('/profile');
                return;
            }
            
            if (empty($newPassword) || !validatePassword($newPassword)) {
                set_flash_message('error', 'Nowe hasło musi mieć co najmniej 8 znaków, zawierać wielką literę, małą literę i cyfrę.');
                redirect('/profile');
                return;
            }
            
            if ($newPassword !== $confirmPassword) {
                set_flash_message('error', 'Nowe hasła nie są identyczne.');
                redirect('/profile');
                return;
            }
            
            if ($currentPassword === $newPassword) {
                set_flash_message('error', 'Nowe hasło musi być inne od aktualnego.');
                redirect('/profile');
                return;
            }
            
            // Hashowanie nowego hasła
            $hashedPassword = hashPassword($newPassword);
            
            // Aktualizacja hasła
            if ($this->userModel->update($userId, ['password' => $hashedPassword])) {
                set_flash_message('success', 'Hasło zostało zmienione.');
            } else {
                set_flash_message('error', 'Wystąpił błąd podczas zmiany hasła.');
            }
            
            redirect('/profile');
            
        } catch (Exception $e) {
            log_error('UserController::changePassword error: ' . $e->getMessage());
            set_flash_message('error', 'Wystąpił błąd podczas zmiany hasła.');
            redirect('/profile');
        }
    }
    
    /**
     * Usunięcie konta
     */
    public function deleteAccount()
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            $userId = $_SESSION['user_id'];
            $user = $this->userModel->find($userId);
            
            if (!$user) {
                set_flash_message('error', 'Użytkownik nie istnieje.');
                redirect('/logout');
                return;
            }
            
            $password = $_POST['password'] ?? '';
            
            if (!verifyPassword($password, $user['password'])) {
                set_flash_message('error', 'Hasło jest nieprawidłowe.');
                redirect('/profile');
                return;
            }
            
            // Dezaktywacja konta (soft delete)
            if ($this->userModel->update($userId, [
                'is_active' => false,
                'deleted_at' => date('Y-m-d H:i:s')
            ])) {
                // Wylogowanie użytkownika
                session_destroy();
                set_flash_message('success', 'Twoje konto zostało usunięte.');
                redirect('/');
            } else {
                set_flash_message('error', 'Wystąpił błąd podczas usuwania konta.');
                redirect('/profile');
            }
            
        } catch (Exception $e) {
            log_error('UserController::deleteAccount error: ' . $e->getMessage());
            set_flash_message('error', 'Wystąpił błąd podczas usuwania konta.');
            redirect('/profile');
        }
    }
    
    /**
     * Upload avatara
     */
    private function uploadAvatar($file)
    {
        try {
            // Sprawdzenie typu pliku
            if (!is_image($file)) {
                set_flash_message('error', 'Dozwolone są tylko pliki obrazów (JPG, PNG, GIF, WebP).');
                return false;
            }
            
            // Sprawdzenie rozmiaru pliku
            if ($file['size'] > MAX_FILE_SIZE) {
                set_flash_message('error', 'Plik jest za duży (maksymalnie 5MB).');
                return false;
            }
            
            $uploadDir = UPLOADS_PATH . '/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Generowanie miniaturki
                $thumbnailPath = $uploadDir . 'thumb_' . $fileName;
                createThumbnail('avatars/' . $fileName, 'avatars/thumb_' . $fileName, 150, 150);
                
                return $fileName;
            }
            
            return false;
            
        } catch (Exception $e) {
            log_error('UserController::uploadAvatar error: ' . $e->getMessage());
            return false;
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
        $categories = (new \App\Models\Category())->getActiveCategories();
        
        // Pobranie ustawień systemu
        $settings = (new \App\Models\Setting())->getPublicSettings();
        
        // Dołączenie layoutu
        include APP_PATH . "/views/layouts/header.php";
        include APP_PATH . "/views/{$view}.php";
        include APP_PATH . "/views/layouts/footer.php";
    }
}
