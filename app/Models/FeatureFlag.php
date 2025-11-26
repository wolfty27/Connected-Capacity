<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'scope',
        'target_id',
        'description',
        'enabled',
        'payload',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'payload' => 'array',
    ];

    public function scopeForKey($query, string $key)
    {
        return $query->where('key', $key);
    }
}
