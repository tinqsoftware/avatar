<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationBundleRequest;
use App\Models\Avatar;
use App\Services\ConversationVersionBundle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InternalAudioSyncController extends Controller
{
    public function avatars(Request $request): JsonResponse
    {
        $this->authorizeSync($request);

        return response()->json([
            'data' => Avatar::query()
                ->with(['conversationVersions' => fn ($query) => $query->where('status', 'published')->latest('published_at')->limit(1)])
                ->orderBy('name')
                ->get()
                ->map(fn (Avatar $avatar): array => [
                    'id' => $avatar->id,
                    'slug' => $avatar->slug,
                    'name' => $avatar->name,
                    'public_title' => $avatar->public_title,
                    'status' => $avatar->status,
                    'published_at' => optional($avatar->conversationVersions->first()?->published_at)->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    public function store(StoreConversationBundleRequest $request, ConversationVersionBundle $bundle): JsonResponse
    {
        $path = $request->file('bundle')->store('avatar-sync/imports', 'local');

        try {
            $result = $bundle->import(Storage::disk('local')->path($path));
        } finally {
            Storage::disk('local')->delete($path);
        }

        return response()->json([
            'avatar' => $result['avatar']->slug,
            'version' => $result['version']->id,
            'asset_count' => $result['asset_count'],
            'status' => 'published',
        ], 201);
    }

    private function authorizeSync(Request $request): void
    {
        $token = config('avatar.sync_token');

        abort_unless(
            config('avatar.audio_role') === 'delivery'
            && is_string($token)
            && $token !== ''
            && hash_equals($token, (string) $request->bearerToken()),
            401,
        );
    }
}
