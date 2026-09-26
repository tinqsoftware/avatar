<?php

namespace App\Console\Commands;

use App\Services\ConversationVersionBundle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('avatars:import-version {archive : Nombre del ZIP dentro de storage/app/private/avatar-imports} {--dry-run : Valida el paquete sin cambiar la base ni los archivos}')]
#[Description('Valida e importa una versión de conversación generada en otro entorno.')]
class ImportConversationVersion extends Command
{
    public function handle(ConversationVersionBundle $bundle): int
    {
        $filename = $this->argument('archive');
        if (basename($filename) !== $filename || ! str_ends_with($filename, '.zip')) {
            $this->error('Indica solamente el nombre de un archivo .zip dentro de avatar-imports.');

            return self::FAILURE;
        }

        $relativePath = "avatar-imports/{$filename}";
        if (! Storage::disk('local')->exists($relativePath)) {
            $this->error('No se encontró el paquete dentro de avatar-imports.');

            return self::FAILURE;
        }

        try {
            $result = $bundle->import(Storage::disk('local')->path($relativePath), (bool) $this->option('dry-run'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $message = $this->option('dry-run')
            ? 'Paquete válido'
            : 'Versión importada y publicada';
        $this->info("{$message}: {$result['avatar']->slug}, {$result['asset_count']} audios.");

        return self::SUCCESS;
    }
}
