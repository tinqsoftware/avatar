<?php

namespace App\Services;

use App\Models\AudioAsset;
use App\Models\Avatar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StaticAudioPublisher
{
    private const MAX_AUDIO_BYTES = 2_000_000;

    private const MAX_AUDIO_DURATION_MS = 60_000;

    public function __construct(
        private readonly VisemeTimeline $visemeTimeline,
        private readonly VoiceSampleReference $voiceSampleReference,
    ) {}

    public function isHealthy(): bool
    {
        if (config('avatar.audio_role') !== 'studio' || ! config('avatar.voicebox_enabled') || ! config('avatar.voicebox_url') || ! config('avatar.voicebox_token')) {
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
        if (config('avatar.audio_role') !== 'studio' || ! config('avatar.voicebox_enabled') || ! config('avatar.voicebox_url') || ! config('avatar.voicebox_token')) {
            throw new RuntimeException('La voz local no está configurada para publicar los audios.');
        }

        try {
            $speech = $this->synthesize($asset->conversationVersion->avatar, $asset->text, $asset->id);
        } catch (Throwable $exception) {
            throw new RuntimeException('No se pudo contactar la voz de Salad.', previous: $exception);
        }

        $bytes = $speech['bytes'];
        $duration = $speech['duration_ms'];
        $words = $speech['words'];

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
            'visemes' => $this->visemes($words, $asset->text, $duration),
            'status' => 'ready',
            'error' => null,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $words
     * @return array<int, array{at_ms:int,value:int}>
     */
    private function visemes(array $words, string $text, int $duration): array
    {
        $visemes = $this->visemeTimeline->fromWords($words, $duration);

        return count($visemes) > 2 ? $visemes : $this->visemeTimeline->fromText($text, $duration);
    }

    /** @return array{bytes: string, duration_ms: int, words: array<int, array<string, mixed>>} */
    public function synthesize(Avatar $avatar, string $text, ?int $assetId = null): array
    {
        $this->extendExecutionWindow();

        $request = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(config('avatar.voicebox_timeout_seconds'))
            ->withToken(config('avatar.voicebox_token'));

        if ($assetId) {
            $request = $request->withHeaders(['Idempotency-Key' => "audio-{$assetId}"]);
        }

        $referencePath = $avatar->usesClonedVoice()
            ? $this->voiceSampleReference->pathFor($avatar)
            : config('avatar.voicebox_synthetic_reference_path');
        if (! is_string($referencePath) || ! is_file($referencePath)) {
            throw new RuntimeException('Falta la referencia local de la voz sintética Anita.');
        }
        $sample = file_get_contents($referencePath);
        if (! is_string($sample)) {
            throw new RuntimeException('No se pudo leer la referencia de voz local.');
        }

        $response = $request
            ->attach('voice_sample', $sample, 'reference.wav')
            ->post(config('avatar.voicebox_url').config('avatar.voicebox_clone_speech_path'), [
                'input' => $text,
                'voice_mode' => $avatar->usesClonedVoice() ? 'cloned' : 'synthetic',
                'response_format' => 'mp3',
                'language' => 'es',
                'locale' => $avatar->voice_locale,
            ]);

        return $this->responsePayload($response);
    }

    private function extendExecutionWindow(): void
    {
        $timeout = (int) config('avatar.voicebox_timeout_seconds');

        if ($timeout > 0 && function_exists('set_time_limit')) {
            set_time_limit($timeout + 10);
        }
    }

    /** @return array{bytes: string, duration_ms: int, words: array<int, array<string, mixed>>} */
    private function responsePayload(Response $response): array
    {
        if ($response->status() === 401) {
            throw new RuntimeException('Voicebox local rechazó la conexión. Reinicia el servicio local e inténtalo nuevamente.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Voicebox local no pudo generar esta prueba. Inténtalo nuevamente.');
        }

        $audio = $response->json('audio_base64');
        $duration = $response->json('duration_ms');
        $words = $response->json('words');
        if (! is_string($audio) || ! is_int($duration) || ! is_array($words)) {
            throw new RuntimeException('Voicebox no devolvió un audio publicable.');
        }

        $bytes = base64_decode($audio, true);
        if ($bytes === false || strlen($bytes) > self::MAX_AUDIO_BYTES || $duration < 1 || $duration > self::MAX_AUDIO_DURATION_MS) {
            throw new RuntimeException('Voicebox devolvió un audio inválido.');
        }

        return ['bytes' => $bytes, 'duration_ms' => $duration, 'words' => $words];
    }
}
