<?php

namespace Database\Seeders;

use App\Models\ServiceProviderOrganization;
use App\Models\Skill;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * StaffSkillsSeeder
 * 
 * Assigns skills to staff members based on their role.
 * Skills are derived from the Skill metadata model.
 * 
 * Role to skill mappings are based on OHaH service delivery requirements:
 * - RN/RPN: Clinical skills (wound care, IV therapy, medication admin)
 * - PSW: Personal support skills (ADL assistance, mobility support)
 * - OT/PT: Specialized therapy skills
 */
class StaffSkillsSeeder extends Seeder
{
    // Role to skill code mappings
    // Each role gets base skills plus optional additional skills
    protected array $roleSkillMappings = [
        'RN' => [
            'required' => ['WOUND_CARE', 'MED_ADMIN', 'VITAL_SIGNS', 'CATHETER_CARE', 'DIABETES_MGMT', 'PAIN_MGMT'],
            'optional' => ['IV_THERAPY', 'OSTOMY_CARE', 'TRACH_CARE', 'RESP_CARE'],
        ],
        'RPN' => [
            'required' => ['MED_ADMIN', 'VITAL_SIGNS', 'CATHETER_CARE', 'DIABETES_MGMT'],
            'optional' => ['WOUND_CARE', 'PAIN_MGMT'],
        ],
        'NP' => [
            'required' => ['WOUND_CARE', 'MED_ADMIN', 'VITAL_SIGNS', 'IV_THERAPY', 'DIABETES_MGMT', 'PAIN_MGMT'],
            'optional' => ['OSTOMY_CARE', 'TRACH_CARE', 'RESP_CARE'],
        ],
        'PSW' => [
            'required' => ['ADL_ASSIST', 'MOBILITY_ASSIST', 'PERSONAL_HYGIENE', 'MEAL_PREP'],
            'optional' => ['TRANSFER_LIFT', 'DEMENTIA_CARE', 'PALLIATIVE_SUPPORT'],
        ],
        'OT' => [
            'required' => ['ADL_ASSIST', 'MOBILITY_ASSIST', 'HOME_SAFETY'],
            'optional' => ['COGNITIVE_SUPPORT', 'ADAPTIVE_EQUIP'],
        ],
        'PT' => [
            'required' => ['MOBILITY_ASSIST', 'TRANSFER_LIFT', 'FALL_PREVENTION'],
            'optional' => ['RESP_CARE'],
        ],
        'SW' => [
            'required' => ['COGNITIVE_SUPPORT', 'CRISIS_INTERVENTION'],
            'optional' => ['DEMENTIA_CARE', 'PALLIATIVE_SUPPORT'],
        ],
        'SLP' => [
            'required' => ['COGNITIVE_SUPPORT'],
            'optional' => [],
        ],
        'RT' => [
            'required' => ['RESP_CARE', 'TRACH_CARE'],
            'optional' => ['VITAL_SIGNS'],
        ],
        'RD' => [
            'required' => ['MEAL_PREP'],
            'optional' => ['DIABETES_MGMT'],
        ],
        'COORD' => [
            'required' => [],
            'optional' => [],
        ],
    ];
    
    public function run(): void
    {
        $this->command->info('Assigning skills to staff...');
        
        // Get SE Health organization
        $spo = ServiceProviderOrganization::where('slug', 'se-health')->first();
        if (!$spo) {
            $this->command->warn('SE Health organization not found. Skipping.');
            return;
        }
        
        // Get all skills
        $skills = Skill::where('is_active', true)->get()->keyBy('code');
        
        if ($skills->isEmpty()) {
            $this->command->warn('No skills found. Run SkillCatalogSeeder first.');
            return;
        }
        
        // Get all field staff
        $staff = User::where('organization_id', $spo->id)
            ->where('role', User::ROLE_FIELD_STAFF)
            ->with('staffRole')
            ->get();
        
        $assignedCount = 0;
        
        foreach ($staff as $member) {
            $roleCode = $member->staffRole?->code ?? $member->organization_role;
            
            if (!$roleCode || !isset($this->roleSkillMappings[$roleCode])) {
                continue;
            }
            
            $mapping = $this->roleSkillMappings[$roleCode];
            
            // Assign required skills (100% get these)
            foreach ($mapping['required'] as $skillCode) {
                if (isset($skills[$skillCode])) {
                    $this->assignSkill($member, $skills[$skillCode]);
                    $assignedCount++;
                }
            }
            
            // Assign some optional skills (50% chance each)
            foreach ($mapping['optional'] as $skillCode) {
                if (isset($skills[$skillCode]) && mt_rand(1, 100) <= 50) {
                    $this->assignSkill($member, $skills[$skillCode]);
                    $assignedCount++;
                }
            }
        }
        
        $this->command->info("  Assigned {$assignedCount} skills to staff");
    }
    
    /**
     * Assign a skill to a staff member.
     */
    protected function assignSkill(User $staff, Skill $skill): void
    {
        // Skip if already has this skill
        if ($staff->skills()->where('skill_id', $skill->id)->exists()) {
            return;
        }
        
        // Random proficiency based on tenure
        $proficiency = $this->determineProficiency($staff);
        
        // Certification date (if required)
        $certifiedAt = null;
        $expiresAt = null;
        
        if ($skill->requires_certification) {
            // Certified 6-24 months ago
            $certifiedAt = Carbon::now()->subMonths(mt_rand(6, 24));
            
            if ($skill->renewal_period_months) {
                $expiresAt = $certifiedAt->copy()->addMonths($skill->renewal_period_months);
                
                // Some certifications expiring soon (10% chance)
                if (mt_rand(1, 100) <= 10) {
                    $expiresAt = Carbon::now()->addDays(mt_rand(7, 45));
                }
            }
        }
        
        $staff->skills()->attach($skill->id, [
            'proficiency_level' => $proficiency,
            'certified_at' => $certifiedAt,
            'expires_at' => $expiresAt,
        ]);
    }
    
    /**
     * Determine proficiency based on staff tenure.
     */
    protected function determineProficiency(User $staff): string
    {
        $hireDate = $staff->hire_date ?? Carbon::now()->subYears(2);
        $monthsEmployed = $hireDate->diffInMonths(Carbon::now());
        
        if ($monthsEmployed >= 60) {
            return 'expert';
        } elseif ($monthsEmployed >= 36) {
            return 'proficient';
        } elseif ($monthsEmployed >= 12) {
            return 'competent';
        } else {
            return 'basic';
        }
    }
}
