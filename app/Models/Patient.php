<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'date_of_birth',
        'gender',
        'email',
        'address',
        'city',
        'state',
        'pincode',
        'chief_complaint',
        'medical_alert',
        'source',
        'referred_by',
        'branch_id',
        'created_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function notes()
    {
        return $this->hasMany(PatientNote::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function alerts()
    {
        return $this->hasMany(PatientAlert::class);
    }

    public function getDobAttribute()
    {
        return $this->date_of_birth;
    }

    public function setDobAttribute($value)
    {
        $this->attributes['date_of_birth'] = $value;
    }
}
