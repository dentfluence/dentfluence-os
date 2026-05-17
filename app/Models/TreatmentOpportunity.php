<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentOpportunity extends Model
{
    protected $fillable = [
        'patient_id','type','label','status','priority',
        'follow_up_date','estimated_value','notes','created_by',
    ];

    protected $casts = ['follow_up_date' => 'date'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Handy display label
    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?: ucwords(str_replace('_', ' ', $this->type));
    }

    public static function statusColor(string $status): string
    {
        return match($status) {
            'prospect'   => 'bg-slate-100 text-slate-600',
            'discussed'  => 'bg-blue-50 text-blue-600',
            'quoted'     => 'bg-amber-50 text-amber-600',
            'accepted'   => 'bg-green-50 text-green-700',
            'declined'   => 'bg-red-50 text-red-500',
            'completed'  => 'bg-purple-50 text-purple-700',
            default      => 'bg-gray-100 text-gray-500',
        };
    }
}
