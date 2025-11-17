<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_relations_are_available()
    {
        $user = User::create([
            'name' => 'Auditor',
            'email' => 'auditor@test.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);

        $patientUser = User::create([
            'name' => 'Audited Patient',
            'email' => 'audited@test.com',
            'password' => bcrypt('secret'),
            'role' => 'patient',
        ]);

        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'hospital_id' => 1,
            'status' => 'Inactive',
            'gender' => 'Male',
        ]);

        $audit = AuditLog::create([
            'user_id' => $user->id,
            'auditable_type' => Patient::class,
            'auditable_id' => $patient->id,
            'action' => 'updated',
            'before' => ['status' => 'Inactive'],
            'after' => ['status' => 'Active'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->assertEquals($patient->id, $audit->auditable->id);
        $this->assertEquals($user->id, $audit->user->id);
        $this->assertEquals('Active', $audit->after['status']);
    }
}
