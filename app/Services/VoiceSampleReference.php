<?php

namespace App\Services;

use App\Models\Avatar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class VoiceSampleReference
{
    private const MIN_TOTAL_DURATION_MS = 20_000;

    private const MAX_TOTAL_DURATION_MS = 30_000;

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function assertValid(array $files): void
    {
        $total = 0;
        foreach ($files as $file) {
            $total += $this->duration($file->getRealPath());
        }

        if ($total < self::MIN_TOTAL_DURATION_MS || $total > self::MAX_TOTAL_DURATION_MS) {
            throw ValidationException::withMessages([
                'samples' => 'Las muestras deben sumar entre 20 y 30 segundos de voz limpia.',
            ]);
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function replace(Avatar $avatar, array $files): void
    {
        $this->assertValid($files);

        $this->clear($avatar);

        foreach ($files as $file) {
            $path = $file->store("avatars/{$avatar->slug}/voice-samples", 'local');
            $avatar->voiceSamples()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'duration_ms' => $this->duration(Storage::disk('local')->path($path)),
                'locale' => $avatar->voice_locale,
                'sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
            ]);
        }

        $this->rebuild($avatar->fresh('voiceSamples'));
    }

    public function pathFor(Avatar $avatar): string
    {
        if (! $avatar->voice_sample_path || ! Storage::disk('local')->exists($avatar->voice_sample_path)) {
            throw new RuntimeException('Este avatar necesita entre una y tres muestras privadas antes de generar audio.');
        }

        return Storage::disk('local')->path($avatar->voice_sample_path);
    }

    public function clear(?Avatar $avatar): void
    {
        if (! $avatar) {
            return;
        }

        $avatar->load('voiceSamples');
        foreach ($avatar->voiceSamples as $sample) {
            Storage::disk('local')->delete($sample->path);
        }
        $avatar->voiceSamples()->delete();

        if ($avatar->voice_sample_path) {
            Storage::disk('local')->delete($avatar->voice_sample_path);
            $avatar->update(['voice_sample_path' => null]);
        }
    }

    public function rebuild(Avatar $avatar): void
    {
        $samples = $avatar->voiceSamples()->orderBy('id')->get();
        if ($samples->isEmpty()) {
            throw new RuntimeException('No hay muestras privadas para construir la referencia de voz.');
        }

        $referencePath = "avatars/{$avatar->slug}/voice-references/reference.wav";
        Storage::disk('local')->makeDirectory(dirname($referencePath));
        $output = Storage::disk('local')->path($referencePath);
        $command = ['ffmpeg', '-y'];
        $inputs = '';
        foreach ($samples as $index => $sample) {
            $command[] = '-i';
            $command[] = Storage::disk('local')->path($sample->path);
            $inputs .= "[{$index}:a]";
        }
        $command[] = '-filter_complex';
        $command[] = $inputs.'concat=n='.$samples->count().':v=0:a=1,aresample=24000,aformat=channel_layouts=mono[a]';
        $command[] = '-map';
        $command[] = '[a]';
        $command[] = '-ac';
        $command[] = '1';
        $command[] = '-ar';
        $command[] = '24000';
        $command[] = '-c:a';
        $command[] = 'pcm_s16le';
        $command[] = $output;

        $result = Process::timeout(120)->run($command);
        if ($result->failed()) {
            throw new RuntimeException('No se pudo normalizar las muestras privadas de voz.');
        }

        $avatar->update(['voice_sample_path' => $referencePath]);
    }

    private function duration(string $path): int
    {
        $result = Process::timeout(20)->run([
            'ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $path,
        ]);
        $seconds = (float) trim($result->output());
        if ($result->failed() || $seconds <= 0) {
            throw ValidationException::withMessages(['samples' => 'No se pudo leer la duración de una de las muestras.']);
        }

        return (int) round($seconds * 1000);
    }
}
