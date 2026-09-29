<?php

namespace App\Services;

use App\Models\AudioAsset;
use App\Models\Avatar;
use App\Models\ConversationVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class ConversationVersionBundle
{
    private const FULL_SCHEMA_VERSION = 1;

    private const INCREMENTAL_SCHEMA_VERSION = 2;

    private const MAX_AUDIO_BYTES = 2_000_000;

    public function __construct(private readonly ConversationTree $conversationTree) {}

    public function export(ConversationVersion $version, string $archivePath, ?string $deliverySlug = null): void
    {
        $version->loadMissing(['avatar', 'audioAssets']);
        $this->assertExportable($version);

        $directory = dirname($archivePath);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar la carpeta del paquete de conversación.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el paquete de conversación.');
        }

        try {
            $assets = [];
            foreach ($version->audioAssets->sortBy('asset_key') as $asset) {
                $bytes = Storage::disk('public')->get($asset->path);
                $file = 'audio/'.sha1($asset->asset_key).'.mp3';

                if (! $zip->addFromString($file, $bytes)) {
                    throw new RuntimeException("No se pudo añadir {$asset->asset_key} al paquete.");
                }

                $assets[] = [
                    'asset_key' => $asset->asset_key,
                    'text' => $asset->text,
                    'file' => $file,
                    'sha256' => hash('sha256', $bytes),
                    'mime_type' => $asset->mime_type,
                    'duration_ms' => $asset->duration_ms,
                    'visemes' => $asset->visemes,
                ];
            }

            $manifest = [
                'schema_version' => self::FULL_SCHEMA_VERSION,
                'mode' => 'full',
                'avatar' => [
                    'slug' => $deliverySlug ?? $version->avatar->slug,
                    'source_slug' => $version->avatar->slug,
                ],
                'conversation_version' => [
                    'label' => $version->label,
                    'tree' => $version->tree,
                ],
                'assets' => $assets,
            ];

            if (! $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
                throw new RuntimeException('No se pudo añadir el manifiesto al paquete.');
            }
        } catch (Throwable $exception) {
            $zip->close();
            @unlink($archivePath);

            throw $exception;
        }

        if (! $zip->close()) {
            @unlink($archivePath);
            throw new RuntimeException('No se pudo cerrar el paquete de conversación.');
        }
    }

    /**
     * @param  iterable<AudioAsset>  $assets
     */
    public function exportIncremental(ConversationVersion $version, iterable $assets, string $archivePath, string $deliverySlug): void
    {
        $version->loadMissing('avatar');
        $tree = $this->conversationTree->validate($version->tree);
        $entries = collect($assets)->values();
        if ($entries->isEmpty()) {
            throw new RuntimeException('No hay audios listos para sincronizar.');
        }

        $directory = dirname($archivePath);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar la carpeta del paquete de conversación.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el paquete de conversación.');
        }

        try {
            $manifestAssets = [];
            foreach ($entries as $asset) {
                if ($asset->status !== 'ready' || ! $asset->path || ! Storage::disk('public')->exists($asset->path)) {
                    throw new RuntimeException("{$asset->asset_key} todavía no está listo para sincronizar.");
                }
                if (($this->conversationTree->lines($tree)[$asset->asset_key] ?? null) !== $asset->text) {
                    throw new RuntimeException("{$asset->asset_key} no coincide con el árbol de conversación.");
                }

                $bytes = Storage::disk('public')->get($asset->path);
                $file = 'audio/'.sha1($asset->asset_key).'.mp3';
                if (! $zip->addFromString($file, $bytes)) {
                    throw new RuntimeException("No se pudo añadir {$asset->asset_key} al paquete.");
                }

                $manifestAssets[] = $this->assetManifestEntry($asset, $file, $bytes);
            }

            $syncKey = $version->sync_key ?: "{$version->avatar->slug}:{$version->id}";
            $manifest = [
                'schema_version' => self::INCREMENTAL_SCHEMA_VERSION,
                'mode' => 'incremental',
                'avatar' => [
                    'slug' => $deliverySlug,
                    'source_slug' => $version->avatar->slug,
                ],
                'conversation_version' => [
                    'label' => $version->label,
                    'tree' => $tree,
                    'sync_key' => $syncKey,
                    'expected_asset_count' => count($this->conversationTree->lines($tree)),
                ],
                'assets' => $manifestAssets,
            ];
            if (! $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
                throw new RuntimeException('No se pudo añadir el manifiesto al paquete.');
            }
        } catch (Throwable $exception) {
            $zip->close();
            @unlink($archivePath);

            throw $exception;
        }

        if (! $zip->close()) {
            @unlink($archivePath);
            throw new RuntimeException('No se pudo cerrar el paquete de conversación.');
        }
    }

    /**
     * @return array{avatar: Avatar, version: ConversationVersion, asset_count: int}
     */
    public function import(string $archivePath, bool $dryRun = false): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('No se pudo abrir el paquete de conversación.');
        }

        try {
            $manifest = $this->validatedManifest($zip);
            $avatar = Avatar::query()->where('slug', $manifest['avatar']['slug'])->first();
            if (! $avatar) {
                throw new RuntimeException('El avatar indicado en el paquete no existe en este servidor.');
            }

            if (($manifest['mode'] ?? 'full') === 'incremental') {
                return $this->importIncremental($avatar, $manifest, $zip, $dryRun);
            }

            if ($dryRun) {
                return [
                    'avatar' => $avatar,
                    'version' => new ConversationVersion([
                        'avatar_id' => $avatar->id,
                        'label' => $manifest['conversation_version']['label'],
                        'tree' => $manifest['conversation_version']['tree'],
                    ]),
                    'asset_count' => count($manifest['assets']),
                ];
            }

            $writtenPaths = [];
            try {
                $result = DB::transaction(function () use ($avatar, $manifest, $zip, &$writtenPaths): array {
                    $lockedAvatar = Avatar::query()->whereKey($avatar->id)->lockForUpdate()->firstOrFail();
                    $version = $lockedAvatar->conversationVersions()->create([
                        'label' => $manifest['conversation_version']['label'],
                        'tree' => $manifest['conversation_version']['tree'],
                        'status' => 'generating',
                    ]);

                    foreach ($manifest['assets'] as $entry) {
                        $path = sprintf(
                            'avatars/%s/versions/%d/%s.mp3',
                            $lockedAvatar->slug,
                            $version->id,
                            str_replace('.', '-', $entry['asset_key']),
                        );
                        $bytes = $zip->getFromName($entry['file']);
                        if (! is_string($bytes) || ! Storage::disk('public')->put($path, $bytes)) {
                            throw new RuntimeException("No se pudo guardar {$entry['asset_key']} en el servidor.");
                        }
                        $writtenPaths[] = $path;

                        $version->audioAssets()->create([
                            'asset_key' => $entry['asset_key'],
                            'text' => $entry['text'],
                            'path' => $path,
                            'mime_type' => $entry['mime_type'],
                            'duration_ms' => $entry['duration_ms'],
                            'visemes' => $entry['visemes'],
                            'status' => 'ready',
                        ]);
                    }

                    $lockedAvatar->conversationVersions()->where('status', 'published')->update(['status' => 'archived']);
                    $version->update(['status' => 'published', 'published_at' => now()]);
                    $lockedAvatar->update(['status' => 'published']);

                    return [$lockedAvatar, $version];
                }, attempts: 3);
            } catch (Throwable $exception) {
                Storage::disk('public')->delete($writtenPaths);

                throw $exception;
            }

            return [
                'avatar' => $result[0],
                'version' => $result[1],
                'asset_count' => count($manifest['assets']),
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @param  array{avatar: array{slug: string}, conversation_version: array{label: string, tree: array<string, mixed>, sync_key?: string, expected_asset_count?: int}, assets: list<array{asset_key: string, text: string, file: string, sha256: string, mime_type: string, duration_ms: int, visemes: array<int, array<string, mixed>>}>}  $manifest
     * @return array{avatar: Avatar, version: ConversationVersion, asset_count: int}
     */
    private function importIncremental(Avatar $avatar, array $manifest, ZipArchive $zip, bool $dryRun): array
    {
        $syncKey = $manifest['conversation_version']['sync_key'] ?? null;
        $expectedAssetCount = $manifest['conversation_version']['expected_asset_count'] ?? null;
        if (! is_string($syncKey) || preg_match('/^[A-Za-z0-9:_-]{3,180}$/', $syncKey) !== 1 || ! is_int($expectedAssetCount) || $expectedAssetCount < count($manifest['assets'])) {
            throw new RuntimeException('El paquete incremental no identifica correctamente la versión de origen.');
        }

        if ($dryRun) {
            return [
                'avatar' => $avatar,
                'version' => new ConversationVersion([
                    'avatar_id' => $avatar->id,
                    'label' => $manifest['conversation_version']['label'],
                    'tree' => $manifest['conversation_version']['tree'],
                    'sync_key' => $syncKey,
                    'expected_audio_assets_count' => $expectedAssetCount,
                ]),
                'asset_count' => count($manifest['assets']),
            ];
        }

        $writtenPaths = [];
        try {
            $result = DB::transaction(function () use ($avatar, $manifest, $zip, $syncKey, $expectedAssetCount, &$writtenPaths): array {
                $lockedAvatar = Avatar::query()->whereKey($avatar->id)->lockForUpdate()->firstOrFail();
                $version = $lockedAvatar->conversationVersions()->where('sync_key', $syncKey)->lockForUpdate()->first();

                if (! $version) {
                    $version = $lockedAvatar->conversationVersions()->create([
                        'label' => $manifest['conversation_version']['label'],
                        'tree' => $manifest['conversation_version']['tree'],
                        'status' => 'staging',
                        'sync_key' => $syncKey,
                        'expected_audio_assets_count' => $expectedAssetCount,
                    ]);
                } elseif ($version->expected_audio_assets_count !== $expectedAssetCount) {
                    throw new RuntimeException('El paquete no coincide con la cantidad de audios de la versión incremental ya iniciada.');
                } elseif ($version->tree != $manifest['conversation_version']['tree']) {
                    $version->update(['tree' => $manifest['conversation_version']['tree']]);
                }

                foreach ($manifest['assets'] as $entry) {
                    $path = sprintf(
                        'avatars/%s/versions/%d/%s.mp3',
                        $lockedAvatar->slug,
                        $version->id,
                        str_replace('.', '-', $entry['asset_key']),
                    );
                    $bytes = $zip->getFromName($entry['file']);
                    if (! is_string($bytes) || ! Storage::disk('public')->put($path, $bytes)) {
                        throw new RuntimeException("No se pudo guardar {$entry['asset_key']} en el servidor.");
                    }
                    $writtenPaths[] = $path;

                    $version->audioAssets()->updateOrCreate(
                        ['asset_key' => $entry['asset_key']],
                        [
                            'text' => $entry['text'],
                            'path' => $path,
                            'mime_type' => $entry['mime_type'],
                            'duration_ms' => $entry['duration_ms'],
                            'visemes' => $entry['visemes'],
                            'status' => 'ready',
                            'error' => null,
                        ],
                    );
                }

                $readyCount = $version->audioAssets()->where('status', 'ready')->count();
                if ($readyCount === $expectedAssetCount) {
                    $lockedAvatar->conversationVersions()->where('status', 'published')->whereKeyNot($version->id)->update(['status' => 'archived']);
                    $version->update(['status' => 'published', 'published_at' => now()]);
                    $lockedAvatar->update(['status' => 'published']);
                } else {
                    $version->update(['status' => 'staging', 'published_at' => null]);
                }

                return [$lockedAvatar, $version];
            }, attempts: 3);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($writtenPaths);

            throw $exception;
        }

        return [
            'avatar' => $result[0],
            'version' => $result[1]->fresh(),
            'asset_count' => count($manifest['assets']),
        ];
    }

    private function assertExportable(ConversationVersion $version): void
    {
        $lines = $this->conversationTree->lines($this->conversationTree->validate($version->tree));
        if ($version->audioAssets->count() !== count($lines) || $version->audioAssets->contains(fn ($asset): bool => $asset->status !== 'ready' || ! $asset->path)) {
            throw new RuntimeException('La versión debe tener todos sus audios listos antes de exportarse.');
        }

        foreach ($version->audioAssets as $asset) {
            if (($lines[$asset->asset_key] ?? null) !== $asset->text || ! Storage::disk('public')->exists($asset->path)) {
                throw new RuntimeException('Los audios no coinciden con el árbol de conversación publicado.');
            }
        }
    }

    /**
     * @return array{
     *     avatar: array{slug: string},
     *     conversation_version: array{label: string, tree: array<string, mixed>},
     *     assets: list<array{asset_key: string, text: string, file: string, sha256: string, mime_type: string, duration_ms: int, visemes: array<int, array<string, mixed>>}>
     * }
     */
    private function validatedManifest(ZipArchive $zip): array
    {
        $json = $zip->getFromName('manifest.json');
        if (! is_string($json)) {
            throw new RuntimeException('El paquete no contiene manifest.json.');
        }

        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('El manifiesto del paquete no es JSON válido.');
        }

        $schemaVersion = $manifest['schema_version'] ?? null;
        $mode = $manifest['mode'] ?? ($schemaVersion === self::FULL_SCHEMA_VERSION ? 'full' : null);
        if (! is_array($manifest) || ! in_array($schemaVersion, [self::FULL_SCHEMA_VERSION, self::INCREMENTAL_SCHEMA_VERSION], true) || ! in_array($mode, ['full', 'incremental'], true)) {
            throw new RuntimeException('La versión del paquete no es compatible.');
        }

        $slug = $manifest['avatar']['slug'] ?? null;
        $label = $manifest['conversation_version']['label'] ?? null;
        $tree = $manifest['conversation_version']['tree'] ?? null;
        $assets = $manifest['assets'] ?? null;
        if (! is_string($slug) || preg_match('/^[a-z0-9-]{2,80}$/', $slug) !== 1 || ! is_string($label) || trim($label) === '' || ! is_array($tree) || ! is_array($assets)) {
            throw new RuntimeException('El manifiesto del paquete no tiene el formato esperado.');
        }

        $tree = $this->conversationTree->validate($tree);
        $expectedLines = $this->conversationTree->lines($tree);
        if ($mode === 'full' && count($assets) !== count($expectedLines)) {
            throw new RuntimeException('El paquete no contiene exactamente los audios del árbol.');
        }
        if ($mode === 'incremental' && ($assets === [] || count($assets) > 5)) {
            throw new RuntimeException('Un paquete incremental debe incluir entre uno y cinco audios.');
        }

        $entries = [];
        foreach ($assets as $asset) {
            if (! is_array($asset) || ! is_string($asset['asset_key'] ?? null) || ! is_string($asset['text'] ?? null) || ! is_string($asset['file'] ?? null) || ! is_string($asset['sha256'] ?? null) || ! is_string($asset['mime_type'] ?? null) || ! is_int($asset['duration_ms'] ?? null) || ! is_array($asset['visemes'] ?? null)) {
                throw new RuntimeException('Un audio del paquete no tiene el formato esperado.');
            }
            if (($expectedLines[$asset['asset_key']] ?? null) !== $asset['text'] || isset($entries[$asset['asset_key']])) {
                throw new RuntimeException('Los audios no coinciden con el árbol de conversación.');
            }
            if (! str_starts_with($asset['file'], 'audio/') || ! str_ends_with($asset['file'], '.mp3') || preg_match('/^[a-f0-9]{64}$/', $asset['sha256']) !== 1 || $asset['mime_type'] !== 'audio/mpeg' || $asset['duration_ms'] < 1 || $asset['duration_ms'] > 60_000) {
                throw new RuntimeException('Un audio del paquete tiene metadatos inválidos.');
            }

            $bytes = $zip->getFromName($asset['file']);
            if (! is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_AUDIO_BYTES || ! hash_equals($asset['sha256'], hash('sha256', $bytes))) {
                throw new RuntimeException("La verificación de {$asset['asset_key']} falló.");
            }

            $entries[$asset['asset_key']] = $asset;
        }

        $validated = [
            'avatar' => ['slug' => $slug],
            'conversation_version' => ['label' => $label, 'tree' => $tree],
            'assets' => array_values($entries),
        ];
        if ($mode === 'incremental') {
            $validated['conversation_version']['sync_key'] = $manifest['conversation_version']['sync_key'] ?? null;
            $validated['conversation_version']['expected_asset_count'] = $manifest['conversation_version']['expected_asset_count'] ?? null;
        }
        $validated['mode'] = $mode;

        return $validated;
    }

    /** @return array{asset_key: string, text: string, file: string, sha256: string, mime_type: string, duration_ms: int, visemes: array<int, array<string, mixed>>} */
    private function assetManifestEntry(AudioAsset $asset, string $file, string $bytes): array
    {
        return [
            'asset_key' => $asset->asset_key,
            'text' => $asset->text,
            'file' => $file,
            'sha256' => hash('sha256', $bytes),
            'mime_type' => $asset->mime_type,
            'duration_ms' => $asset->duration_ms,
            'visemes' => $asset->visemes,
        ];
    }
}
