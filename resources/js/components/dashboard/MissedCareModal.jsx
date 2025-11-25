import React, { useState, useEffect } from 'react';

const MissedCareModal = ({ isOpen, onClose, missedCareCount }) => {
    const [step, setStep] = useState('loading'); // loading, analysis, action
    const [analysis, setAnalysis] = useState(null);

    useEffect(() => {
        if (isOpen) {
            setStep('loading');
            // Simulate AI Analysis delay
            setTimeout(() => {
                setAnalysis({
                    rootCause: 'Patient Refusal / Family Interference',
                    confidence: 'High (92%)',
                    details: 'Field notes indicate staff arrived at 09:00. Patient daughter refused entry, stating patient was sleeping. No prior cancellation received.',
                    recommendation: 'Mark as "Client Declined" (Non-Culpable). Schedule Care Conference with family.'
                });
                setStep('analysis');
            }, 2000);
        }
    }, [isOpen]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden animate-fade-in-up">
                {/* Header */}
                <div className="bg-rose-600 px-6 py-4 flex justify-between items-center text-white">
                    <h3 className="font-bold flex items-center gap-2">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                        Missed Care Analysis
                    </h3>
                    <button onClick={onClose} className="hover:text-rose-200 text-xl font-bold">&times;</button>
                </div>

                {/* Content */}
                <div className="p-6 min-h-[300px]">

                    {step === 'loading' && (
                        <div className="flex flex-col items-center justify-center h-full py-12">
                            <div className="w-12 h-12 border-4 border-rose-100 border-t-rose-600 rounded-full animate-spin mb-4"></div>
                            <p className="text-rose-600 font-medium">Gemini is analyzing field notes & GPS logs...</p>
                            <p className="text-slate-400 text-sm mt-2">Processing {missedCareCount} event(s)</p>
                        </div>
                    )}

                    {step === 'analysis' && analysis && (
                        <div className="space-y-6 animate-fade-in">
                            {/* Root Cause Section */}
                            <div className="bg-rose-50 p-4 rounded-lg border border-rose-100">
                                <h4 className="text-xs font-bold text-rose-800 uppercase tracking-wider mb-2">AI Root Cause Identification</h4>
                                <div className="flex items-start gap-3">
                                    <div className="text-2xl">🚫</div>
                                    <div>
                                        <p className="font-bold text-slate-800 text-lg">{analysis.rootCause}</p>
                                        <p className="text-sm text-slate-600 mt-1">{analysis.details}</p>
                                    </div>
                                </div>
                            </div>

                            {/* Recommendation Section */}
                            <div className="bg-emerald-50 p-4 rounded-lg border border-emerald-100">
                                <h4 className="text-xs font-bold text-emerald-800 uppercase tracking-wider mb-2">Recommended Action</h4>
                                <div className="flex items-start gap-3">
                                    <div className="text-2xl">✅</div>
                                    <div>
                                        <p className="font-bold text-slate-800">{analysis.recommendation}</p>
                                    </div>
                                </div>
                            </div>

                            {/* Actions */}
                            <div className="flex gap-3 pt-4">
                                <button
                                    onClick={onClose}
                                    className="flex-1 bg-rose-600 hover:bg-rose-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition-colors"
                                >
                                    Accept & Resolve
                                </button>
                                <button
                                    onClick={onClose}
                                    className="px-4 py-2 text-slate-500 font-medium hover:bg-slate-50 rounded-lg"
                                >
                                    Dismiss
                                </button>
                            </div>
                        </div>
                    )}

                </div>
            </div>
        </div>
    );
};

export default MissedCareModal;
