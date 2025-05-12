<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AvitoService
{
    protected $clientId;
    protected $clientSecret;
    protected $apiUrl;
    protected $token;

    public function __construct()
    {
        $this->clientId = config('services.avito.client_id');
        $this->clientSecret = config('services.avito.client_secret');
        $this->apiUrl = config('services.avito.api_url');
        Log::info('Avito Config', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'api_url' => $this->apiUrl,
        ]);
    }

    /**
     * Получение токена доступа через Client Credentials Flow
     */
    public function getAccessToken()
    {
        if (Cache::has('avito_access_token')) {
            $this->token = Cache::get('avito_access_token');
            return $this->token;
        }

        $response = Http::asForm()->post("{$this->apiUrl}/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        Log::info('Avito Token Response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->successful()) {
            $this->token = $response->json()['access_token'];
            $expiresIn = $response->json()['expires_in'] ?? 86400; // 24 часа
            Cache::put('avito_access_token', $this->token, $expiresIn - 60);
            return $this->token;
        }

        throw new \Exception('Failed to obtain Avito access token: ' . $response->body());
    }

    /**
     * Получение информации о текущем пользователе
     */
    public function getUserInfo()
    {
        if (!$this->token) {
            $this->getAccessToken();
        }

        // Эндпоинт для получения информации о пользователе
        // Замените на актуальный эндпоинт из Swagger или документации (например, /user или /core/v1/users/self)
        $response = Http::withHeaders([
                                          'Authorization' => "Bearer {$this->token}",
                                      ])->get("{$this->apiUrl}/core/v1/accounts/self");

        Log::info('Avito User Info Response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to fetch user info: ' . $response->body());
    }
}
