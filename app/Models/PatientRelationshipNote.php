<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientRelationshipNote extends Model
{
    protected $fillable = ['patient_id', 'note', 'note_type', 'tags', 'created_by'];

    protected $casts = ['tags' => 'array'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
