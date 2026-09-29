<?php

namespace Tests\Unit\Services;

use App\Services\VisemeAuditService;
use Tests\TestCase;

class VisemeAuditServiceTest extends TestCase
{
    public function test_it_accepts_a_complete_ordered_viseme_timeline(): void
    {
        $report = app(VisemeAuditService::class)->inspectTimeline('Hola Ica.', 1000, [
            ['at_ms' => 0, 'value' => 0],
            ['at_ms' => 100, 'value' => 4],
            ['at_ms' => 350, 'value' => 1],
            ['at_ms' => 700, 'value' => 3],
            ['at_ms' => 1000, 'value' => 0],
        ]);

        $this->assertSame('ok', $report['status']);
        $this->assertSame([], $report['issues']);
    }

    public function test_it_does_not_warn_about_the_final_rest_anchor(): void
    {
        $report = app(VisemeAuditService::class)->inspectTimeline('Hola.', 1000, [
            ['at_ms' => 0, 'value' => 0],
            ['at_ms' => 350, 'value' => 4],
            ['at_ms' => 900, 'value' => 0],
            ['at_ms' => 1000, 'value' => 0],
        ]);

        $this->assertSame('ok', $report['status']);
        $this->assertSame([], $report['issues']);
    }

    public function test_it_reports_events_outside_the_audio_duration(): void
    {
        $report = app(VisemeAuditService::class)->inspectTimeline('Hola.', 500, [
            ['at_ms' => 0, 'value' => 0],
            ['at_ms' => 600, 'value' => 1],
            ['at_ms' => 500, 'value' => 0],
        ]);

        $this->assertSame('error', $report['status']);
        $this->assertSame('time_out_of_range', $report['issues'][0]['code']);
    }

    public function test_it_reports_an_unordered_timeline(): void
    {
        $report = app(VisemeAuditService::class)->inspectTimeline('Hola.', 1000, [
            ['at_ms' => 0, 'value' => 0],
            ['at_ms' => 600, 'value' => 1],
            ['at_ms' => 500, 'value' => 4],
            ['at_ms' => 1000, 'value' => 0],
        ]);

        $this->assertSame('error', $report['status']);
        $this->assertSame('timeline_unordered', $report['issues'][0]['code']);
    }

    public function test_it_reports_an_invalid_viseme_value(): void
    {
        $report = app(VisemeAuditService::class)->inspectTimeline('Hola.', 1000, [
            ['at_ms' => 0, 'value' => 0],
            ['at_ms' => 400, 'value' => 7],
            ['at_ms' => 1000, 'value' => 0],
        ]);

        $this->assertSame('error', $report['status']);
        $this->assertSame('viseme_out_of_range', $report['issues'][0]['code']);
    }

    public function test_it_reports_vowel_text_without_spoken_visemes(): void
    {
        $report = app(VisemeAuditService::class)->inspectTimeline('Hola Ica.', 1000, [
            ['at_ms' => 0, 'value' => 0],
            ['at_ms' => 1000, 'value' => 0],
        ]);

        $this->assertSame('error', $report['status']);
        $this->assertContains('speech_viseme_missing', array_column($report['issues'], 'code'));
    }

    public function test_it_warns_about_repeated_visemes_and_long_rests(): void
    {
        $report = app(VisemeAuditService::class)->inspectTimeline('Hola.', 2000, [
            ['at_ms' => 0, 'value' => 0],
            ['at_ms' => 1300, 'value' => 0],
            ['at_ms' => 1500, 'value' => 1],
            ['at_ms' => 2000, 'value' => 0],
        ]);

        $this->assertSame('warning', $report['status']);
        $this->assertSame(['repeated_viseme', 'long_rest'], array_column($report['issues'], 'code'));
    }
}
