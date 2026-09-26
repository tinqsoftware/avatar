<?php

namespace App\Models;

use Database\Factories\AvatarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Avatar extends Model
{
    /** @use HasFactory<AvatarFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'public_title',
        'voice_mode',
        'voice_profile',
        'voice_sample_path',
        'rive_path',
        'background_path',
        'status',
    ];

    public function usesClonedVoice(): bool
    {
        return $this->voice_mode === 'cloned';
    }

    public function conversationVersions(): HasMany
    {
        return $this->hasMany(ConversationVersion::class);
    }

    public function publishedConversation(): ?ConversationVersion
    {
        return $this->conversationVersions()
            ->where('status', 'published')
            ->latest('published_at')
            ->first();
    }
}
