<?php

namespace App\Http\Controllers;

use App\Models\Avatar;
use App\Services\StaticConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicAvatarController extends Controller
{
    public function landing(Request $request): View
    {
        return view('public.landing', ['avatar' => $this->avatar($request)]);
    }

    public function call(Request $request): View
    {
        $avatar = $this->avatar($request);

        return view('public.call', [
            'avatar' => $avatar,
            'backgroundUrl' => $avatar->background_path
                ? Storage::disk('public')->url($avatar->background_path)
                : null,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $avatar = $this->avatar($request);

        return response()->json([
            'ready' => $avatar->publishedConversation() !== null,
            'max_recording_seconds' => 15,
            'avatar' => [
                'name' => $avatar->name,
                'slug' => $avatar->slug,
            ],
        ]);
    }

    public function greeting(Request $request, StaticConversationService $conversation): JsonResponse
    {
        return response()->json($conversation->greeting($this->avatar($request)));
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
