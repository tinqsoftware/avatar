<?php

namespace App\Models;

use Database\Factories\AudioAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioAsset extends Model
{
    /** @use HasFactory<AudioAssetFactory> */
    use HasFactory;

    protected $fillable = [
        'conversation_version_id',
        'asset_key',
        'text',
        'path',
        'mime_type',
        'duration_ms',
        'visemes',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return ['visemes' => 'array'];
    }

    public function conversationVersion(): BelongsTo
    {
        return $this->belongsTo(ConversationVersion::class);
    }
}
