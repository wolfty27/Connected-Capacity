<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referral extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_TRIAGED = 'triaged';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'patient_id',
        'service_type_id',
        'service_provider_organization_id',
        'submitted_by',
        'status',
        'source',
        'intake_notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function organization()
    {
        return $this->belongsTo(ServiceProviderOrganization::class, 'service_provider_organization_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
