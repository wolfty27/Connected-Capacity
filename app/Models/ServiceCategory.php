<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'description'];

    public function serviceTypes()
    {
        return $this->hasMany(ServiceType::class, 'category_id');
    }
}