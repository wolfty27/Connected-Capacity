<?php

namespace App\Services\BundleEngine;

use App\Models\Patient;
use App\Models\InterraiAssessment;
use App\Services\BundleEngine\Contracts\AssessmentIngestionServiceInterface;
use App\Services\BundleEngine\Contracts\AssessmentMapperInterface;
use App\Services\BundleEngine\DTOs\PatientNeedsProfile;
use App\Services\BundleEngine\Derivers\EpisodeTypeDeriver;
use App\Services\BundleEngine\Derivers\RehabPotentialDeriver;
use App\Services\BundleEngine\Mappers\HcAssessmentMapper;
use App\Services\BundleEngine\Mappers\CaAssessmentMapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AssessmentIngestionService
 *
 * Builds PatientNeedsProfile from various assessment sources.
 * This is the main entry point for assessment data ingestion.
 *
 * Data Source Priority:
 * 1. InterRAI HC (Home Care) - Full assessment, highest confidence
 * 2. InterRAI CA (Contact Assessment) - Quick intake, medium confidence
 * 3. InterRAI BMHS (Behavioural/Mental Health Screener) - Supplement
 * 4. Referral data - Hospital discharge info, lower confidence
 *
 * Key Principle: We NEVER block bundling due to missing data.
 * ANY of (HC, CA, referral) is sufficient for generating bundles.
 *
 * @see docs/CC21_AI_Bundle_Engine_Design.md Section 2.1
 */
class AssessmentIngestionService implements AssessmentIngestionServiceInterface
{
    /**
     * Cache TTL for patient profiles (in seconds).
     */
    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Cache prefix for patient profiles.
     */
    protected const CACHE_PREFIX = 'bundle_engine:patient_profile:';

    /**
     * Assessment mappers.
     *
     * @var array<string, AssessmentMapperInterface>
     */
    protected array $mappers;

    /**
     * Episode type deriver.
     */
    protected EpisodeTypeDeriver $episodeTypeDeriver;

    /**
     * Rehab potential deriver.
     */
    protected RehabPotentialDeriver $rehabPotentialDeriver;

    public function __construct(
        ?HcAssessmentMapper $hcMapper = null,
        ?CaAssessmentMapper $caMapper = null,
        ?EpisodeTypeDeriver $episodeTypeDeriver = null,
        ?RehabPotentialDeriver $rehabPotentialDeriver = null
    ) {
        $this->mappers = [
            'hc' => $hcMapper ?? new HcAssessmentMapper(),
            'ca' => $caMapper ?? new CaAssessmentMapper(),
        ];
        $this->episodeTypeDeriver = $episodeTypeDeriver ?? new EpisodeTypeDeriver();
        $this->rehabPotentialDeriver = $rehabPotentialDeriver ?? new RehabPotentialDeriver();
    }

