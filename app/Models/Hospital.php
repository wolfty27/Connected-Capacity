<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Patient;

class Hospital extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hospitals';

    protected $fillable = [
        'user_id',
        'documents',
        'website',
        'calendly',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }
}
