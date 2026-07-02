<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Idea extends Model
{
    use SoftDeletes;

    protected $table = 'mkt_ideas';

    protected $fillable = [
        'clinic_id',
        'campaign_id',
        'title',
        'description',
        'content_type',
        'platforms',
        'tags',
        'is_ai_generated',
        'status',
        'converted_to',
        'converted_id',
        'cover_image',
        'key_points',
        'notes',
        'festival_date_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'platforms'        => 'array',
        'tags'             => 'array',
        'key_points'       => 'array',
        'is_ai_generated'  => 'boolean',
        'deleted_at'       => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(IdeaAsset::class, 'idea_id');
    }

    public function festivalDate(): BelongsTo
    {
        return $this->belongsTo(FestivalDate::class, 'festival_date_id');
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['idea', 'in_progress']);
    }
}
