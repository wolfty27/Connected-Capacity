<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Calendly extends Model
{
    use HasFactory;

    protected $fillable = ['hospital_id', 'code', 'access_token', 'refresh_token', 'token_type', 'token_created_at', 'expires_in', 'organization', 'owner'];
}
