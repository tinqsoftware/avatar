<?php

namespace App\Services;

class VisemeTimeline
{
    private const VISEMES = [
        'a' => 1, 'á' => 1,
        'e' => 2, 'é' => 2,
        'i' => 3, 'í' => 3, 'y' => 3,
        'o' => 4, 'ó' => 4,
        'u' => 5, 'ú' => 5, 'ü' => 5,
        'f' => 6, 'v' => 6,
    ];

    /**
     * @param  array<int, mixed>  $words
     * @return array<int, array{at_ms:int,value:int}>
     */
    public function fromWords(array $words, int $durationMs): array
    {
        $events = [['at_ms' => 0, 'value' => 0]];

        foreach ($words as $word) {
            if (! is_array($word) || ! is_string($word['text'] ?? null)) {
                continue;
            }

            $start = max(0, (int) ($word['start_ms'] ?? 0));
            $end = min($durationMs, max($start + 1, (int) ($word['end_ms'] ?? $start + 1)));
            $letters = preg_split('/(?<!^)(?!$)/u', mb_strtolower($word['text'])) ?: [];
            $letters = array_values(array_filter($letters, fn (string $letter): bool => preg_match('/^[a-záéíóúüñ]$/u', $letter) === 1));

            foreach ($letters as $index => $letter) {
                $value = self::VISEMES[$letter] ?? 0;
                $at = (int) round($start + (($end - $start) * $index / max(count($letters), 1)));
                if ($events[array_key_last($events)]['value'] !== $value) {
                    $events[] = ['at_ms' => $at, 'value' => $value];
                }
            }

            if ($events[array_key_last($events)]['value'] !== 0) {
                $events[] = ['at_ms' => $end, 'value' => 0];
            }
        }

        $events[] = ['at_ms' => $durationMs, 'value' => 0];

        return array_values(array_reduce($events, function (array $carry, array $event): array {
            if ($carry === [] || end($carry) !== $event) {
                $carry[] = $event;
            }

            return $carry;
        }, []));
    }
}
