import React from 'react';
import { Bot } from 'lucide-react';

const BundleSummary = ({ services, totalCost, isGeneratingAi, aiRecommendation, onGenerateAi }) => {
    return (
        <div className="space-y-6">
            {/* Bundle Summary */}
            <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
                <h3 className="text-lg font-bold text-slate-900 mb-4">Bundle Summary</h3>
                <div className="space-y-3 mb-6">
                    {services.filter(s => s.currentFrequency > 0).map(s => (
                        <div key={s.id} className="flex justify-between text-sm">
                            <span className="text-slate-700">{s.name}</span>
                            <span className="text-slate-900 font-medium text-right">{s.currentFrequency}/wk, {s.currentDuration} wks</span>
                        </div>
                    ))}
                    {services.filter(s => s.currentFrequency > 0).length === 0 && (
                        <p className="text-sm text-slate-400 italic">No services selected.</p>
                    )}
                </div>
                <div className="pt-4 border-t border-slate-200 flex justify-between items-center">
                    <span className="font-bold text-slate-900">Total Est. Cost:</span>
                    <span className="font-bold text-slate-900 text-lg">${totalCost.toLocaleString()}</span>
                </div>
            </div>

            {/* Clinical Recommendation */}
            <div className="bg-emerald-800 rounded-lg shadow-sm p-6 text-white relative overflow-hidden">
                <div className="flex items-center justify-between mb-2">
                    <h3 className="text-md font-bold">Clinical Recommendation</h3>
                    <Bot className="w-5 h-5 opacity-80" />
                </div>
                <div className="text-sm opacity-90 leading-relaxed mb-4 min-h-[80px]">
                    {isGeneratingAi ? (
                        <span className="animate-pulse">Generating clinical insights...</span>
                    ) : (
                        aiRecommendation || "Recommendation: Based on the high TNP score, consider increasing Personal Support hours."
                    )}
                </div>

                <button
                    onClick={onGenerateAi}
                    disabled={isGeneratingAi}
                    className="w-full text-center py-2 bg-emerald-700 bg-opacity-50 hover:bg-opacity-70 rounded text-xs font-medium transition-colors border border-emerald-600"
                >
                    Refresh AI Analysis
                </button>
            </div>
        </div>
    );
};

export default BundleSummary;
