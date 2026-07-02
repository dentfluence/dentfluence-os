<?php

namespace App\Models\Prescription;

use Illuminate\Database\Eloquent\Model;

class RxRouteOfAdmin extends Model
{
    protected $table = 'rx_routes_of_admin';
    protected $fillable = ['name', 'abbreviation', 'is_active'];
}
