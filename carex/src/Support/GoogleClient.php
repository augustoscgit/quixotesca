<?php

declare(strict_types=1);

namespace Carex\Support;

use RuntimeException;

final class GoogleClient
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $redirectUri
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;

        if ($this->clientId === '' || $this->clientSecret === '' || $this->redirectUri === '') {
            throw new RuntimeException('Configurações de cliente Google OAuth 2.0 incompletas.');
        }
    }

    /**
     * Generates the Google consent screen authorization URL.
     */
    public function getAuthUrl(string $state): string
    {
        $params = [
            'response_type' => 'code',
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'scope'         => 'openid profile email',
            'state'         => $state,
            'prompt'        => 'select_account'
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchanges the authorization code for access token and profile info.
     *
     * @param string $code
     * @return array{google_id: string, email: string, name: string, profile_picture: ?string}
     * @throws RuntimeException
     */
    public function fetchTokenAndProfile(string $code): array
    {
        // 1. Exchange code for access token
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = http_build_query([
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code'
        ]);

        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                             "Content-Length: " . strlen($postData) . "\r\n",
                'content' => $postData,
                'ignore_errors' => true // allows inspecting response status / body on failure
            ]
        ];

        $context = stream_context_create($opts);
        $tokenResponse = @file_get_contents($tokenUrl, false, $context);

        if ($tokenResponse === false) {
            throw new RuntimeException('Falha na comunicação com o servidor OAuth do Google.');
        }

        $tokenData = json_decode($tokenResponse, true);
        if (!is_array($tokenData) || isset($tokenData['error'])) {
            $err = $tokenData['error_description'] ?? $tokenData['error'] ?? 'desconhecido';
            throw new RuntimeException("Erro ao trocar código de autorização: {$err}");
        }

        $accessToken = $tokenData['access_token'] ?? '';
        if ($accessToken === '') {
            throw new RuntimeException('Access Token não retornado pelo Google.');
        }

        // 2. Fetch userinfo using the access token
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
        $optsUser = [
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$accessToken}\r\n",
                'ignore_errors' => true
            ]
        ];

        $contextUser = stream_context_create($optsUser);
        $userResponse = @file_get_contents($userInfoUrl, false, $contextUser);

        if ($userResponse === false) {
            throw new RuntimeException('Falha ao obter dados cadastrais do usuário no Google.');
        }

        $userData = json_decode($userResponse, true);
        if (!is_array($userData) || isset($userData['error'])) {
            $err = $userData['error_description'] ?? $userData['error'] ?? 'desconhecido';
            throw new RuntimeException("Erro ao consultar perfil do usuário no Google: {$err}");
        }

        $googleId = $userData['sub'] ?? '';
        $email = $userData['email'] ?? '';
        $name = $userData['name'] ?? '';
        $picture = $userData['picture'] ?? null;

        if ($googleId === '' || $email === '' || $name === '') {
            throw new RuntimeException('Perfil retornado pelo Google está incompleto.');
        }

        return [
            'google_id'       => $googleId,
            'email'           => $email,
            'name'            => $name,
            'profile_picture' => $picture
        ];
    }
}
