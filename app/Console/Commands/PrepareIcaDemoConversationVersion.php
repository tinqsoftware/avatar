<?php

namespace App\Console\Commands;

use App\Models\Avatar;
use Database\Seeders\AvatarDemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('avatars:prepare-ica-demo-version {--queue : Encola los audios pendientes tras preparar la versión.}')]
#[Description('Prepara la versión conversacional amistosa de Anita sin interrumpir la versión publicada.')]
class PrepareIcaDemoConversationVersion extends Command
{
    public function handle(AvatarDemoSeeder $seeder): int
    {
        $seeder->run();

        $avatar = Avatar::query()->where('slug', 'ica-demo')->first();
        $version = $avatar?->conversationVersions()
            ->where('label', 'Demo Gobierno Regional de Ica · Conversación amistosa')
            ->first();

        if (! $avatar || ! $version) {
            $this->error('No se pudo preparar la versión conversacional de Anita.');

            return self::FAILURE;
        }

        $this->info("Versión {$version->id} preparada con {$version->audioAssets()->count()} audios.");

        if (! $this->option('queue') || $version->status !== 'draft') {
            return self::SUCCESS;
        }

        return $this->call('avatars:queue-static-audio', [
            'avatar' => $avatar->slug,
            '--conversation-version' => $version->id,
        ]);
    }
}
