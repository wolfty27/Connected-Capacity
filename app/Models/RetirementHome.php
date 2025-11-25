<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetirementHome extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'central_dining_room', 'private_dining_room', 'concierge', 'hairdresser',
        'library', 'movie_theatre', 'pets_allowed', 'pool', 'special_outings', 'tuck_shop', 'bar', 'computer_lounge',
        'gym', 'art_studio', 'sun_room', 'wellness_centre', 'religious_centre', 'outdoor_area', 'type', 'website'];
    
        public function user()
    {
        return $this->belongsTo(User::class);
    }

}
