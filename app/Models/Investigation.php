<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Investigation extends Model { use HasFactory; protected $guarded = []; protected $table = 'investigation_masters'; }
