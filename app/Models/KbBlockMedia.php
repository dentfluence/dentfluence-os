<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KbBlockMedia — KB block ↔ media pivot (frozen §5.1). Media is referenced by
 * KB blocks, never inlined.
 */
class KbBlockMedia extends Model
{
    protected $table = 'kb_block_media';

    protected $fillable = [
        'kb_block_id', 'media_asset_id', 'role', 'sort_order',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(KbBlock::class, 'kb_block_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
