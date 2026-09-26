<?php

namespace App\Console\Commands;

use App\Jobs\GenerateStaticAudio;
use App\Models\Avatar;
use App\Models\ConversationVersion;
use App\Services\StaticAudioPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('avatars:queue-static-audio {avatar : Slug del avatar} {--conversation-version= : ID de una versión concreta}')]
#[Description('Envía a la cola los audios estáticos pendientes de una versión de conversación.')]
class QueueStaticAudio extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(StaticAudioPublisher $publisher): int
    {
        $avatar = Avatar::query()->where('slug', $this->argument('avatar'))->first();

        if (! $avatar) {
            $this->error('No existe un avatar con ese slug.');

            return self::FAILURE;
        }

        $version = ConversationVersion::query()
            ->where('avatar_id', $avatar->id)
            ->when(
                $this->option('conversation-version'),
                fn ($query, $versionId) => $query->whereKey($versionId),
                fn ($query) => $query->latest('id'),
            )
            ->first();

        if (! $version) {
            $this->error('No se encontró una versión para este avatar.');

            return self::FAILURE;
        }

        $assets = $version->audioAssets()
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        if ($assets->isEmpty()) {
            $this->warn('No hay audios pendientes para enviar a la cola.');

            return self::SUCCESS;
        }

        if (! $publisher->isHealthy()) {
            $version->update(['status' => 'draft', 'published_at' => null]);
            if (! $avatar->publishedConversation()) {
                $avatar->update(['status' => 'draft']);
            }
            $this->error('La voz de Salad no está lista. No se encoló ningún audio.');

            return self::FAILURE;
        }

        $version->update(['status' => 'generating', 'published_at' => null]);
        if (! $avatar->publishedConversation()) {
            $avatar->update(['status' => 'generating']);
        }

        $assets->each(fn ($asset) => GenerateStaticAudio::dispatch($asset->id));

        $this->info("{$assets->count()} audios estáticos enviados a la cola para {$avatar->name}.");

        return self::SUCCESS;
    }
}
