<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'secondary_contact_name',
        'secondary_contact_relationship',
        'secondary_contact_phone',
        'secondary_contact_email',
        'designated_alc',
        'least_3_days',
        'pcr_covid_test',
        'post_acute',
        'if_yes',
        'length',
        'npc',
        'apc',
        'bk',
    ];
}
