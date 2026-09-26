<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RemoteDeliveryClient
{
    /** @return list<array{id: int, slug: string, name: string, public_title: string, status: string}> */
    public function avatars(): array
    {
        $response = $this->request()->get('/internal/audio-sync/avatars');
        if (! $response->successful() || ! is_array($response->json('data'))) {
            throw new RuntimeException('No se pudo obtener la lista de avatares del VPS.');
        }

        return $response->json('data');
    }

    /** @return array{avatar: string, version: int, asset_count: int} */
    public function upload(string $archivePath): array
    {
        $contents = file_get_contents($archivePath);
        if (! is_string($contents)) {
            throw new RuntimeException('No se pudo leer el paquete de audio local.');
        }

        $response = $this->request()
            ->attach('bundle', $contents, basename($archivePath), ['Content-Type' => 'application/zip'])
            ->post('/internal/audio-sync/bundles');
        if (! $response->successful() || ! is_array($response->json())) {
            $message = $response->json('message');
            throw new RuntimeException(is_string($message) ? $message : 'El VPS rechazó el paquete de audio.');
        }

        return $response->json();
    }

    private function request(): PendingRequest
    {
        $url = config('avatar.sync_url');
        $token = config('avatar.sync_token');
        if (! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            throw new RuntimeException('Configura AVATAR_SYNC_URL y AVATAR_SYNC_TOKEN en el estudio local.');
        }

        return Http::acceptJson()
            ->withToken($token)
            ->baseUrl($url)
            ->connectTimeout(10)
            ->timeout(config('avatar.sync_timeout_seconds'));
    }
}
