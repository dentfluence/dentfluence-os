<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HuddleNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'date',
        'category',   // wins | lows | failures | concerns
        'body',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
