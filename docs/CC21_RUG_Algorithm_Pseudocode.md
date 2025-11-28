# CC2.1 – Bundle Engine Algorithm (Pseudocode)

**Goal:** Port CIHI’s RUG-III/HC SAS-based grouper into a Laravel-oriented pipeline and connect it to the CC2.1 Bundle Engine.

---

## 1. Overall Flow

```text
Assessment (interRAI HC) 
  → RUGClassificationService (RUG group)
    → BundleEligibilityService (which stream?)
      → CareBundleTemplateRepository (candidate templates)
        → CareBundleBuilderService
          → BundleConfigurationRuleEngine + CostEngine
            → CareBundle (instance)
              → CarePlanService
                → CarePlan (published)

2. RUG Classification Pseudocode

class RUGClassificationService
{
    public function classify(Assessment $assessment): RUGClassification
    {
        $data = $assessment->toICodeArray(); // map raw JSON → keyed iCODE array

        // 1. Validate ranges as per CIHI spec
        if (!$this->fieldsAreValid($data)) {
            throw new InvalidAssessmentException(...);
        }

        // 2. Compute Cognitive Performance Scale (sCPS)
        $sCPS = $this->computeCPS($data);

        // 3. Compute temporary variables used by RUG logic
        $iadl = $this->computeIADLIndex($data);         // x_iadls
        $adl  = $this->computeADLIndex($data);          // x_adlsum
        $rehabMinutes = $this->computeRehabMinutes($data); // x_th_min

        // 3a. Specialized flags
        $flags = [
            'rehab'                => $rehabMinutes >= 120,
            'extensive_services'   => $this->hasExtensiveServices($data),
            'special_care'         => $this->hasSpecialCareIndicators($data, $adl),
            'clinically_complex'   => $this->hasClinicallyComplexIndicators($data, $adl),
            'impaired_cognition'   => $sCPS >= 3,
            'behaviour_problems'   => $this->hasBehaviourProblems($data),
        ];

        // 4. Determine RUG category & group based on CIHI hierarchy
        $rugGroup = $this->determineRugGroup($data, $adl, $iadl, $sCPS, $flags);

        // 5. Map RUG alpha code → numeric rank (aNR3H)
        $rugNumeric = $this->mapRugCodeToNumeric($rugGroup);

        // 6. Persist classification
        return RUGClassification::create([
            'patient_id'      => $assessment->patient_id,
            'assessment_id'   => $assessment->id,
            'rug_group'       => $rugGroup,
            'rug_category'    => $this->rugCategoryFromGroup($rugGroup),
            'adl_sum'         => $adl,
            'iadl_sum'        => $iadl,
            'cps_score'       => $sCPS,
            'flags'           => $flags,
            'numeric_rank'    => $rugNumeric,
        ]);
    }

    // ----------------- Core pieces -----------------

    private function fieldsAreValid(array $d): bool
    {
        // Implement CIHI range checks (mirroring SAS "VARIABLE VALUE CHECK")
        // Return false if any required iCODE is missing or out of range.
    }

    private function computeCPS(array $d): int
    {
        // Port CIHI's create_sCPS_scale macro:
        // - compute xcps1 and xcps2 based on cognition and communication items
        // - assign sCPS from 0–6
    }

    private function computeIADLIndex(array $d): int
    {
        // Use 2025 logic:
        // if location == private_home:
        //   use self-performance items iG1aa, iG1da, iG1ea
        // else:
        //   use capacity items iG1ab, iG1db, iG1eb
        // Count how many are "full help or more".
    }

    private function computeADLIndex(array $d): int
    {
        // Apply scoring conversions for bed mobility, transfer,
        // toilet use, and eating, then sum to x_adlsum (4–18).
    }

    private function computeRehabMinutes(array $d): int
    {
        // x_th_min = sum of minutes from PT, OT, SLP (iN3eb, iN3fb, iN3gb)
    }

    private function hasExtensiveServices(array $d): bool
    {
        // Check IV feeds, IV meds, suctioning, trach care, ventilator flags.
    }

    private function hasSpecialCareIndicators(array $d, int $adl): bool
    {
        // Use CIHI conditions: stage 3/4 ulcers + turning,
        // complex feeding + aphasia, major skin with wound care, etc.
        // Only count as Special Care if ADL >= threshold as per spec.
    }

    private function hasClinicallyComplexIndicators(array $d, int $adl): bool
    {
        // Use CIHI conditions: dehydration, pneumonia,
        // hemiplegia with high ADL, end-stage disease, chemo, etc.
    }

    private function hasBehaviourProblems(array $d): bool
    {
        // Check E3a–E3f behaviour items and J2i, J2h for hallucinations/delusions.
    }

    private function determineRugGroup(array $d, int $adl, int $iadl, int $sCPS, array $flags): string
    {
        // Apply CIHI hierarchy in order:

        // 1. Special Rehabilitation
        if ($flags['rehab']) {
            if ($adl >= 11)      return 'RB0';
            elseif ($adl >= 4) {
                return ($iadl > 1) ? 'RA2' : 'RA1';
            }
        }

        // 2. Extensive Services
        if ($flags['extensive_services'] && $adl >= 7) {
            $extCount = $this->computeExtensiveCount($d, $flags);
            if ($extCount >= 4)  return 'SE3';
            if ($extCount >= 2)  return 'SE2';
            return 'SE1';
        }

        // 3. Special Care
        if ($flags['special_care'] || ($flags['extensive_services'] && $adl <= 6)) {
            if ($adl >= 14)      return 'SSB';
            return 'SSA';
        }

        // 4. Clinically Complex
        if ($flags['clinically_complex'] || $flags['special_care']) {
            if ($adl >= 11)      return 'CC0';
            if ($adl >=  6)      return 'CB0';
            // ADL 4–5
            return ($iadl >= 1) ? 'CA2' : 'CA1';
        }

        // 5. Impaired Cognition
        if ($flags['impaired_cognition'] && $adl <= 10) {
            if ($adl >= 6)       return 'IB0';
            // ADL 4–5
            return ($iadl >= 1) ? 'IA2' : 'IA1';
        }

        // 6. Behaviour Problems
        if ($flags['behaviour_problems'] && $adl <= 10) {
            if ($adl >= 6)       return 'BB0';
            // ADL 4–5
            return ($iadl >= 1) ? 'BA2' : 'BA1';
        }

        // 7. Reduced Physical Functions (catch-all)
        if ($adl >= 11)          return 'PD0';
        if ($adl >=  9)          return 'PC0';
        if ($adl >=  6)          return 'PB0';
        // ADL 4–5
        return ($iadl >= 1) ? 'PA2' : 'PA1';
    }

    private function computeExtensiveCount(array $d, array $flags): int
    {
        // Count of:
        //   - special care
        //   - clinically complex
        //   - impaired cognition
        //   plus IV feeding and IV meds as per CIHI code.
    }
}

3. Bundle Eligibility & Template Selection
class BundleEligibilityService
{
    public function evaluate(Patient $patient, RUGClassification $rug): EligibleStreamsResult
    {
        $streams = [];
        $reasons = [];
        $constraints = [];

        // Example: LTC bundle requires adult, crisis LTC designation,
        // non-palliative, not pediatric.
        if ($patient->age >= 18 && $patient->hasCrisisLTCPriority() && !$patient->isPediatric()) {
            $streams[] = 'LTC';
            $constraints['must_support_24_7'] = true;
        } else {
            $reasons[] = 'Not eligible for LTC bundle based on age or LTC status.';
        }

        return new EligibleStreamsResult($streams, $reasons, $constraints);
    }
}

class CareBundleTemplateRepository
{
    public function findForRug(
        string $stream,
        string $rugGroup,
        int $adl,
        int $iadl,
        array $flags
    ): Collection {
        return CareBundleTemplate::query()
            ->where('funding_stream', $stream)
            ->where(function($q) use ($rugGroup) {
                $q->where('rug_group', $rugGroup)
                  ->orWhereNull('rug_group');
            })
            ->where('min_adl_sum', '<=', $adl)
            ->where('max_adl_sum', '>=', $adl)
            ->where('min_iadl_sum', '<=', $iadl)
            ->where('max_iadl_sum', '>=', $iadl)
            ->get()
            ->filter(function ($template) use ($flags) {
                return $this->flagsMatch($template, $flags);
            });
    }

    private function flagsMatch(CareBundleTemplate $template, array $flags): bool
    {
        // Evaluate required and excluded flags against classification flags.
    }
}

4. Bundle Construction & Rule Application
class CareBundleBuilderService
{
    public function getBundlesForPatient(int $patientId): array
    {
        $patient     = Patient::findOrFail($patientId);
        $assessment  = $patient->latestAssessmentOfType('interRAI_HC');
        $rug         = $assessment->latestRugClassification();

        $eligibility = $this->eligibilityService->evaluate($patient, $rug);

        $bundles = [];

        foreach ($eligibility->eligible_streams as $stream) {
            $templates = $this->templateRepo->findForRug(
                $stream,
                $rug->rug_group,
                $rug->adl_sum,
                $rug->iadl_sum,
                $rug->flags
            );

            foreach ($templates as $template) {
                $bundle = $this->instantiateBundleFromTemplate($patient, $rug, $template);
                $bundle = $this->ruleEngine->applyRules($bundle, $patient, $rug);
                $bundle = $this->costEngine->evaluateBundle($bundle);

                $bundles[] = $bundle;
            }
        }

        return $this->transformer->bundlesToApiResponse($bundles);
    }

    private function instantiateBundleFromTemplate(
        Patient $patient,
        RUGClassification $rug,
        CareBundleTemplate $template
    ): CareBundle {
        $bundle = CareBundle::create([
            'patient_id'          => $patient->id,
            'rug_classification_id' => $rug->id,
            'care_bundle_template_id' => $template->id,
            'status'              => 'draft',
        ]);

        foreach ($template->services as $tplService) {
            CareBundleService::create([
                'care_bundle_id'           => $bundle->id,
                'service_type_id'          => $tplService->service_type_id,
                'frequency_per_week'       => $tplService->default_frequency_per_week,
                'duration_minutes'         => $tplService->default_duration_minutes,
                'cost_per_visit_cents'     => $tplService->serviceType->default_cost_per_visit_cents,
            ]);
        }

        return $bundle->fresh('services');
    }

    public function previewBundle(
        int $patientId,
        int $bundleId,
        array $overrides
    ): CareBundle {
        $bundle = CareBundle::with('services')->where('patient_id', $patientId)->findOrFail($bundleId);

        // Apply overrides (do NOT persist)
        $services = $bundle->services->map(function ($line) use ($overrides) {
            $override = collect($overrides)
                ->firstWhere('service_type_id', $line->service_type_id);

            if ($override) {
                $line->frequency_per_week = $override['currentFrequency'] ?? $line->frequency_per_week;
                $line->duration_minutes   = $override['currentDuration']  ?? $line->duration_minutes;
            }

            return $line;
        });

        $bundle->setRelation('services', $services);

        // Re-evaluate cost/budget only on the in-memory copy
        $bundle = $this->costEngine->evaluateBundle($bundle, persist: false);

        return $bundle;
    }
}

class BundleConfigurationRuleEngine
{
    public function applyRules(CareBundle $bundle, Patient $patient, RUGClassification $rug): CareBundle
    {
        $rules = BundleConfigurationRule::ordered()->get();

        foreach ($rules as $rule) {
            if ($this->matches($rule, $bundle, $patient, $rug)) {
                $bundle = $this->applyAction($rule, $bundle, $patient, $rug);
            }
        }

        return $bundle->fresh('services');
    }

    private function matches(BundleConfigurationRule $rule, CareBundle $bundle, Patient $patient, RUGClassification $rug): bool
    {
        // Evaluate trigger payload (rug_group, rug_category, flags, ranges, etc.)
    }

    private function applyAction(BundleConfigurationRule $rule, CareBundle $bundle, Patient $patient, RUGClassification $rug): CareBundle
    {
        // Perform ADD_SERVICE, REMOVE_SERVICE, ADJUST_FREQUENCY, etc.
    }
}

class CostEngine
{
    public function evaluateBundle(CareBundle $bundle, bool $persist = true): CareBundle
    {
        $weeklyTotal = 0;

        foreach ($bundle->services as $line) {
            $line->calculated_weekly_cost_cents =
                $line->frequency_per_week * $line->cost_per_visit_cents;
            $weeklyTotal += $line->calculated_weekly_cost_cents;
        }

        $bundle->weekly_cost_cents = $weeklyTotal;

        $cap = $bundle->template->weekly_cap_cents ?? 500000; // $5,000
        $bundle->is_within_cap = $weeklyTotal <= $cap;

        $bundle->metadata = array_merge($bundle->metadata ?? [], [
            'weekly_cost'   => $weeklyTotal,
            'weekly_cap'    => $cap,
            'budget_status' => $this->deriveBudgetStatus($weeklyTotal, $cap),
        ]);

        if ($persist) {
            $bundle->push(); // saves bundle + lines
        }

        return $bundle;
    }

    private function deriveBudgetStatus(int $total, int $cap): string
    {
        if ($total <= $cap)           return 'OK';
        if ($total <= (int) round($cap * 1.1)) return 'WARNING';
        return 'OVER_CAP';
    }
}

5. Care Plan Creation & Publishing
class CarePlanService
{
    public function createPlanFromBundle(
        int $patientId,
        int $bundleId,
        array $services,
        ?string $notes = null
    ): CarePlan {
        $bundle = CareBundle::with('services')
            ->where('patient_id', $patientId)
            ->findOrFail($bundleId);

        // Apply service overrides to bundle instance and persist
        foreach ($services as $svcOverride) {
            $line = $bundle->services
                ->firstWhere('service_type_id', $svcOverride['service_type_id']);

            if ($line) {
                $line->frequency_per_week = $svcOverride['currentFrequency'] ?? $line->frequency_per_week;
                $line->duration_minutes   = $svcOverride['currentDuration']  ?? $line->duration_minutes;
                $line->save();
            } else {
                // handle new service addition
            }
        }

        // Recompute costs
        app(CostEngine::class)->evaluateBundle($bundle);

        $plan = CarePlan::create([
            'patient_id'   => $patientId,
            'care_bundle_id' => $bundleId,
            'status'       => 'draft',
            'notes'        => $notes,
            'created_by'   => auth()->id(),
        ]);

        CarePlanEvent::create([
            'care_plan_id' => $plan->id,
            'event_type'   => 'created',
            'diff_payload' => null,
            'user_id'      => auth()->id(),
        ]);

        return $plan->fresh('careBundle.services');
    }

    public function publishPlan(int $patientId, int $planId): CarePlan
    {
        $plan = CarePlan::with('careBundle.services')
            ->where('patient_id', $patientId)
            ->findOrFail($planId);

        // Validate constraints (e.g. budget_status, eligibility, etc.)
        $this->validateBeforePublish($plan);

        $plan->status = 'published';
        $plan->approved_by = auth()->id();
        $plan->save();

        CarePlanEvent::create([
            'care_plan_id' => $plan->id,
            'event_type'   => 'published',
            'diff_payload' => null,
            'user_id'      => auth()->id(),
        ]);

        // Fire domain event for scheduling:
        event(new CarePlanPublished($plan));

        return $plan;
    }

    private function validateBeforePublish(CarePlan $plan): void
    {
        $bundle = $plan->careBundle;

        if ($bundle->metadata['budget_status'] === 'OVER_CAP') {
            // optionally require admin override or throw
        }
    }
}

6. Summary

The pseudocode above:
	1.	Ports the RUG-III/HC SAS classification into a PHP-style service that calculates sCPS, ADL, IADL, and group codes.
	2.	Builds on that classification to select LTC bundle templates.
	3.	Instantiates bundles with configurable rules and cost checks.
	4.	Produces draft and published care plans that can be consumed by scheduling and reporting components.