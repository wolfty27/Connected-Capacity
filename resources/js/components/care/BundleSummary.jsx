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

const BundleSummary = ({ services = [], totalCost = 0, isGeneratingAi = false, aiRecommendation = null, onGenerateAi = () => { }, bundleName = '' }) => {

    const getServiceIcon = (name) => {
        if (!name || typeof name !== 'string') return <Activity className="w-5 h-5 text-slate-400" />;

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

    const bundleServices = services.filter(s => s.currentFrequency > 0 && s.defaultFrequency > 0);
    const addedServices = services.filter(s => s.currentFrequency > 0 && s.defaultFrequency === 0);

    const ServiceItem = ({ service }) => (
        <div className="border border-slate-200 rounded-lg p-3">
            <div className="flex items-start gap-3 mb-2">
                <div className="flex-shrink-0 mt-0.5">
                    {getServiceIcon(service.name)}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="font-bold text-slate-900 text-xs leading-tight">{service.name}</div>
                    <div className="text-[10px] text-slate-500">{service.code}</div>
                </div>
            </div>
            <div className="flex justify-between items-center pt-2 border-t border-slate-100">
                <div className="font-medium text-slate-900 text-xs">
                    {service.currentFrequency} {service.code === 'PSW' ? 'hours/week' : 'visits/week'}
                </div>
                <div className="text-[10px] text-slate-500">{service.currentDuration} wks</div>
            </div>
        </div>
    );

    return (
        <div className="space-y-6">
            {/* Bundle Summary */}
            <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
                <h3 className="text-lg font-bold text-slate-900 mb-6">Bundle Summary</h3>

                <div className="space-y-6 mb-8">
                    {/* Bundle Services Section */}
                    <div>
                        <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
                            {bundleName || 'Selected Bundle'}
                        </h4>
                        <div className="space-y-3">
                            {bundleServices.length > 0 ? (
                                bundleServices.map(s => <ServiceItem key={s.id} service={s} />)
                            ) : (
                                <p className="text-sm text-slate-400 italic">No bundle services selected.</p>
                            )}
                        </div>
                    </div>

                    {/* Added Services Section */}
                    {addedServices.length > 0 && (
                        <div>
                            <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
                                Added Services
                            </h4>
                            <div className="space-y-3">
                                {addedServices.map(s => <ServiceItem key={s.id} service={s} />)}
                            </div>
                        </div>
                    )}

                    {bundleServices.length === 0 && addedServices.length === 0 && (
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
                        aiRecommendation || "Recommendation: Based on the RUG classification and ADL hierarchy, consider adjusting Personal Support hours."
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
