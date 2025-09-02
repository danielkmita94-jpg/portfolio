namespace App\Controllers;  
<?php
/**
 * Subscription Controller
 * Kontroler do zarządzania subskrypcjami newslettera
 */

class SubscriptionController
{
    private $subscriptionModel;
    
    public function __construct()
    {
        $this->subscriptionModel = new Subscription();
    }
    
    /**
     * Dodawanie subskrypcji
     */
    public function store()
    {
        try {
            // Walidacja CSRF
            validateCSRFToken();
            
            // Rate limiting
            if (!checkRateLimit('subscription_' . $_SERVER['REMOTE_ADDR'], 3, 3600)) {
                setFlashMessage('error', 'Zbyt wiele prób subskrypcji. Spróbuj ponownie za godzinę.');
                redirect('/');
                return;
            }
            
            // Walidacja danych
            $email = trim($_POST['email'] ?? '');
            $name = trim($_POST['name'] ?? '');
            
            if (empty($email) || !validateEmail($email)) {
                setFlashMessage('error', 'Podaj prawidłowy adres email.');
                redirect('/');
                return;
            }
            
            if (empty($name) || strlen($name) < 2) {
                setFlashMessage('error', 'Imię musi mieć co najmniej 2 znaki.');
                redirect('/');
                return;
            }
            
            // Sprawdzenie czy email już istnieje
            $existingSubscription = $this->subscriptionModel->whereFirst('email', $email);
            if ($existingSubscription) {
                if ($existingSubscription['is_active']) {
                    setFlashMessage('info', 'Ten adres email jest już zasubskrybowany.');
                } else {
                    // Reaktywacja subskrypcji
                    $this->subscriptionModel->update($existingSubscription['id'], [
                        'is_active' => true,
                        'name' => $name,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    setFlashMessage('success', 'Twoja subskrypcja została reaktywowana.');
                }
                redirect('/');
                return;
            }
            
            // Tworzenie nowej subskrypcji
            $subscriptionData = [
                'email' => $email,
                'name' => sanitizeInput($name),
                'is_active' => true,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'subscribed_at' => date('Y-m-d H:i:s')
            ];
            
            $subscriptionId = $this->subscriptionModel->create($subscriptionData);
            
            if ($subscriptionId) {
                // Wysłanie emaila powitalnego
                $this->sendWelcomeEmail($email, $name);
                
                setFlashMessage('success', 'Dziękujemy za subskrypcję! Sprawdź swoją skrzynkę email.');
            } else {
                setFlashMessage('error', 'Wystąpił błąd podczas dodawania subskrypcji.');
            }
            
            redirect('/');
            
        } catch (Exception $e) {
            logError('SubscriptionController::store error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas dodawania subskrypcji.');
            redirect('/');
        }
    }
    
    /**
     * Anulowanie subskrypcji
     */
    public function unsubscribe($token)
    {
        try {
            $subscription = $this->subscriptionModel->whereFirst('unsubscribe_token', $token);
            
            if (!$subscription) {
                setFlashMessage('error', 'Nieprawidłowy token anulowania subskrypcji.');
                redirect('/');
                return;
            }
            
            // Dezaktywacja subskrypcji
            $this->subscriptionModel->update($subscription['id'], [
                'is_active' => false,
                'unsubscribed_at' => date('Y-m-d H:i:s')
            ]);
            
            setFlashMessage('success', 'Twoja subskrypcja została anulowana.');
            redirect('/');
            
        } catch (Exception $e) {
            logError('SubscriptionController::unsubscribe error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas anulowania subskrypcji.');
            redirect('/');
        }
    }
    
    /**
     * Potwierdzenie subskrypcji
     */
    public function confirm($token)
    {
        try {
            $subscription = $this->subscriptionModel->whereFirst('confirmation_token', $token);
            
            if (!$subscription) {
                setFlashMessage('error', 'Nieprawidłowy token potwierdzenia.');
                redirect('/');
                return;
            }
            
            if ($subscription['is_confirmed']) {
                setFlashMessage('info', 'Ta subskrypcja została już potwierdzona.');
                redirect('/');
                return;
            }
            
            // Potwierdzenie subskrypcji
            $this->subscriptionModel->update($subscription['id'], [
                'is_confirmed' => true,
                'confirmed_at' => date('Y-m-d H:i:s')
            ]);
            
            setFlashMessage('success', 'Twoja subskrypcja została potwierdzona. Dziękujemy!');
            redirect('/');
            
        } catch (Exception $e) {
            logError('SubscriptionController::confirm error: ' . $e->getMessage());
            setFlashMessage('error', 'Wystąpił błąd podczas potwierdzania subskrypcji.');
            redirect('/');
        }
    }
    
    /**
     * Wysłanie emaila powitalnego
     */
    private function sendWelcomeEmail($email, $name)
    {
        try {
            $subject = 'Witamy w newsletterze ' . SITE_NAME;
            $message = "
                <h2>Witaj {$name}!</h2>
                <p>Dziękujemy za subskrypcję naszego newslettera.</p>
                <p>Będziemy informować Cię o nowych artykułach i aktualnościach.</p>
                <p>Jeśli chcesz anulować subskrypcję, kliknij <a href='" . BASE_URL . "/unsubscribe'>tutaj</a>.</p>
                <p>Pozdrawiamy,<br>Zespół " . SITE_NAME . "</p>
            ";
            
            // Tutaj można dodać wysyłanie emaila przez SMTP
            // mail($email, $subject, $message, $headers);
            
        } catch (Exception $e) {
            logError('SubscriptionController::sendWelcomeEmail error: ' . $e->getMessage());
        }
    }
}
