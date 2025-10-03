<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Patient;
use App\Models\Hospital;
use Carbon\Carbon;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['hospital_id', 'retirement_home_id', 'patient_id', 'status', 'retirement_home_status', 'start_time', 'end_time', 'event_uri', 'invitee_uri','updated_at'];

    protected $appends=['updated_at_new', 'patient_id_new'];

    public function patient()
    {
        return $this->belongsTo(Patient::class,'patient_id');
    }

    public function hospital()
    {
        return $this->belongsTo(NewHospital::class,'hospital_id');
    }

    public function retirement()
    {
        return $this->belongsTo(RetirementHome::class,'retirement_home_id');
    }

    public function assessment()
    {
        return $this->hasOne(InPersonAssessment::class,'booking_id');
    }

    public function getUpdatedAtNewAttribute()
    {
        return Carbon::parse($this->updated_at)->toDateString();
    }
    public function getPatientIdNewAttribute()
    {
        return $this->patient_id;
    }    
}

