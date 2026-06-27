<?php

declare(strict_types=1);

use Carex\Database\Connection;
use Carex\Database\UserRepository;
use Carex\Http\Auth;
use Carex\Support\GoogleClient;

$config = require dirname(__DIR__) . '/src/bootstrap.php';

function failOAuth(array $config, string $message): never
{
    Auth::startSession();
    if (($config['app']['debug'] ?? false) || (($config['app']['env'] ?? '') === 'local')) {
        $_SESSION['_carex_oauth_error'] = $message;
    }

    header('Location: login.php?error=oauth');
    exit;
}

try {
    Auth::startSession();

    // Support Local Dev Bypasses/Mocks
    $mockLogin = $_GET['mock_login'] ?? '';
    if (($config['app']['env'] ?? '') === 'local' && in_array($mockLogin, ['admin', 'especialista', 'usuario', 'desligado'], true)) {
        if ($mockLogin === 'admin') {
            $profile = [
                'google_id' => 'mock-google-id-admin',
                'email' => 'augustosc@gmail.com',
                'name' => 'Augusto Admin (Mock)',
                'profile_picture' => null
            ];
        } elseif ($mockLogin === 'especialista') {
            $profile = [
                'google_id' => 'mock-google-id-especialista',
                'email' => 'especialista@carex.com',
                'name' => 'Eduardo Especialista (Mock)',
                'profile_picture' => null
            ];
        } elseif ($mockLogin === 'usuario') {
            $profile = [
                'google_id' => 'mock-google-id-usuario',
                'email' => 'usuario@carex.com',
                'name' => 'Usuario Cadastrado (Mock)',
                'profile_picture' => null
            ];
        } else {
            $profile = [
                'google_id' => 'mock-google-id-desligado',
                'email' => 'desligado@carex.com',
                'name' => 'Usuário Desligado (Mock)',
                'profile_picture' => null
            ];
        }

        $config['database']['allow_writes'] = true;
        $pdo = Connection::make($config['database']);
        $userRepo = new UserRepository($pdo);
        
        $user = $userRepo->upsertGoogleUser($profile);

        if ($mockLogin === 'especialista') {
            $pdo->prepare("UPDATE users SET role = 'especialista' WHERE id = :id")->execute(['id' => $user['id']]);
            $user['role'] = 'especialista';
        } elseif ($mockLogin === 'usuario') {
            $pdo->prepare("UPDATE users SET role = 'usuario', status = 'ativo' WHERE id = :id")->execute(['id' => $user['id']]);
            $user['role'] = 'usuario';
            $user['status'] = 'ativo';
        } elseif ($mockLogin === 'desligado') {
            $pdo->prepare("UPDATE users SET status = 'desligado' WHERE id = :id")->execute(['id' => $user['id']]);
            throw new \RuntimeException('Aviso de desligamento. Por favor, entre em contato com o administrador.');
        }

        $rememberMe = isset($_SESSION['remember_me']) || isset($_GET['remember']);
        unset($_SESSION['remember_me']);

        Auth::login($user, $rememberMe);

        $redirectTo = $_SESSION['redirect_to'] ?? 'matrizes.php';
        unset($_SESSION['redirect_to']);

        if (str_contains($redirectTo, 'auth-callback.php') || str_contains($redirectTo, 'login.php')) {
            $redirectTo = 'matrizes.php';
        }

        header('Location: ' . $redirectTo);
        exit;
    }

    $state = $_GET['state'] ?? '';
    $savedState = $_SESSION['oauth_state'] ?? '';

    // 1. Validate state to prevent CSRF
    if ($state === '' || $savedState === '' || !hash_equals($savedState, $state)) {
        failOAuth($config, 'State OAuth ausente ou invalido. Recarregue a tela de login e tente novamente.');
    }

    $code = $_GET['code'] ?? '';
    if ($code === '') {
        $googleError = $_GET['error'] ?? 'codigo de autorizacao ausente';
        failOAuth($config, 'Google nao retornou code: ' . (string) $googleError);
    }

    unset($_SESSION['oauth_state']);

    // 2. Fetch Google profile info
    $gConfig = $config['google'] ?? [];
    $googleClient = new GoogleClient(
        $gConfig['client_id'],
        $gConfig['client_secret'],
        $gConfig['redirect_uri']
    );

    $profile = $googleClient->fetchTokenAndProfile($code);

    // 3. Upsert user in database
    // Force allow_writes for registration and login sync updates
    $config['database']['allow_writes'] = true;
    $pdo = Connection::make($config['database']);
    $userRepo = new UserRepository($pdo);
    
    $user = $userRepo->upsertGoogleUser($profile);

    // 4. Authenticate user in session
    // Check if remember_me checkbox was ticked
    $rememberMe = isset($_SESSION['remember_me']) || isset($_GET['remember']) || isset($_GET['state_remember']);
    unset($_SESSION['remember_me']);

    Auth::login($user, $rememberMe);

    // 5. Redirect back to target page
    $redirectTo = $_SESSION['redirect_to'] ?? 'matrizes.php';
    unset($_SESSION['redirect_to']);

    // Ensure we don't redirect to auth-callback itself recursively
    if (str_contains($redirectTo, 'auth-callback.php') || str_contains($redirectTo, 'login.php')) {
        $redirectTo = 'matrizes.php';
    }

    header('Location: ' . $redirectTo);
    exit;

} catch (\RuntimeException $suspendedError) {
    // Suspend check failed or user is deactivated
    if (str_contains($suspendedError->getMessage(), 'Aviso de desligamento')) {
        header('Location: login.php?error=desligado');
    } else {
        failOAuth($config, $suspendedError->getMessage());
    }
    exit;
} catch (\Throwable $error) {
    // Other errors
    failOAuth($config, $error->getMessage());
}
