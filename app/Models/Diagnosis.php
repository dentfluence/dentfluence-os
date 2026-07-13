<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Diagnosis extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = 'diagnosis_masters';

    /** Knowledge Bank — ranked Treatment options for this diagnosis. */
    public function treatmentOptions(): HasMany
    {
        return $this->hasMany(DiagnosisTreatmentOption::class, 'diagnosis_id')
            ->orderBy('sort_order');
    }
}
