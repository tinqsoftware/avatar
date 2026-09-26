<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationVersionRequest;
use App\Jobs\GenerateStaticAudio;
use App\Models\Avatar;
use App\Models\ConversationVersion;
use App\Services\ConversationTree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;

class ConversationVersionController extends Controller
{
    public function create(Avatar $avatar): View
    {
        abort_unless(config('avatar.audio_role') === 'studio', 404);

        return view('admin.versions.create', compact('avatar'));
    }

    public function store(StoreConversationVersionRequest $request, Avatar $avatar, ConversationTree $conversationTree): RedirectResponse
    {
        abort_unless(config('avatar.audio_role') === 'studio', 404);
        try {
            $tree = $conversationTree->parse($request->string('tree_json')->toString());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['tree_json' => $exception->getMessage()]);
        }

        $version = $avatar->conversationVersions()->create([
            'label' => $request->string('label')->toString(),
            'tree' => $tree,
        ]);

        return redirect()->route('admin.avatars.show', $avatar)->with('success', "Versión {$version->label} guardada como borrador.");
    }

    public function publish(Avatar $avatar, ConversationVersion $version, ConversationTree $conversationTree): RedirectResponse
    {
        abort_unless(config('avatar.audio_role') === 'studio', 404);
        abort_unless($version->avatar_id === $avatar->id, 404);
        if (! config('avatar.voicebox_enabled')) {
            return back()->withErrors(['publish' => 'Configura Voicebox local antes de generar los MP3 estáticos.']);
        }
        if (! $version->preview_approved_at) {
            return back()->withErrors(['publish' => 'Aprueba primero las cinco pruebas de voz.']);
        }
        if ($avatar->usesClonedVoice() && (! $avatar->voice_sample_path || ! Storage::disk('local')->exists($avatar->voice_sample_path))) {
            return back()->withErrors(['publish' => 'Este avatar necesita su muestra privada de voz antes de publicar.']);
        }

        DB::transaction(function () use ($avatar, $version, $conversationTree): void {
            $version->audioAssets()->delete();
            foreach ($conversationTree->lines($version->tree) as $key => $text) {
                $asset = $version->audioAssets()->create(['asset_key' => $key, 'text' => $text]);
                GenerateStaticAudio::dispatch($asset->id)->afterCommit();
            }
            $version->update(['status' => 'generating', 'published_at' => null]);
            if (! $avatar->publishedConversation()) {
                $avatar->update(['status' => 'generating']);
            }
        });

        return back()->with('success', 'El lote se está generando en el estudio local. Se habilitará el envío al VPS cuando todos estén listos.');
    }

    public function show(Avatar $avatar, ConversationVersion $version): View
    {
        abort_unless($version->avatar_id === $avatar->id, 404);

        return view('admin.versions.show', ['avatar' => $avatar, 'version' => $version->load('audioAssets')]);
    }
}
