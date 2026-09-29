<?php

namespace App;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class LocalAudioWorker
{
    private const LAUNCH_AGENT_LABEL = 'pe.tinq.avatar-queue';

    public function start(): void
    {
        if (config('avatar.audio_role') !== 'studio') {
            return;
        }

        if (PHP_OS_FAMILY !== 'Darwin') {
            throw new RuntimeException('El estudio local solo puede iniciar el worker automático en macOS.');
        }

        $service = sprintf('gui/%d/%s', $this->userId(), self::LAUNCH_AGENT_LABEL);
        $state = Process::timeout(5)->run(['launchctl', 'print', $service]);

        if (! $state->successful()) {
            throw new RuntimeException('El worker local no está instalado. Ejecuta la instalación local del Estudio de audio una vez.');
        }

        if (str_contains($state->output(), 'state = running')) {
            return;
        }

        $start = Process::timeout(5)->run(['launchctl', 'kickstart', $service]);

        if (! $start->successful()) {
            throw new RuntimeException('No se pudo iniciar el worker local de audio. Revisa que Voicebox esté disponible e inténtalo nuevamente.');
        }
    }

    private function userId(): int
    {
        if (function_exists('posix_geteuid')) {
            return posix_geteuid();
        }

        return getmyuid();
    }
}
