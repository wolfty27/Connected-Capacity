<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;


class Hospital extends Model
{
    // protected $table = 'hospitals';
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'documents', 'website', 'calendly'];

    // public function myuser()
    // {
    //     return $this->belongsTo(User::class);
    // }
    public function user()
    {
        return "test";
    }
}
