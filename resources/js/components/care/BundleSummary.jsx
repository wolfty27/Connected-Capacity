import React from 'react';
import {
    Bot,
    Stethoscope,
    User,
    Activity,
    Hand,
    MessageSquare,
    BarChart,
    FlaskConical
} from 'lucide-react';

const BundleSummary = ({ services, totalCost, isGeneratingAi, aiRecommendation, onGenerateAi }) => {

    const getServiceIcon = (name) => {
        const lowerName = name.toLowerCase();
        if (lowerName.includes('nursing')) return <Stethoscope className="w-5 h-5 text-slate-400" />;
        if (lowerName.includes('personal') || lowerName.includes('psw')) return <User className="w-5 h-5 text-slate-400" />;
        if (lowerName.includes('physio') || lowerName.includes('pt')) return <Activity className="w-5 h-5 text-slate-400" />;
        if (lowerName.includes('occupational') || lowerName.includes('ot')) return <Hand className="w-5 h-5 text-slate-400" />;
        if (lowerName.includes('social') || lowerName.includes('sw')) return <MessageSquare className="w-5 h-5 text-slate-400" />;
        if (lowerName.includes('monitoring') || lowerName.includes('rpm')) return <BarChart className="w-5 h-5 text-slate-400" />;
        if (lowerName.includes('lab')) return <FlaskConical className="w-5 h-5 text-slate-400" />;
        return <Activity className="w-5 h-5 text-slate-400" />;
    };

    return (
        <div className="space-y-6">
            {/* Bundle Summary */}
            <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
                <h3 className="text-lg font-bold text-slate-900 mb-6">Bundle Summary</h3>

                <div className="space-y-3 mb-8">
                    {services.filter(s => s.currentFrequency > 0).map(s => (
                        <div key={s.id} className="border border-slate-200 rounded-lg p-4 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex-shrink-0">
                                    {getServiceIcon(s.name)}
                                </div>
                                <div>
                                    <div className="font-bold text-slate-900 text-sm">{s.name}</div>
                                    <div className="text-xs text-slate-500">{s.code}</div>
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="font-medium text-slate-900 text-sm">{s.currentFrequency} visits/week</div>
                                <div className="text-xs text-slate-500">for {s.currentDuration} weeks</div>
                            </div>
                        </div>
                    ))}
                    {services.filter(s => s.currentFrequency > 0).length === 0 && (
                        <p className="text-sm text-slate-400 italic text-center py-4">No services selected.</p>
                    )}
                </div>

                <div className="pt-6 border-t border-slate-200 flex justify-between items-center">
                    <span className="font-bold text-slate-900 text-lg">Total Est. Weekly Cost:</span>
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
