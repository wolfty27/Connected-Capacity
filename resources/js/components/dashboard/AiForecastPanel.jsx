import React, { useState } from 'react';
import axios from 'axios';

const AiForecastPanel = ({ onRunForecast }) => {
    const [isLoading, setIsLoading] = useState(false);
    const [insights, setInsights] = useState(null);
    const [error, setError] = useState(null);

    const handleRunForecast = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await axios.post('/api/v2/ai/forecast');
            setInsights(response.data.insights);
        } catch (err) {
            console.error('Forecast failed', err);
            setError('Failed to generate forecast. Please try again.');
        } finally {
            setIsLoading(false);
        }
    };

    // Expose run method to parent if needed, or handle internal state
    // For this design, the button is often in the page header, so we might need to lift state up.
    // However, the wireframe shows the panel itself having a placeholder state.
    // Let's assume the "Run" button in the header triggers this, OR this panel has its own trigger.
    // Based on wireframe: Button is in Header. So we'll accept `insights` and `isLoading` as props if controlled,
    // or we can make this panel self-contained if the button was inside it.
    // The wireframe has the button in the header. 
    // BUT, to keep it simple for now, I'll make this panel display the results, and maybe the parent controls the fetching.
    // Actually, let's make this panel handle the display logic, and the parent passes the data.

    // REVISION: To match the wireframe exactly, the panel shows a placeholder "Click Run Forecast".
    // So the parent `CareDashboardPage` will hold the state and pass it down.

    return (
        <div className="bg-indigo-50 rounded-xl border border-indigo-100 p-6 relative overflow-hidden h-full">
            <div className="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-indigo-100 rounded-full blur-2xl"></div>

            <h3 className="font-bold text-indigo-900 mb-4 flex items-center gap-2 relative z-10">
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                AI Capacity Forecast
            </h3>

            {/* Loading State */}
            {isLoading && (
                <div className="flex flex-col items-center justify-center py-8 relative z-10">
                    <div className="w-8 h-8 border-2 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mb-3"></div>
                    <p className="text-indigo-600 text-sm font-medium">Analyzing schedules...</p>
                </div>
            )}

            {/* Empty State */}
            {!isLoading && !insights && !error && (
                <div className="text-center py-8 text-indigo-400 relative z-10">
                    <p className="text-sm mb-4">Click 'Run Forecast' to analyze staff schedules against incoming referrals.</p>
                </div>
            )}

            {/* Error State */}
            {error && (
                <div className="p-3 rounded-lg bg-red-50 text-red-600 text-sm text-center relative z-10">
                    {error}
                </div>
            )}

            {/* Results */}
            {!isLoading && insights && (
                <div className="space-y-4 relative z-10 animate-fade-in-up">
                    {insights.map((insight, index) => (
                        <div
                            key={index}
                            className={`bg-white p-3 rounded-lg shadow-sm border-l-4 ${insight.type === 'warning' ? 'border-amber-400' : 'border-emerald-400'
                                }`}
                        >
                            <h4 className="text-xs font-bold text-slate-400 uppercase">{insight.title}</h4>
                            <p className="text-sm font-medium text-slate-700 mt-1">{insight.description}</p>
                            {insight.metric && (
                                <p className="text-xs text-slate-400 mt-1">{insight.metric}</p>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};

export default AiForecastPanel;
