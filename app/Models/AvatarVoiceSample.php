<?php

namespace App\Models;

use Database\Factories\AvatarVoiceSampleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvatarVoiceSample extends Model
{
    /** @use HasFactory<AvatarVoiceSampleFactory> */
    use HasFactory;

    protected $fillable = [
        'avatar_id',
        'path',
        'original_name',
        'duration_ms',
        'locale',
        'sha256',
    ];

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Avatar::class);
    }
}
