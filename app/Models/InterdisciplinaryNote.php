<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterdisciplinaryNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'service_assignment_id',
        'author_id',
        'author_role',
        'note_type',
        'content',
        'visible_to_orgs',
    ];

    protected $casts = [
        'visible_to_orgs' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function serviceAssignment()
    {
        return $this->belongsTo(ServiceAssignment::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
