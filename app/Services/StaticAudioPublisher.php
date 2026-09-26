<?php

namespace App\Services;

use App\Models\AudioAsset;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StaticAudioPublisher
{
    private const MAX_AUDIO_BYTES = 2_000_000;

    private const MAX_AUDIO_DURATION_MS = 60_000;

    public function __construct(private readonly VisemeTimeline $visemeTimeline) {}

    public function isHealthy(): bool
    {
        if (! config('avatar.voicebox_enabled') || ! config('avatar.voicebox_url') || ! config('avatar.voicebox_token')) {
            return false;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(config('avatar.voicebox_health_timeout_seconds'))
                ->get(config('avatar.voicebox_url').'/health');
        } catch (Throwable) {
            return false;
        }

        return $response->successful() && $response->json('models_loaded') === true;
    }

    public function publish(AudioAsset $asset): void
    {
        if (! config('avatar.voicebox_enabled') || ! config('avatar.voicebox_url') || ! config('avatar.voicebox_token')) {
            throw new RuntimeException('La voz de Salad no está configurada para publicar los audios.');
        }

        try {
            $response = $this->requestSpeech($asset);
        } catch (Throwable $exception) {
            throw new RuntimeException('No se pudo contactar la voz de Salad.', previous: $exception);
        }

        $audio = $response->json('audio_base64');
        $duration = $response->json('duration_ms');
        $words = $response->json('words');
        if (! $response->successful() || ! is_string($audio) || ! is_int($duration) || ! is_array($words)) {
            throw new RuntimeException('Salad no devolvió un audio publicable.');
        }

        $bytes = base64_decode($audio, true);
        if ($bytes === false || strlen($bytes) > self::MAX_AUDIO_BYTES || $duration < 1 || $duration > self::MAX_AUDIO_DURATION_MS) {
            throw new RuntimeException('Salad devolvió un audio inválido.');
        }

        $path = sprintf(
            'avatars/%s/versions/%d/%s.mp3',
            $asset->conversationVersion->avatar->slug,
            $asset->conversation_version_id,
            str_replace('.', '-', $asset->asset_key),
        );
        Storage::disk('public')->put($path, $bytes);

        $asset->update([
            'path' => $path,
            'duration_ms' => $duration,
            'visemes' => $this->visemeTimeline->fromWords($words, $duration),
            'status' => 'ready',
            'error' => null,
        ]);
    }

    private function requestSpeech(AudioAsset $asset): Response
    {
        $avatar = $asset->conversationVersion->avatar;
        $request = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(config('avatar.voicebox_timeout_seconds'))
            ->withToken(config('avatar.voicebox_token'))
            ->withHeaders(['Idempotency-Key' => "audio-{$asset->id}"]);

        if (! $avatar->usesClonedVoice()) {
            return $request->post(config('avatar.voicebox_url').config('avatar.voicebox_synthetic_speech_path'), [
                'input' => $asset->text,
                'voice' => $avatar->voice_profile,
                'response_format' => 'mp3',
            ]);
        }

        if (! $avatar->voice_sample_path || ! Storage::disk('local')->exists($avatar->voice_sample_path)) {
            throw new RuntimeException('Este avatar no tiene una muestra privada de voz disponible.');
        }

        return $request
            ->attach('voice_sample', Storage::disk('local')->get($avatar->voice_sample_path), basename($avatar->voice_sample_path))
            ->post(config('avatar.voicebox_url').config('avatar.voicebox_clone_speech_path'), [
                'input' => $asset->text,
                'voice_mode' => 'cloned',
                'response_format' => 'mp3',
            ]);
    }
}
