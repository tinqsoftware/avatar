<?php

namespace App\Services;

use App\Models\AudioAsset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class VisemeAuditService
{
    private const REST_VALUE = 0;

    private const MAX_VISEME_VALUE = 6;

    private const LONG_REST_MS = 1200;

    /**
     * @return array{status: 'ok'|'warning'|'error', issues: list<array{severity: 'warning'|'error', code: string, message: string}>, events: list<array{at_ms: int, value: int}>, duration_ms: ?int, measured_duration_ms: ?int}
     */
    public function audit(AudioAsset $asset): array
    {
        $measuredDuration = $this->measuredDuration($asset);
        $duration = $asset->duration_ms ?? $measuredDuration;
        $report = $this->inspectTimeline($asset->text, $duration, $asset->visemes);

        if ($asset->status === 'ready' && (! $asset->path || ! Storage::disk('public')->exists($asset->path))) {
            $report['issues'][] = $this->issue('error', 'audio_missing', 'El MP3 marcado como listo no existe en el almacenamiento local.');
        } elseif ($asset->path && $measuredDuration === null) {
            $report['issues'][] = $this->issue('warning', 'duration_unverified', 'No se pudo medir la duración real del MP3; se usó la duración registrada.');
        } elseif ($measuredDuration !== null && $asset->duration_ms !== null) {
            $this->inspectMeasuredDuration($report['events'], $asset->duration_ms, $measuredDuration, $report['issues']);
        }

        $report['status'] = $this->statusFor($report['issues']);
        $report['measured_duration_ms'] = $measuredDuration;

        return $report;
    }

    /**
     * @param  array<int, mixed>|null  $events
     * @return array{status: 'ok'|'warning'|'error', issues: list<array{severity: 'warning'|'error', code: string, message: string}>, events: list<array{at_ms: int, value: int}>, duration_ms: ?int, measured_duration_ms: null}
     */
    public function inspectTimeline(string $text, ?int $durationMs, ?array $events): array
    {
        $issues = [];
        $normalizedEvents = $this->normalizeEvents($events, $issues);

        if ($durationMs === null || $durationMs <= 0) {
            $issues[] = $this->issue('error', 'duration_missing', 'El audio no tiene una duración válida para auditar.');
        }

        if ($normalizedEvents === []) {
            $issues[] = $this->issue('error', 'timeline_empty', 'El audio no tiene una línea de tiempo de visemas.');
        } else {
            $this->inspectBoundaries($normalizedEvents, $durationMs, $issues);
            $this->inspectSequence($normalizedEvents, $durationMs, $issues);
            $this->inspectSpeechCoverage($text, $normalizedEvents, $issues);
        }

        return [
            'status' => $this->statusFor($issues),
            'issues' => $issues,
            'events' => $normalizedEvents,
            'duration_ms' => $durationMs,
            'measured_duration_ms' => null,
        ];
    }

    /**
     * @param  array<int, mixed>|null  $events
     * @param  list<array{severity: 'warning'|'error', code: string, message: string}>  $issues
     * @return list<array{at_ms: int, value: int}>
     */
    private function normalizeEvents(?array $events, array &$issues): array
    {
        $normalized = [];

        foreach ($events ?? [] as $index => $event) {
            if (! is_array($event) || ! is_int($event['at_ms'] ?? null) || ! is_int($event['value'] ?? null)) {
                $issues[] = $this->issue('error', 'event_invalid', "El evento {$index} no tiene tiempo y visema numéricos válidos.");

                continue;
            }

            $normalized[] = ['at_ms' => $event['at_ms'], 'value' => $event['value']];
        }

        return $normalized;
    }

    /**
     * @param  list<array{at_ms: int, value: int}>  $events
     * @param  list<array{severity: 'warning'|'error', code: string, message: string}>  $issues
     */
    private function inspectBoundaries(array $events, ?int $durationMs, array &$issues): void
    {
        $first = $events[0];
        $last = $events[array_key_last($events)];

        if ($first['at_ms'] !== 0 || $first['value'] !== self::REST_VALUE) {
            $issues[] = $this->issue('error', 'initial_rest_missing', 'La línea debe iniciar en REST (0) exactamente en 0 ms.');
        }

        if ($durationMs !== null && ($last['at_ms'] !== $durationMs || $last['value'] !== self::REST_VALUE)) {
            $issues[] = $this->issue('error', 'final_rest_missing', 'La línea debe terminar en REST (0) exactamente al final del audio.');
        }
    }

    /**
     * @param  list<array{at_ms: int, value: int}>  $events
     * @param  list<array{severity: 'warning'|'error', code: string, message: string}>  $issues
     */
    private function inspectSequence(array $events, ?int $durationMs, array &$issues): void
    {
        foreach ($events as $index => $event) {
            if ($event['value'] < self::REST_VALUE || $event['value'] > self::MAX_VISEME_VALUE) {
                $issues[] = $this->issue('error', 'viseme_out_of_range', "El evento {$index} usa el visema {$event['value']}; solo se permiten valores de 0 a 6.");
            }

            if ($event['at_ms'] < 0 || ($durationMs !== null && $event['at_ms'] > $durationMs)) {
                $issues[] = $this->issue('error', 'time_out_of_range', "El evento {$index} está fuera de la duración del audio.");
            }

            if ($index === 0) {
                continue;
            }

            $previous = $events[$index - 1];
            if ($event['at_ms'] < $previous['at_ms']) {
                $issues[] = $this->issue('error', 'timeline_unordered', "El evento {$index} ocurre antes que el evento anterior.");
            }

            if ($event['value'] === $previous['value'] && ($durationMs === null || $event['at_ms'] !== $durationMs)) {
                $issues[] = $this->issue('warning', 'repeated_viseme', "Los eventos {$index} y ".($index + 1).' repiten el mismo visema consecutivamente.');
            }

            if ($previous['value'] === self::REST_VALUE && $event['at_ms'] - $previous['at_ms'] > self::LONG_REST_MS) {
                $issues[] = $this->issue('warning', 'long_rest', 'Hay una pausa en REST de más de 1.2 segundos; conviene revisar la sincronización.');
            }
        }
    }

    /**
     * @param  list<array{at_ms: int, value: int}>  $events
     * @param  list<array{severity: 'warning'|'error', code: string, message: string}>  $issues
     */
    private function inspectSpeechCoverage(string $text, array $events, array &$issues): void
    {
        if (preg_match('/[aeiouáéíóúü]/iu', $text) !== 1) {
            return;
        }

        $hasSpokenViseme = collect($events)->contains(fn (array $event): bool => $event['value'] >= 1 && $event['value'] <= 5);
        if (! $hasSpokenViseme) {
            $issues[] = $this->issue('error', 'speech_viseme_missing', 'El texto contiene vocales, pero no hay visemas hablados para revisarlas.');
        }
    }

    /**
     * @param  list<array{at_ms: int, value: int}>  $events
     * @param  list<array{severity: 'warning'|'error', code: string, message: string}>  $issues
     */
    private function inspectMeasuredDuration(array $events, int $timelineDurationMs, int $measuredDurationMs, array &$issues): void
    {
        if (abs($timelineDurationMs - $measuredDurationMs) > 100) {
            $issues[] = $this->issue('warning', 'duration_mismatch', 'La duración registrada y la duración medida del MP3 difieren en más de 100 ms.');
        }

        foreach ($events as $event) {
            if ($event['at_ms'] > $measuredDurationMs + 100) {
                $issues[] = $this->issue('error', 'time_beyond_audio', 'Hay un visema programado después del final medido del MP3.');

                return;
            }
        }
    }

    private function measuredDuration(AudioAsset $asset): ?int
    {
        if (! $asset->path || ! Storage::disk('public')->exists($asset->path)) {
            return null;
        }

        $path = Storage::disk('public')->path($asset->path);
        $process = new Process([
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=nokey=1:noprint_wrappers=1',
            $path,
        ]);
        $process->setTimeout(5);
        $process->run();

        if (! $process->isSuccessful() || ! is_numeric(trim($process->getOutput()))) {
            return null;
        }

        return (int) round((float) trim($process->getOutput()) * 1000);
    }

    /** @return array{severity: 'warning'|'error', code: string, message: string} */
    private function issue(string $severity, string $code, string $message): array
    {
        return compact('severity', 'code', 'message');
    }

    /** @param list<array{severity: 'warning'|'error', code: string, message: string}> $issues */
    private function statusFor(array $issues): string
    {
        if (collect($issues)->contains(fn (array $issue): bool => $issue['severity'] === 'error')) {
            return 'error';
        }

        return $issues === [] ? 'ok' : 'warning';
    }
}
