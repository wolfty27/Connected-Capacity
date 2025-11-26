<?php

namespace App\Services\Finance;

use App\Models\CarePlan;
use Carbon\Carbon;

class BillingService
{
    public function generateShadowBill($periodStart, $periodEnd)
    {
        $start = Carbon::parse($periodStart);
        $end = Carbon::parse($periodEnd);
        $periodDays = $start->diffInDays($end) + 1; // Inclusive

        // Fetch plans active during this period
        $plans = CarePlan::with(['patient.user', 'careBundle', 'serviceAssignments'])
            ->withTrashed()
            ->get()
            ->filter(function ($plan) use ($start, $end) {
                $planStart = $plan->created_at;
                $planEnd = $plan->deleted_at ?? Carbon::now()->addYears(10); // Assume active goes on
                
                // Check overlap
                return $planStart->lt($end) && $planEnd->gt($start);
            });

        $lineItems = [];
        $totalAmount = 0;

        foreach ($plans as $plan) {
            // Calculate Active Days in Period
            $activeStart = $plan->created_at->max($start);
            $activeEnd = ($plan->deleted_at ?? $end)->min($end);
            
            $activeDays = $activeStart->diffInDays($activeEnd) + 1;
            // Clamp to 0 if negative (edge case)
            $activeDays = max(0, $activeDays);

            // Bundle Price (Fallback if 0)
            $bundlePrice = $plan->careBundle->price > 0 ? $plan->careBundle->price : $this->getFallbackPrice($plan->careBundle->code);
            
            // Daily Rate
            $dailyRate = $bundlePrice / 30; // Standard 30-day basis
            
            // Pro-rated Amount
            $amount = $dailyRate * $activeDays;

            $lineItems[] = [
                'patient_id' => $plan->patient->id,
                'patient_name' => $plan->patient->user->name,
                'bundle' => $plan->careBundle->name,
                'start_date' => $activeStart->format('Y-m-d'),
                'end_date' => $activeEnd->format('Y-m-d'),
                'active_days' => $activeDays,
                'daily_rate' => round($dailyRate, 2),
                'total_amount' => round($amount, 2),
                'status' => $activeDays < $periodDays ? 'Pro-rated' : 'Full Period'
            ];

            $totalAmount += $amount;
        }

        return [
            'period_start' => $start->format('Y-m-d'),
            'period_end' => $end->format('Y-m-d'),
            'total_patients' => $plans->unique('patient_id')->count(),
            'total_amount' => round($totalAmount, 2),
            'line_items' => $lineItems
        ];
    }

    private function getFallbackPrice($code)
    {
        return match ($code) {
            'STD-MED' => 1200.00,
            'DEM-SUP' => 2400.00,
            'COMPLEX' => 3100.00,
            default => 1000.00,
        };
    }
}