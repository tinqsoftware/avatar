<?php

namespace App\Models;

use Database\Factories\ConversationVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationVersion extends Model
{
    /** @use HasFactory<ConversationVersionFactory> */
    use HasFactory;

    protected $fillable = ['avatar_id', 'label', 'tree', 'status', 'published_at'];

    protected function casts(): array
    {
        return [
            'tree' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Avatar::class);
    }

    public function audioAssets(): HasMany
    {
        return $this->hasMany(AudioAsset::class);
    }
}
