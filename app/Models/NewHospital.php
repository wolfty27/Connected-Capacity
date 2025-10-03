<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;



class NewHospital extends Model
{
    use HasFactory, SoftDeletes;

    public $table='hospitals';
    protected $fillable=['user_id', 'website', 'calendly', 'created_at', 'updated_at', 'id'];
    // protected $appends=['created_at_new', 'hospital_id_new'];



    public function user()
    {
        return $this->belongsTo(User::class);
    }
    // public function getCreatedAtNewAttribute()
    // {
    //     return Carbon::parse($this->created_at)->format('F');
    // }
    // public function getHospitalIdNewAttribute()
    // {
    //     return $this->id;
    // } 
}
