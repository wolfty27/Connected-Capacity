<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\RUGClassification;
use Illuminate\Database\Seeder;

/**
 * PatientNotesSeeder - Creates clinical notes and narrative summaries for demo patients
 *
 * Seeds OHaH-style narrative summaries based on each patient's RUG classification,
 * plus follow-up notes to demonstrate the notes functionality.
 */
class PatientNotesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating patient notes and narratives...');

        $patients = Patient::with(['latestRugClassification', 'user'])->get();

        $count = 0;
        foreach ($patients as $patient) {
            // Create summary note based on RUG classification
            $this->createSummaryNote($patient);

            // Create 1-2 follow-up notes for active patients
            if (!$patient->is_in_queue) {
                $this->createFollowUpNotes($patient);
            }

            $count++;
        }

        $this->command->info("Created notes for {$count} patients.");
    }

    /**
     * Create the main narrative summary note for a patient.
     */
    protected function createSummaryNote(Patient $patient): void
    {
        $rug = $patient->latestRugClassification;
        $narrative = $this->generateNarrativeSummary($patient, $rug);

        PatientNote::create([
            'patient_id' => $patient->id,
            'author_id' => null, // System-generated
            'source' => PatientNote::SOURCE_OHAH_INTAKE,
            'note_type' => PatientNote::TYPE_SUMMARY,
            'content' => $narrative,
            'created_at' => $patient->created_at ?? now()->subDays(rand(30, 60)),
        ]);
    }

    /**
     * Create follow-up notes for active patients.
     */
    protected function createFollowUpNotes(Patient $patient): void
    {
        $followUpTemplates = [
            [
                'source' => 'SE Health Care Coordination',
                'content' => 'Initial home visit completed. Care plan reviewed with client and family. Environment assessed and found suitable for services. Client engaged and motivated to participate in care goals. No immediate safety concerns identified.',
            ],
            [
                'source' => PatientNote::SOURCE_OHAH,
                'content' => 'Monthly review completed. Services progressing as planned. Client reports satisfaction with current care arrangement. Will continue monitoring for any changes in condition.',
            ],
            [
                'source' => 'SE Health Nursing',
                'content' => 'Nursing assessment completed. Vital signs stable. Medication compliance good. Wound healing progressing well. Continue current care plan.',
            ],
            [
                'source' => PatientNote::SOURCE_OHAH,
                'content' => 'Care coordination call with family. Discussed upcoming reassessment schedule and any concerns. Family appreciative of services provided.',
            ],
        ];

        // Create 1-2 random follow-up notes
        $numNotes = rand(1, 2);
        $selectedNotes = array_rand($followUpTemplates, $numNotes);
        if (!is_array($selectedNotes)) {
            $selectedNotes = [$selectedNotes];
        }

        foreach ($selectedNotes as $index) {
            $template = $followUpTemplates[$index];
            PatientNote::create([
                'patient_id' => $patient->id,
                'author_id' => null,
                'source' => $template['source'],
                'note_type' => PatientNote::TYPE_UPDATE,
                'content' => $template['content'],
                'created_at' => now()->subDays(rand(5, 25)),
            ]);
        }
    }

    /**
     * Generate an OHaH-style narrative summary based on RUG classification.
     */
    protected function generateNarrativeSummary(Patient $patient, ?RUGClassification $rug): string
    {
        $name = $patient->user?->name ?? 'Client';
        $firstName = explode(' ', $name)[0];

        if (!$rug) {
            return "Client {$firstName} has been referred for home care services. Assessment pending to determine appropriate care bundle and service allocation. Initial intake completed and awaiting InterRAI HC assessment.";
        }

        $category = $rug->rug_category ?? 'Unknown';
        $group = $rug->rug_group ?? 'Unknown';

        return match ($category) {
            RUGClassification::CATEGORY_SPECIAL_REHABILITATION => $this->getRehabNarrative($firstName, $group),
            RUGClassification::CATEGORY_EXTENSIVE_SERVICES => $this->getExtensiveServicesNarrative($firstName, $group),
            RUGClassification::CATEGORY_SPECIAL_CARE => $this->getSpecialCareNarrative($firstName, $group),
            RUGClassification::CATEGORY_CLINICALLY_COMPLEX => $this->getClinicallyComplexNarrative($firstName, $group),
            RUGClassification::CATEGORY_IMPAIRED_COGNITION => $this->getImpairedCognitionNarrative($firstName, $group),
            RUGClassification::CATEGORY_BEHAVIOUR_PROBLEMS => $this->getBehaviourNarrative($firstName, $group),
            RUGClassification::CATEGORY_REDUCED_PHYSICAL_FUNCTION => $this->getReducedPhysicalNarrative($firstName, $group),
            default => "Client {$firstName} has been assessed and classified as RUG {$group}. Care plan developed to address identified needs. Ongoing monitoring in place to ensure care goals are met.",
        };
    }

    protected function getRehabNarrative(string $name, string $group): string
    {
        return "Client {$name} is a post-acute rehabilitation patient requiring intensive therapy services following recent hospitalization. Assessment indicates significant potential for functional recovery with appropriate rehabilitation support. Primary goal is to maximize functional independence through structured PT/OT intervention and prevent re-hospitalization. Client demonstrates motivation to participate in therapy goals. Care plan includes intensive rehabilitation services with focus on mobility, strength, and ADL retraining. Family engaged and supportive of rehabilitation objectives.";
    }

    protected function getExtensiveServicesNarrative(string $name, string $group): string
    {
        return "Client {$name} presents with complex medical needs requiring extensive home-based services including specialized treatments. Assessment indicates need for intensive nursing support to maintain stability in the home setting as an alternative to institutional care. Primary goal is to provide hospital-level care at home while monitoring for changes in condition. Care coordination essential given complexity of medical management. Strong family support system in place, engaged in care planning. Will require close monitoring and regular reassessment given medical complexity.";
    }

    protected function getSpecialCareNarrative(string $name, string $group): string
    {
        return "Client {$name} is a high-needs patient with significant clinical complexity and physical dependency requiring comprehensive care support. Assessment indicates multiple comorbidities requiring close monitoring and coordinated intervention. Primary goal is clinical stabilization and prevention of acute episodes while maintaining quality of life in the home. Care plan developed with emphasis on symptom management, fall prevention, and caregiver support. Family capacity to support care has been assessed and additional resources allocated as needed.";
    }

    protected function getClinicallyComplexNarrative(string $name, string $group): string
    {
        return "Client {$name} is a high-needs medically complex patient with significant ADL dependence and multiple chronic conditions requiring ongoing monitoring. Assessment indicates elevated health instability with risk factors for acute episodes. Primary goal is to stabilize at home with intensive nursing, PSW, and clinical support to avoid ED visits and potential LTC admission. Care plan focuses on proactive condition management, medication adherence, and early intervention for changes in status. Regular reassessment scheduled to monitor trajectory and adjust services as needed.";
    }

    protected function getImpairedCognitionNarrative(string $name, string $group): string
    {
        return "Client {$name} is living with moderate to significant cognitive impairment affecting daily functioning and safety. Assessment indicates need for structured support to maintain safety in the home environment. Primary care focus is on cognitive support, structured daily routines, and caregiver coaching to maintain safety and quality of life. PSW services allocated to support personal care, meal preparation, and supervision. Family caregiver involved in care planning with respite services available to prevent burnout. Monitoring in place for changes in cognitive or behavioural status.";
    }

    protected function getBehaviourNarrative(string $name, string $group): string
    {
        return "Client {$name} is living with cognitive impairment and exhibiting responsive behaviours consistent with dementia-related behavioural symptoms. Assessment indicates need for specialized behavioural support approaches and structured environment. Care plan developed in alignment with BSO (Behavioural Supports Ontario) principles. Requires structured daily PSW support with staff trained in gentle persuasive approaches. Caregiver coaching provided to support effective management of responsive behaviours. Safety monitoring in place given wandering/elopement risk. Will benefit from consistent staffing to build rapport and trust.";
    }

    protected function getReducedPhysicalNarrative(string $name, string $group): string
    {
        return "Client {$name} has primarily physical functional limitations with minimal cognitive impairment. Assessment indicates need for support with personal care, transfers, and home safety to maintain independence. Focus of care is to support ADLs, mobility, and home safety in order to delay or prevent long-term care placement. Client remains cognitively intact and able to direct own care. PSW services allocated for personal care support with focus on maintaining dignity and maximizing client independence. Falls prevention strategies implemented. Goal is to maintain current functional level and quality of life in the community.";
    }
}
