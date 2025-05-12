<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
    }

    /**
     * Получение токена доступа через Client Credentials Flow
     */
    public function getAccessToken()
    {
        $response = Http::asForm()->post("{$this->apiUrl}/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if ($response->successful()) {
            $this->token = $response->json()['access_token'];
            return $this->token;
        }

        throw new \Exception('Failed to obtain Avito access token: ' . $response->body());
    }

    /**
     * Запрос к API Avito (например, получение списка объявлений)
     */
    public function getAds()
    {
        if (!$this->token) {
            $this->getAccessToken();
        }

        $response = Http::withToken($this->token)
            ->get("{$this->apiUrl}/ads");

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to fetch ads: ' . $response->body());
    }
}
