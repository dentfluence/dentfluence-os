<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresentationMediaItem extends Model
{
    protected $fillable = [
        'presentation_id',
        'treatment_media_id',
        'included',
    ];

    protected $casts = [
        'included' => 'boolean',
    ];

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }

    public function treatmentMedia(): BelongsTo
    {
        return $this->belongsTo(TreatmentMedia::class);
    }
}
