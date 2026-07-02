<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'uploaded_by',
        'category',
        'title',
        'original_name',
        'path',
        'mime_type',
        'file_size',
        'notes',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
