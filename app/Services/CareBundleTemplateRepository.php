<?php

namespace App\Services;

use App\Models\CareBundleTemplate;
use App\Models\Patient;
use App\Models\RUGClassification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * CareBundleTemplateRepository
 *
 * Retrieves and matches care bundle templates based on RUG classification.
 * This is the primary interface for finding the right template for a patient.
 *
 * Template matching follows this hierarchy:
 * 1. Exact RUG group match (e.g., CB0 -> LTC_CB0_STANDARD)
 * 2. RUG category match (e.g., Clinically Complex -> any CC template)
 * 3. ADL/IADL range match
 * 4. Flag compatibility check
 *
 * @see docs/CC21_BundleEngine_Architecture.md
 */
class CareBundleTemplateRepository
{
    /**
     * Find the best matching template for a patient.
     *
     * @param Patient $patient
     * @return CareBundleTemplate|null
     */
    public function findForPatient(Patient $patient): ?CareBundleTemplate
    {
        $rug = $patient->latestRugClassification;

        if (!$rug) {
            Log::warning('No RUG classification for patient', ['patient_id' => $patient->id]);
            return null;
        }

        return $this->findForClassification($rug);
    }

    /**
     * Find the best matching template for a RUG classification.
     *
     * @param RUGClassification $rug
     * @return CareBundleTemplate|null
     */
    public function findForClassification(RUGClassification $rug): ?CareBundleTemplate
    {
        // 1. Try exact RUG group match first
        $template = $this->findByRugGroup($rug->rug_group);
        if ($template && $template->matchesClassification($rug)) {
            return $template;
        }

        // 2. Try category match with ADL/IADL filtering
        $template = $this->findByCategoryAndScores(
            $rug->rug_category,
            $rug->adl_sum,
            $rug->iadl_sum,
            $rug->flags ?? []
        );

        if ($template) {
            return $template;
        }

        // 3. Fall back to any matching template by scores
        return $this->findByScoresOnly($rug->adl_sum, $rug->iadl_sum, $rug->flags ?? []);
    }

    /**
     * Find template by exact RUG group.
     */
    public function findByRugGroup(string $rugGroup): ?CareBundleTemplate
    {
        return CareBundleTemplate::active()
            ->forRugGroup($rugGroup)
            ->first();
    }

    /**
     * Find template by RUG category and scores.
     */
    public function findByCategoryAndScores(
        string $category,
        int $adlSum,
        int $iadlSum,
        array $flags = []
    ): ?CareBundleTemplate {
        $templates = CareBundleTemplate::active()
            ->forRugCategory($category)
            ->matchingAdl($adlSum)
            ->matchingIadl($iadlSum)
            ->orderBy('priority_weight', 'desc')
            ->get();

        // Find best match considering flags
        foreach ($templates as $template) {
            if ($template->matchesFlags($flags)) {
                return $template;
            }
        }

        return $templates->first();
    }

    /**
     * Find template by ADL/IADL scores only (fallback).
     */
    public function findByScoresOnly(int $adlSum, int $iadlSum, array $flags = []): ?CareBundleTemplate
    {
        $templates = CareBundleTemplate::active()
            ->matchingAdl($adlSum)
            ->matchingIadl($iadlSum)
            ->orderBy('priority_weight', 'desc')
            ->get();

        foreach ($templates as $template) {
            if ($template->matchesFlags($flags)) {
                return $template;
            }
        }

        return $templates->first();
    }

    /**
     * Get all templates that could match a classification.
     * Returns multiple options for user selection.
     *
     * @param RUGClassification $rug
     * @return Collection<CareBundleTemplate>
     */
    public function findAllMatchingTemplates(RUGClassification $rug): Collection
    {
        $matches = collect();

        // Primary match by exact RUG group
        $primary = $this->findByRugGroup($rug->rug_group);
        if ($primary) {
            $matches->push([
                'template' => $primary,
                'match_type' => 'exact',
                'match_score' => 100,
                'is_recommended' => true,
            ]);
        }

        // Alternative matches by category
        $categoryTemplates = CareBundleTemplate::active()
            ->forRugCategory($rug->rug_category)
            ->where('rug_group', '!=', $rug->rug_group)
            ->matchingAdl($rug->adl_sum)
            ->orderBy('priority_weight', 'desc')
            ->get();

        foreach ($categoryTemplates as $template) {
            $score = $this->calculateMatchScore($template, $rug);
            $matches->push([
                'template' => $template,
                'match_type' => 'category',
                'match_score' => $score,
                'is_recommended' => false,
            ]);
        }

        // Cross-category alternatives for edge cases
        $crossCategory = CareBundleTemplate::active()
            ->where('rug_category', '!=', $rug->rug_category)
            ->matchingAdl($rug->adl_sum)
            ->matchingIadl($rug->iadl_sum)
            ->orderBy('priority_weight', 'desc')
            ->take(3)
            ->get();

        foreach ($crossCategory as $template) {
            if (!$matches->contains('template.id', $template->id)) {
                $score = $this->calculateMatchScore($template, $rug);
                if ($score >= 50) {
                    $matches->push([
                        'template' => $template,
                        'match_type' => 'alternative',
                        'match_score' => $score,
                        'is_recommended' => false,
                    ]);
                }
            }
        }

        return $matches->sortByDesc('match_score')->values();
    }

