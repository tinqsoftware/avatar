<?php

namespace App\Http\Controllers;

use App\Models\Avatar;
use App\Services\ConversationCoverage;
use App\Services\StaticConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicAvatarController extends Controller
{
    public function landing(Request $request): View
    {
        $avatar = $this->avatar($request);

        return view('public.landing', [
            'avatar' => $avatar,
            'socialImageUrl' => $this->publicStorageUrl($avatar->social_image_path),
        ]);
    }

    public function call(Request $request, StaticConversationService $conversation): View
    {
        return $this->callView($request, $conversation, false);
    }

    public function previewCall(Request $request, StaticConversationService $conversation): View
    {
        return $this->callView($request, $conversation, true);
    }

    public function status(Request $request, StaticConversationService $conversation, ConversationCoverage $coverage): JsonResponse
    {
        return $this->statusResponse($request, $conversation, $coverage, false);
    }

    public function previewStatus(Request $request, StaticConversationService $conversation, ConversationCoverage $coverage): JsonResponse
    {
        return $this->statusResponse($request, $conversation, $coverage, true);
    }

    public function greeting(Request $request, StaticConversationService $conversation): JsonResponse
    {
        return response()->json($conversation->greeting($this->avatar($request)));
    }

    public function previewGreeting(Request $request, StaticConversationService $conversation): JsonResponse
    {
        return response()->json($conversation->greeting($this->avatar($request), true));
    }

    public function previewMessage(Request $request, StaticConversationService $conversation): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:1', 'max:750']]);

        return response()->json($conversation->respond($this->avatar($request), $data['message'], true));
    }

    public function previewPoll(Request $request, string $ticket, StaticConversationService $conversation): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{16,120}$/', $ticket) === 1, 404);

        return response()->json($conversation->poll($this->avatar($request), $ticket, true));
    }

    private function callView(Request $request, StaticConversationService $conversation, bool $preview): View
    {
        $avatar = $this->avatar($request);

        return view('public.call', [
            'avatar' => $avatar,
            'preview' => $preview,
            'backgroundUrl' => $this->publicStorageUrl($avatar->background_path),
            'socialImageUrl' => $this->publicStorageUrl($avatar->social_image_path),
            'riveUrl' => $this->publicStorageUrl($avatar->rive_path) ?? asset('assets/avatar/anita.riv'),
            'topicTitles' => $conversation->topicTitles($avatar, $preview),
        ]);
    }

    private function publicStorageUrl(?string $path): ?string
    {
        return $path ? '/storage/'.ltrim($path, '/') : null;
    }

    private function statusResponse(Request $request, StaticConversationService $conversation, ConversationCoverage $coverage, bool $preview): JsonResponse
    {
        $avatar = $this->avatar($request);
        $progressVersion = $avatar->conversationVersions()
            ->whereIn('status', ['staging', 'published'])
            ->latest('id')
            ->first();

        return response()->json([
            'ready' => $conversation->canStart($avatar, $preview),
            'preview' => $preview,
            'max_recording_seconds' => 15,
            'progress' => $progressVersion ? $coverage->summarize($progressVersion) : null,
            'avatar' => [
                'name' => $avatar->name,
                'slug' => $avatar->slug,
            ],
        ]);
    }

    public function message(Request $request, StaticConversationService $conversation): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:1', 'max:750']]);

        return response()->json($conversation->respond($this->avatar($request), $data['message']));
    }

    public function poll(Request $request, string $ticket, StaticConversationService $conversation): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{16,120}$/', $ticket) === 1, 404);

        return response()->json($conversation->poll($this->avatar($request), $ticket));
    }

    private function avatar(Request $request): Avatar
    {
        $avatar = $request->attributes->get('avatar');
        abort_unless($avatar instanceof Avatar, 404);

        return $avatar;
    }
}