    /**
     * Build a PatientNeedsProfile for a given patient.
     *
     * @inheritDoc
     */
    public function buildPatientNeedsProfile(Patient $patient, array $options = []): PatientNeedsProfile
    {
        $forceRefresh = $options['force_refresh'] ?? false;
        $cacheKey = self::CACHE_PREFIX . $patient->id;

        // Check cache unless force refresh
        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Get assessment cutoff date
            $cutoffDays = $options['assessment_cutoff_days'] ?? 365;
            $cutoffDate = now()->subDays($cutoffDays);

            // Fetch available assessments
            $hcAssessment = $this->getLatestAssessment($patient, 'hc', $cutoffDate);
            $caAssessment = $this->getLatestAssessment($patient, 'ca', $cutoffDate);
            $bmhsAssessment = $this->getLatestAssessment($patient, 'bmhs', $cutoffDate);

            // Get referral if available (with graceful fallback)
            $referral = null;
            if ($options['include_referral'] ?? true) {
                try {
                    $referral = $patient->referrals()->latest()->first();
                } catch (\Exception $e) {
                    Log::debug('No referrals relationship or data for patient', ['patient_id' => $patient->id]);
                }
            }

            // Build profile from available data
            $profile = $this->buildFromSources(
                $patient,
                $hcAssessment,
                $caAssessment,
                $bmhsAssessment,
                $referral
            );

            // Cache the profile
            Cache::put($cacheKey, $profile, self::CACHE_TTL);

            return $profile;
        } catch (\Exception $e) {
            Log::error('Failed to build PatientNeedsProfile', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);

            // Return minimal profile on failure
            return PatientNeedsProfile::minimal($patient->id);
        }
    }

    /**
     * Check if a patient has sufficient data for profile building.
     *
     * @inheritDoc
     */
    public function hasSufficientData(Patient $patient): bool
    {
        $sources = $this->getAvailableDataSources($patient);

        return $sources['has_hc'] || $sources['has_ca'] || $sources['has_referral'];
    }

    /**
     * Get available data sources for a patient.
     *
     * @inheritDoc
     */
    public function getAvailableDataSources(Patient $patient): array
    {
        $cutoffDate = now()->subYear();

        $hcAssessment = $this->getLatestAssessment($patient, 'hc', $cutoffDate);
        $caAssessment = $this->getLatestAssessment($patient, 'ca', $cutoffDate);
        $bmhsAssessment = $this->getLatestAssessment($patient, 'bmhs', $cutoffDate);
        $referral = $patient->referrals()->latest()->first();

        return [
            'has_hc' => $hcAssessment !== null,
            'hc_date' => $hcAssessment?->assessment_date?->toDateString(),
            'has_ca' => $caAssessment !== null,
            'ca_date' => $caAssessment?->assessment_date?->toDateString(),
            'has_bmhs' => $bmhsAssessment !== null,
            'bmhs_date' => $bmhsAssessment?->assessment_date?->toDateString(),
            'has_referral' => $referral !== null,
            'referral_source' => $referral?->source ?? $referral?->referral_source ?? null,
            'has_family_input' => false, // TODO: Implement when family input system is available
        ];
    }

    /**
     * Invalidate cached profile for a patient.
     *
     * @inheritDoc
     */
    public function invalidateCache(Patient $patient): void
    {
        Cache::forget(self::CACHE_PREFIX . $patient->id);
    }

    /**
     * Build profile from available data sources.
     */
    protected function buildFromSources(
        Patient $patient,
        ?InterraiAssessment $hcAssessment,
        ?InterraiAssessment $caAssessment,
        ?InterraiAssessment $bmhsAssessment,
        $referral
    ): PatientNeedsProfile {
        // Merge data from all sources
        $mergedData = [];
        $confidenceFactors = [];

        // Map HC assessment (highest priority)
        if ($hcAssessment) {
            $hcData = $this->mappers['hc']->mapToProfileFields($hcAssessment);
            $mergedData = array_merge($mergedData, $hcData);
            $confidenceFactors[] = 1.0;
        }

        // Fallback: If no RUG group from assessment, try patient's direct RUG classification
        if (empty($mergedData['rugGroup'])) {
            $patientRug = $patient->latestRugClassification;
            if ($patientRug) {
                $mergedData['rugGroup'] = $patientRug->rug_group;
                $mergedData['rugCategory'] = $patientRug->rug_category;
                $mergedData['rugNumericRank'] = $patientRug->numeric_rank;
            }
        }

        // Map CA assessment (fill gaps)
        if ($caAssessment) {
            $caData = $this->mappers['ca']->mapToProfileFields($caAssessment);
            // Only merge fields not already populated by HC
            foreach ($caData as $key => $value) {
                if (!isset($mergedData[$key]) || $mergedData[$key] === null || $mergedData[$key] === 0) {
                    $mergedData[$key] = $value;
                }
            }
            $confidenceFactors[] = 0.7;
        }

        // Map BMHS assessment (supplement behavioural/MH data)
        if ($bmhsAssessment) {
            $bmhsData = $this->mapBmhsAssessment($bmhsAssessment);
            // Merge BMHS-specific fields
            $mergedData = array_merge($mergedData, $bmhsData);
            $confidenceFactors[] = 0.5;
        }

        // Extract data from referral
        if ($referral) {
            $referralData = $this->extractFromReferral($referral);
            // Only merge fields not already populated
            foreach ($referralData as $key => $value) {
                if (!isset($mergedData[$key]) || $mergedData[$key] === null) {
                    $mergedData[$key] = $value;
                }
            }
            $confidenceFactors[] = 0.4;
        }

        // Derive episode type
        $episodeType = $this->episodeTypeDeriver->derive($patient, $mergedData, $referral);
        $mergedData['episodeType'] = $episodeType;

        // Derive rehab potential
        $rehabResult = $this->rehabPotentialDeriver->derive($mergedData, $episodeType, $referral);
        $mergedData['hasRehabPotential'] = $rehabResult['hasRehabPotential'];
        $mergedData['rehabPotentialScore'] = $rehabResult['score'];

        // Calculate confidence and completeness
        $confidence = $this->calculateConfidenceLevel($confidenceFactors, $mergedData);
        $completeness = $this->calculateCompletenessScore($mergedData);

        // Determine primary assessment
        $primaryType = $hcAssessment ? 'hc' : ($caAssessment ? 'ca' : 'referral_only');
        $primaryDate = $hcAssessment?->assessment_date
            ?? $caAssessment?->assessment_date
            ?? null;

        // Build the profile
        return new PatientNeedsProfile(
            patientId: $patient->id,
            profileGeneratedAt: Carbon::now(),
            profileVersion: '1.0',

            // Data source tracking
            primaryAssessmentType: $primaryType,
            primaryAssessmentDate: $primaryDate,
            hasFullHcAssessment: $hcAssessment !== null,
            hasCaAssessment: $caAssessment !== null,
            hasBmhsAssessment: $bmhsAssessment !== null,
            hasReferralData: $referral !== null,
            dataCompletenessScore: $completeness,

            // Case classification
            rugGroup: $mergedData['rugGroup'] ?? null,
            rugCategory: $mergedData['rugCategory'] ?? null,
            needsCluster: $mergedData['needsCluster'] ?? null,
            episodeType: $mergedData['episodeType'] ?? null,
            rugNumericRank: $mergedData['rugNumericRank'] ?? null,

            // Functional needs
            adlSupportLevel: $mergedData['adlSupportLevel'] ?? 0,
            iadlSupportLevel: $mergedData['iadlSupportLevel'] ?? 0,
            mobilityComplexity: $mergedData['mobilityComplexity'] ?? 0,
            specificAdlNeeds: $mergedData['specificAdlNeeds'] ?? null,

            // Cognitive & Behavioural
            cognitiveComplexity: $mergedData['cognitiveComplexity'] ?? 0,
            behaviouralComplexity: $mergedData['behaviouralComplexity'] ?? 0,
            mentalHealthComplexity: $mergedData['mentalHealthComplexity'] ?? 0,
            hasWanderingRisk: $mergedData['hasWanderingRisk'] ?? false,
            hasAggressionRisk: $mergedData['hasAggressionRisk'] ?? false,
            behaviouralFlags: $mergedData['behaviouralFlags'] ?? null,

            // Clinical risk profile
            fallsRiskLevel: $mergedData['fallsRiskLevel'] ?? 0,
            skinIntegrityRisk: $mergedData['skinIntegrityRisk'] ?? 0,
            painManagementNeed: $mergedData['painManagementNeed'] ?? 0,
            continenceSupport: $mergedData['continenceSupport'] ?? 0,
            healthInstability: $mergedData['healthInstability'] ?? 0,
            clinicalRiskFlags: $mergedData['clinicalRiskFlags'] ?? null,
            activeConditions: $mergedData['activeConditions'] ?? null,

            // Treatment context
            hasRehabPotential: $mergedData['hasRehabPotential'] ?? false,
            rehabPotentialScore: $mergedData['rehabPotentialScore'] ?? 0,
            requiresExtensiveServices: $mergedData['requiresExtensiveServices'] ?? false,
            extensiveServices: $mergedData['extensiveServices'] ?? null,
            weeklyTherapyMinutes: $mergedData['weeklyTherapyMinutes'] ?? 0,

            // Support context
            caregiverAvailabilityScore: $mergedData['caregiverAvailabilityScore'] ?? 0,
            caregiverStressLevel: $mergedData['caregiverStressLevel'] ?? 0,
            livesAlone: $mergedData['livesAlone'] ?? false,
            caregiverRequiresRelief: $mergedData['caregiverRequiresRelief'] ?? false,
            socialSupportScore: $mergedData['socialSupportScore'] ?? 0,

            // Technology
            technologyReadiness: $mergedData['technologyReadiness'] ?? 0,
            hasInternet: $mergedData['hasInternet'] ?? false,
            hasPers: $mergedData['hasPers'] ?? false,
            suitableForRpm: $mergedData['suitableForRpm'] ?? false,

            // Environment
            regionCode: $patient->region_code ?? $patient->region?->code ?? null,
            regionName: $patient->region?->name ?? null,
            travelComplexityScore: $mergedData['travelComplexityScore'] ?? 0,
            isRural: $mergedData['isRural'] ?? false,

            // Confidence
            confidenceLevel: $confidence,
            missingDataFields: $this->getMissingFields($mergedData),
            dataQualityNotes: $this->generateDataQualityNotes($mergedData, $hcAssessment, $caAssessment),
        );
    }

    /**
     * Get the latest assessment of a given type.
     */
    protected function getLatestAssessment(Patient $patient, string $type, Carbon $cutoffDate): ?InterraiAssessment
    {
        return InterraiAssessment::where('patient_id', $patient->id)
            ->where('assessment_type', $type)
            ->where('assessment_date', '>=', $cutoffDate)
            ->with('latestRugClassification') // Eager load RUG classification
            ->orderBy('assessment_date', 'desc')
            ->first();
    }

    /**
     * Map BMHS assessment data.
     */
    protected function mapBmhsAssessment(InterraiAssessment $assessment): array
    {
        $rawItems = $assessment->raw_items ?? [];

        // BMHS-specific fields (supplement behavioural/MH data)
        return [
            'hasBmhsAssessment' => true,
            'mentalHealthComplexity' => $this->deriveMentalHealthComplexity($rawItems),
            // BMHS can refine behavioural complexity
        ];
    }

    /**
     * Derive mental health complexity from BMHS items.
     */
    protected function deriveMentalHealthComplexity(array $rawItems): int
    {
        // VERIFY: Check actual BMHS field names
        $depression = (int) ($rawItems['depression_rating'] ?? 0);
        $anxiety = (int) ($rawItems['anxiety_scale'] ?? 0);
        $psychosis = (int) ($rawItems['psychosis_indicators'] ?? 0);

        // Simple scoring
        $score = 0;
        if ($depression >= 3) $score++;
        if ($anxiety >= 3) $score++;
        if ($psychosis > 0) $score++;

        return min(3, $score);
    }

    /**
     * Extract relevant data from referral.
     */
    protected function extractFromReferral($referral): array
    {
        $data = [
            'hasReferralData' => true,
        ];

        // Extract tech indicators
        if ($referral->has_internet ?? false) {
            $data['hasInternet'] = true;
        }
        if ($referral->has_pers ?? false) {
            $data['hasPers'] = true;
        }

        // Extract geographic data
        if ($referral->is_rural ?? false) {
            $data['isRural'] = true;
        }

        // Extract active conditions if available
        if ($referral->diagnoses ?? null) {
            $data['activeConditions'] = is_array($referral->diagnoses)
                ? $referral->diagnoses
                : json_decode($referral->diagnoses, true);
        }

        return $data;
    }

    /**
     * Calculate confidence level based on data sources.
     */
    protected function calculateConfidenceLevel(array $factors, array $data): string
    {
        if (empty($factors)) {
            return 'low';
        }

        // Weight by data quality
        $maxFactor = max($factors);

        // HC assessment = high confidence
        if ($maxFactor >= 1.0 && ($data['hasFullHcAssessment'] ?? false)) {
            return 'high';
        }

        // CA assessment = medium confidence
        if ($maxFactor >= 0.7) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Calculate data completeness score.
     */
    protected function calculateCompletenessScore(array $data): float
    {
        $requiredFields = [
            'adlSupportLevel',
            'cognitiveComplexity',
            'healthInstability',
            'fallsRiskLevel',
            'episodeType',
        ];

        $populatedCount = 0;
        foreach ($requiredFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== 0) {
                $populatedCount++;
            }
        }

        return $populatedCount / count($requiredFields);
    }

    /**
     * Get list of missing fields.
     */
    protected function getMissingFields(array $data): array
    {
        $importantFields = [
            'rugGroup' => 'RUG Classification',
            'adlSupportLevel' => 'ADL Support Level',
            'cognitiveComplexity' => 'Cognitive Complexity',
            'healthInstability' => 'Health Instability',
            'weeklyTherapyMinutes' => 'Therapy Minutes',
        ];

        $missing = [];
        foreach ($importantFields as $field => $label) {
            if (!isset($data[$field]) || $data[$field] === null) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * Generate data quality notes.
     */
    protected function generateDataQualityNotes(array $data, ?InterraiAssessment $hc, ?InterraiAssessment $ca): string
    {
        $notes = [];

        if ($hc) {
            $notes[] = 'Full HC assessment available';
        } elseif ($ca) {
            $notes[] = 'CA assessment only - RUG derived from needs cluster';
        } else {
            $notes[] = 'Limited assessment data - using referral/defaults';
        }

        if (!isset($data['rugGroup'])) {
            $notes[] = 'No RUG classification - using needs cluster for template selection';
        }

        return implode('. ', $notes);
    }
}

