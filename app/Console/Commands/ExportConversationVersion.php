<?php

namespace App\Console\Commands;

use App\Models\Avatar;
use App\Models\ConversationVersion;
use App\Services\ConversationVersionBundle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

#[Signature('avatars:export-version {avatar : Slug del avatar} {--conversation-version= : ID de una versión concreta} {--output= : Nombre del ZIP dentro de storage/app/private/avatar-exports}')]
#[Description('Exporta una versión de conversación y sus MP3 listos en un paquete verificable.')]
class ExportConversationVersion extends Command
{
    public function handle(ConversationVersionBundle $bundle): int
    {
        $avatar = Avatar::query()->where('slug', $this->argument('avatar'))->first();
        if (! $avatar) {
            $this->error('No existe un avatar con ese slug.');

            return self::FAILURE;
        }

        $version = ConversationVersion::query()
            ->whereBelongsTo($avatar)
            ->when(
                $this->option('conversation-version'),
                fn ($query, $versionId) => $query->whereKey($versionId),
                fn ($query) => $query->where('status', 'published')->latest('published_at'),
            )
            ->first();
        if (! $version) {
            $this->error('No se encontró una versión publicable para este avatar.');

            return self::FAILURE;
        }

        $filename = $this->filename();
        if (! $filename) {
            $this->error('El nombre del paquete debe terminar en .zip y no puede incluir carpetas.');

            return self::FAILURE;
        }

        $relativePath = "avatar-exports/{$filename}";
        try {
            $bundle->export($version, Storage::disk('local')->path($relativePath));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Paquete creado: '.Storage::disk('local')->path($relativePath));

        return self::SUCCESS;
    }

    private function filename(): ?string
    {
        $filename = $this->option('output');
        if (! is_string($filename) || $filename === '') {
            $filename = sprintf('%s-%s.zip', $this->argument('avatar'), Str::uuid());
        }

        return basename($filename) === $filename && str_ends_with($filename, '.zip') ? $filename : null;
    }
}