    /**
     * Calculate match score between a template and classification.
     */
    protected function calculateMatchScore(CareBundleTemplate $template, RUGClassification $rug): int
    {
        $score = 0;

        // RUG group match (50 points)
        if ($template->rug_group === $rug->rug_group) {
            $score += 50;
        }

        // RUG category match (25 points)
        if ($template->rug_category === $rug->rug_category) {
            $score += 25;
        }

        // ADL range match (15 points)
        if ($rug->adl_sum >= $template->min_adl_sum && $rug->adl_sum <= $template->max_adl_sum) {
            $score += 15;
        }

        // IADL range match (10 points)
        if ($rug->iadl_sum >= $template->min_iadl_sum && $rug->iadl_sum <= $template->max_iadl_sum) {
            $score += 10;
        }

        // Flag compatibility bonus (up to 10 points)
        if ($template->matchesFlags($rug->flags ?? [])) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * Get all active templates.
     */
    public function getAllActive(): Collection
    {
        return CareBundleTemplate::active()
            ->with('services.serviceType')
            ->orderBy('priority_weight', 'desc')
            ->get();
    }

    /**
     * Get templates by category.
     */
    public function getByCategory(string $category): Collection
    {
        return CareBundleTemplate::active()
            ->forRugCategory($category)
            ->with('services.serviceType')
            ->orderBy('priority_weight', 'desc')
            ->get();
    }

    /**
     * Get templates by funding stream.
     */
    public function getByFundingStream(string $stream = 'LTC'): Collection
    {
        return CareBundleTemplate::active()
            ->forFundingStream($stream)
            ->with('services.serviceType')
            ->orderBy('priority_weight', 'desc')
            ->get();
    }

    /**
     * Get a specific template by code.
     */
    public function findByCode(string $code): ?CareBundleTemplate
    {
        return CareBundleTemplate::active()
            ->where('code', $code)
            ->with('services.serviceType')
            ->first();
    }

    /**
     * Get a specific template by ID.
     */
    public function findById(int $id): ?CareBundleTemplate
    {
        return CareBundleTemplate::active()
            ->where('id', $id)
            ->with('services.serviceType')
            ->first();
    }

    /**
     * Get template recommendation summary for a patient.
     */
    public function getRecommendationSummary(Patient $patient): array
    {
        $rug = $patient->latestRugClassification;

        if (!$rug) {
            return [
                'status' => 'no_classification',
                'message' => 'Patient requires InterRAI assessment and RUG classification',
                'recommended_template' => null,
                'alternatives' => [],
            ];
        }

        $matches = $this->findAllMatchingTemplates($rug);
        $recommended = $matches->firstWhere('is_recommended', true);

        return [
            'status' => 'ready',
            'rug_group' => $rug->rug_group,
            'rug_category' => $rug->rug_category,
            'recommended_template' => $recommended ? [
                'id' => $recommended['template']->id,
                'code' => $recommended['template']->code,
                'name' => $recommended['template']->name,
                'match_score' => $recommended['match_score'],
                'weekly_cap' => $recommended['template']->weekly_cap,
            ] : null,
            'alternatives' => $matches->where('is_recommended', false)
                ->take(3)
                ->map(fn($m) => [
                    'id' => $m['template']->id,
                    'code' => $m['template']->code,
                    'name' => $m['template']->name,
                    'match_score' => $m['match_score'],
                    'match_type' => $m['match_type'],
                ])
                ->values()
                ->toArray(),
        ];
    }
}
