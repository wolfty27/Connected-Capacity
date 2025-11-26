<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InPersonAssessment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['assessed_care_level', 'booking_id', 'status', 'tier_id'];

    public function tier()
    {
        return $this->belongsTo(Tier::class,'tier_id');
    }
}
