<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_provider_organization_id',
        'organization_role',
        'default_assignment_scope',
    ];

    protected $casts = [
        'default_assignment_scope' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(ServiceProviderOrganization::class, 'service_provider_organization_id');
    }
}
