<?php

namespace App\Services;

use App\Models\ConversationVersion;
use InvalidArgumentException;

class ConversationCoverage
{
    public function __construct(private readonly ConversationTree $conversationTree) {}

    /**
     * @return array{total_assets: int, ready_assets: int, synced_assets: int, generating_assets: int, failed_assets: int, total_percent: int, topics_total: int, topics_covered: int, topics_percent: int, variant_rounds: list<array{number: int, total: int, ready: int, percent: int, complete: bool}>, complete: bool}
     */
    public function summarize(ConversationVersion $version): array
    {
        $version->loadMissing('audioAssets');
        $assets = $version->audioAssets;

        try {
            $tree = $this->conversationTree->validate($version->tree);
        } catch (InvalidArgumentException) {
            return $this->emptySummary($assets->count());
        }

        $readyKeys = $assets->where('status', 'ready')->pluck('asset_key')->flip();
        $topics = collect($tree['topics']);
        $totalAssets = count($this->conversationTree->lines($tree));
        $readyAssets = $assets->where('status', 'ready')->count();
        $topicsTotal = $topics->count();
        $topicsCovered = $topics
            ->filter(fn (array $topic): bool => $readyKeys->has("topic.{$topic['id']}.summary.0"))
            ->count();
        $roundCount = max(1, (int) $topics->map(fn (array $topic): int => count($topic['summary']['variants']))->max());
        $rounds = [];
        for ($index = 0; $index < $roundCount; $index++) {
            $eligibleTopics = $topics->filter(fn (array $topic): bool => array_key_exists($index, $topic['summary']['variants']));
            $readyTopics = $eligibleTopics
                ->filter(fn (array $topic): bool => $readyKeys->has("topic.{$topic['id']}.summary.{$index}"))
                ->count();
            $total = $eligibleTopics->count();
            $rounds[] = [
                'number' => $index + 1,
                'total' => $total,
                'ready' => $readyTopics,
                'percent' => $total === 0 ? 100 : (int) round(($readyTopics / $total) * 100),
                'complete' => $total === $readyTopics,
            ];
        }

        return [
            'total_assets' => $totalAssets,
            'ready_assets' => $readyAssets,
            'synced_assets' => $assets->filter(fn ($asset): bool => $asset->synced_at !== null)->count(),
            'generating_assets' => $assets->where('status', 'generating')->count(),
            'failed_assets' => $assets->where('status', 'failed')->count(),
            'total_percent' => $totalAssets === 0 ? 0 : (int) round(($readyAssets / $totalAssets) * 100),
            'topics_total' => $topicsTotal,
            'topics_covered' => $topicsCovered,
            'topics_percent' => $topicsTotal === 0 ? 0 : (int) round(($topicsCovered / $topicsTotal) * 100),
            'variant_rounds' => $rounds,
            'complete' => $totalAssets > 0 && $readyAssets === $totalAssets,
        ];
    }

    /**
     * Legacy draft records can exist before a complete tree has been uploaded.
     * They are not publishable, but the local studio must remain accessible.
     *
     * @return array{total_assets: int, ready_assets: int, synced_assets: int, generating_assets: int, failed_assets: int, total_percent: int, topics_total: int, topics_covered: int, topics_percent: int, variant_rounds: list<array{number: int, total: int, ready: int, percent: int, complete: bool}>, complete: bool}
     */
    private function emptySummary(int $assetCount): array
    {
        return [
            'total_assets' => $assetCount,
            'ready_assets' => 0,
            'synced_assets' => 0,
            'generating_assets' => 0,
            'failed_assets' => 0,
            'total_percent' => 0,
            'topics_total' => 0,
            'topics_covered' => 0,
            'topics_percent' => 0,
            'variant_rounds' => [],
            'complete' => false,
        ];
    }
}
