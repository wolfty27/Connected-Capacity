<?php

namespace App\Jobs;

use App\Models\TransitionNeedsProfile;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTnpAiAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tnpId;

    /**
     * Create a new job instance.
     */
    public function __construct($tnpId)
    {
        $this->tnpId = $tnpId;
    }

    /**
     * Execute the job.
     */
    public function handle(GeminiService $geminiService): void
    {
        $tnp = TransitionNeedsProfile::find($this->tnpId);

        if (!$tnp) {
            Log::error("TNP ID {$this->tnpId} not found for AI analysis.");
            return;
        }

        $tnp->update(['ai_summary_status' => 'processing']);

        try {
            // Assuming narrative_summary is the source text
            $text = $tnp->narrative_summary ?? 'No narrative provided.';
            
            $summary = $geminiService->summarize($text);
            
            // Simple risk analysis simulation
            // $risks = $geminiService->analyzeRisk($tnp->toArray()); 

            $tnp->update([
                'ai_summary_text' => $summary,
                'ai_summary_status' => 'completed',
                // 'clinical_flags' => array_merge($tnp->clinical_flags ?? [], $risks['flags']) // Example merge
            ]);

        } catch (\Exception $e) {
            Log::error("AI Analysis failed for TNP {$this->tnpId}: " . $e->getMessage());
            $tnp->update(['ai_summary_status' => 'failed']);
        }
    }
}